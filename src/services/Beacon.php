<?php

namespace justinholtweb\garrison\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use craft\helpers\FileHelper;
use justinholtweb\garrison\enums\BlockReason;
use justinholtweb\garrison\enums\NotificationChannel;
use justinholtweb\garrison\models\Edition;
use justinholtweb\garrison\models\ScanReport;
use justinholtweb\garrison\Plugin;
use justinholtweb\garrison\queue\jobs\SendNotificationJob;
use justinholtweb\garrison\records\FileBaselineRecord;

/**
 * Beacon — outbound notifications and file integrity monitoring.
 *
 * Notifications fan out to any configured channel (email, Slack, Discord,
 * generic webhook). Delivery is queued so a slow webhook never blocks the
 * request that triggered it.
 */
class Beacon extends Component
{
    /**
     * Notify that a request was blocked / a threat was detected.
     *
     * Deduplicated per IP + reason so a flood of blocked requests doesn't
     * produce a flood of notifications.
     */
    public function notifyThreat(BlockReason $reason, string $ip, array $details = []): void
    {
        $settings = Plugin::getInstance()->getSettings();
        if (!$settings->enableNotifications) {
            return;
        }

        $wanted = $reason === BlockReason::LoginLockout
            ? $settings->notifyOnLoginLockout
            : $settings->notifyOnThreatDetected;
        if (!$wanted) {
            return;
        }

        $dedupeKey = "garrison:notified:{$reason->value}:$ip";
        if (Craft::$app->getCache()->get($dedupeKey)) {
            return;
        }
        Craft::$app->getCache()->set($dedupeKey, true, 300);

        $subject = Craft::t('garrison', 'Garrison: {reason} from {ip}', [
            'reason' => $reason->label(),
            'ip' => $ip,
        ]);
        $body = $this->renderLines(array_merge([
            'Reason' => $reason->label(),
            'IP address' => $ip,
            'Time' => gmdate('Y-m-d H:i:s') . ' UTC',
        ], $this->stringifyDetails($details)));

        $this->send('threat', $subject, $body, [
            'reason' => $reason->value,
            'ip' => $ip,
            'details' => $details,
        ]);
    }

    /**
     * Notify about a completed scan when it failed or hit a critical issue.
     */
    public function notifyScanResult(ScanReport $report): void
    {
        $settings = Plugin::getInstance()->getSettings();
        if (!$settings->enableNotifications || !$settings->notifyOnScanFailure) {
            return;
        }

        if ($report->criticalChecks === 0 && $report->failedChecks === 0) {
            return;
        }

        $subject = Craft::t('garrison', 'Garrison scan: {status} ({score}/100)', [
            'status' => $report->status->label(),
            'score' => $report->riskScore,
        ]);
        $body = $this->renderLines([
            'Status' => $report->status->label(),
            'Risk score' => "{$report->riskScore}/100 ({$report->riskLabel()})",
            'Critical' => (string) $report->criticalChecks,
            'Failed' => (string) $report->failedChecks,
            'Warnings' => (string) $report->warningChecks,
            'Passed' => (string) $report->passedChecks,
        ]);

        $this->send('scan', $subject, $body, ['scanId' => $report->id, 'riskScore' => $report->riskScore]);
    }

    /**
     * Queue a notification for delivery to every configured channel.
     */
    public function send(string $type, string $subject, string $body, array $data = []): void
    {
        Craft::$app->getQueue()->push(new SendNotificationJob([
            'type' => $type,
            'subject' => $subject,
            'body' => $body,
            'data' => $data,
        ]));
    }

    /**
     * Deliver a notification synchronously to all configured channels. Called
     * by SendNotificationJob. Returns the channels that were attempted.
     *
     * @return string[]
     */
    public function dispatch(string $subject, string $body, array $data = []): array
    {
        $settings = Plugin::getInstance()->getSettings();
        $sent = [];

        if (!empty($settings->emailRecipients)) {
            $this->sendEmail($settings->emailRecipients, $subject, $body);
            $sent[] = NotificationChannel::Email->value;
        }
        if ($settings->slackWebhookUrl !== '') {
            $this->postJson($settings->slackWebhookUrl, ['text' => "*$subject*\n$body"]);
            $sent[] = NotificationChannel::Slack->value;
        }
        if ($settings->discordWebhookUrl !== '') {
            $this->postJson($settings->discordWebhookUrl, ['content' => "**$subject**\n$body"]);
            $sent[] = NotificationChannel::Discord->value;
        }
        if ($settings->webhookUrl !== '') {
            $this->postJson($settings->webhookUrl, [
                'subject' => $subject,
                'body' => $body,
                'data' => $data,
            ]);
            $sent[] = NotificationChannel::Webhook->value;
        }

        return $sent;
    }

    // File integrity (Pro)
    // -------------------------------------------------------------------------

