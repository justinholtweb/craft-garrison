<?php

namespace justinholtweb\garrison\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use justinholtweb\garrison\models\Edition;
use justinholtweb\garrison\Plugin;
use justinholtweb\garrison\records\AuditLogRecord;

/**
 * Sentinel — audit logging.
 *
 * Records user and system actions to the garrison_audit_log table. Event
 * listeners that feed this service are registered in Plugin::init() and only
 * fire when the `enableAuditLog` setting is on.
 */
class Sentinel extends Component
{
    /**
     * Log an audit event.
     *
     * Context (acting user, IP, user agent, site) is filled in automatically
     * from the current request unless explicitly provided in $attributes.
     *
     * @param string $action An AuditAction value.
     * @param string $category A short grouping key (auth, content, settings, system).
     * @param array $attributes Optional overrides: userId, userName, targetType,
     *                          targetId, targetTitle, details, ipAddress, userAgent, siteId.
     */
    public function log(string $action, string $category, array $attributes = []): void
    {
        $record = new AuditLogRecord();
        $record->action = $action;
        $record->category = $category;
        $record->targetType = $attributes['targetType'] ?? null;
        $record->targetId = $attributes['targetId'] ?? null;
        $record->targetTitle = $attributes['targetTitle'] ?? null;
        $record->details = $attributes['details'] ?? null;

        $this->fillContext($record, $attributes);

        // Never let audit logging interrupt the action being logged.
        try {
            $record->save(false);
        } catch (\Throwable $e) {
            Craft::warning('Garrison could not write audit log: ' . $e->getMessage(), 'garrison');
        }
    }

    /**
     * Query the audit log.
     *
     * @param array $criteria Optional filters: action, category, userId, ipAddress.
     * @return AuditLogRecord[]
     */
    public function getAuditLog(array $criteria = [], int $limit = 100, int $offset = 0): array
    {
        /** @var AuditLogRecord[] $records */
        $records = $this->buildQuery($criteria)
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit($limit)
            ->offset($offset)
            ->all();

        return $records;
    }

    public function getAuditLogCount(array $criteria = []): int
    {
        return (int) $this->buildQuery($criteria)->count();
    }

    /**
     * Delete audit log entries older than the edition/configured retention window.
     *
     * @return int Number of rows deleted.
     */
    public function pruneOldLogs(): int
    {
        $settings = Plugin::getInstance()->getSettings();
        $days = $settings->auditLogRetentionDays ?? Edition::auditLogRetentionDays();

        $cutoff = Db::prepareDateForDb(
            (new \DateTime('now', new \DateTimeZone('UTC')))->modify("-{$days} days")
        );

        return AuditLogRecord::deleteAll(['<', 'dateCreated', $cutoff]);
    }

    private function buildQuery(array $criteria): \craft\db\ActiveQuery
    {
        $query = AuditLogRecord::find();

        foreach (['action', 'category', 'userId', 'ipAddress'] as $field) {
            if (!empty($criteria[$field])) {
                $query->andWhere([$field => $criteria[$field]]);
            }
        }

        return $query;
    }

    private function fillContext(AuditLogRecord $record, array $attributes): void
    {
        if (array_key_exists('siteId', $attributes)) {
            $record->siteId = $attributes['siteId'];
        } else {
            $record->siteId = Craft::$app->getIsInstalled()
                ? Craft::$app->getSites()->getCurrentSite()->id
                : null;
        }

        // Acting user
        if (array_key_exists('userId', $attributes)) {
            $record->userId = $attributes['userId'];
            $record->userName = $attributes['userName'] ?? null;
        } else {
            $identity = Craft::$app->getUser()->getIdentity();
            if ($identity) {
                $record->userId = $identity->id;
                $record->userName = $identity->username;
            }
        }

        // Request context (absent on console)
        $request = Craft::$app->getRequest();
        if (!$request->getIsConsoleRequest()) {
            $record->ipAddress = $attributes['ipAddress'] ?? $request->getUserIP();
            $record->userAgent = $attributes['userAgent'] ?? substr((string) $request->getUserAgent(), 0, 500);
        } else {
            $record->ipAddress = $attributes['ipAddress'] ?? null;
            $record->userAgent = $attributes['userAgent'] ?? 'console';
        }
    }
}