    /**
     * Hash every file under the monitored paths and store it as the baseline,
     * replacing any previous baseline.
     *
     * @return int Number of files recorded.
     */
    public function createBaseline(): int
    {
        Edition::requiresPro('File integrity monitoring');

        FileBaselineRecord::deleteAll();

        $now = Db::prepareDateForDb(new \DateTime('now', new \DateTimeZone('UTC')));
        $count = 0;

        foreach ($this->collectFiles() as $file) {
            $record = new FileBaselineRecord();
            $record->filePath = $this->relativePath($file);
            $record->fileHash = (string) hash_file('sha256', $file);
            $record->fileSize = (int) filesize($file);
            $record->filePermissions = substr(sprintf('%o', fileperms($file)), -4);
            $record->status = 'ok';
            $record->lastChecked = $now;
            $record->save(false);
            $count++;
        }

        return $count;
    }

    /**
     * Compare the current filesystem against the baseline.
     *
     * @return array{modified: string[], deleted: string[], added: string[]}
     */
    public function checkIntegrity(): array
    {
        Edition::requiresPro('File integrity monitoring');

        $changes = ['modified' => [], 'deleted' => [], 'added' => []];
        /** @var FileBaselineRecord[] $baselines */
        $baselines = FileBaselineRecord::find()->all();
        $root = $this->root();
        $seen = [];

        foreach ($baselines as $baseline) {
            $absolute = $root . DIRECTORY_SEPARATOR . $baseline->filePath;
            $seen[$baseline->filePath] = true;

            if (!file_exists($absolute)) {
                $changes['deleted'][] = $baseline->filePath;
                $baseline->status = 'deleted';
                $baseline->save(false);
                continue;
            }

            if (hash_file('sha256', $absolute) !== $baseline->fileHash) {
                $changes['modified'][] = $baseline->filePath;
                $baseline->status = 'modified';
            } else {
                $baseline->status = 'ok';
            }
            $baseline->lastChecked = Db::prepareDateForDb(new \DateTime('now', new \DateTimeZone('UTC')));
            $baseline->save(false);
        }

        foreach ($this->collectFiles() as $file) {
            $relative = $this->relativePath($file);
            if (!isset($seen[$relative])) {
                $changes['added'][] = $relative;
            }
        }

        $total = count($changes['modified']) + count($changes['deleted']) + count($changes['added']);
        if ($total > 0) {
            $this->notifyIntegrityChanges($changes);
        }

        return $changes;
    }

    public function getBaselineCount(): int
    {
        return (int) FileBaselineRecord::find()->count();
    }

    /**
     * @return FileBaselineRecord[]
     */
    public function getChangedBaselines(): array
    {
        /** @var FileBaselineRecord[] $records */
        $records = FileBaselineRecord::find()
            ->where(['not', ['status' => 'ok']])
            ->orderBy(['filePath' => SORT_ASC])
            ->all();

        return $records;
    }

    // Internal
    // -------------------------------------------------------------------------

    private function notifyIntegrityChanges(array $changes): void
    {
        $settings = Plugin::getInstance()->getSettings();
        if (!$settings->enableNotifications || !$settings->notifyOnThreatDetected) {
            return;
        }

        $subject = Craft::t('garrison', 'Garrison: file integrity changes detected');
        $body = $this->renderLines([
            'Modified' => (string) count($changes['modified']),
            'Deleted' => (string) count($changes['deleted']),
            'Added' => (string) count($changes['added']),
        ]);

        $this->send('integrity', $subject, $body, $changes);
    }

    private function sendEmail(array $recipients, string $subject, string $body): void
    {
        try {
            Craft::$app->getMailer()->compose()
                ->setTo($recipients)
                ->setSubject($subject)
                ->setHtmlBody(nl2br(htmlspecialchars($body)))
                ->send();
        } catch (\Throwable $e) {
            Craft::warning('Garrison email notification failed: ' . $e->getMessage(), 'garrison');
        }
    }

    private function postJson(string $url, array $payload): void
    {
        try {
            Craft::createGuzzleClient()->post($url, [
                'json' => $payload,
                'timeout' => 10,
            ]);
        } catch (\Throwable $e) {
            Craft::warning('Garrison webhook notification failed: ' . $e->getMessage(), 'garrison');
        }
    }

    /**
     * @return \Generator<string>
     */
    private function collectFiles(): \Generator
    {
        $settings = Plugin::getInstance()->getSettings();
        $root = $this->root();

        foreach ($settings->monitoredPaths as $path) {
            $absolute = $root . DIRECTORY_SEPARATOR . ltrim($path, '/');

            if (is_file($absolute)) {
                yield $absolute;
            } elseif (is_dir($absolute)) {
                foreach (FileHelper::findFiles($absolute) as $file) {
                    yield $file;
                }
            }
        }
    }

    private function relativePath(string $absolute): string
    {
        $root = $this->root() . DIRECTORY_SEPARATOR;

        return str_starts_with($absolute, $root) ? substr($absolute, strlen($root)) : $absolute;
    }

    private function root(): string
    {
        return rtrim((string) Craft::getAlias('@root'), '/\\');
    }

    /**
     * @return array<string, string>
     */
    private function stringifyDetails(array $details): array
    {
        $out = [];
        foreach ($details as $key => $value) {
            $label = ucfirst(preg_replace('/(?<!^)[A-Z]/', ' $0', (string) $key));
            $out[$label] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return $out;
    }

    /**
     * @param array<string, string> $lines
     */
    private function renderLines(array $lines): string
    {
        $out = [];
        foreach ($lines as $label => $value) {
            $out[] = "$label: $value";
        }

        return implode("\n", $out);
    }
}
