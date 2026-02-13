<?php

namespace justinholtweb\garrison\console\controllers;

use Craft;
use craft\console\Controller;
use justinholtweb\garrison\Plugin;
use yii\console\ExitCode;

/**
 * Security scanning commands.
 */
class ScanController extends Controller
{
    /**
     * @var int|null Site ID to scan. Defaults to primary site.
     */
    public ?int $siteId = null;

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'run') {
            $options[] = 'siteId';
        }

        return $options;
    }

    /**
     * Run a security scan.
     */
    public function actionRun(): int
    {
        $this->stdout("Running security scan...\n");

        $report = Plugin::getInstance()->scanner->runScan(
            $this->siteId,
            'console',
        );

        $this->stdout("\n");
        $this->stdout("Scan complete in {$report->duration}s\n");
        $this->stdout("Status: {$report->status->label()}\n");
        $this->stdout("Risk Score: {$report->riskScore}/100 ({$report->riskLabel()})\n");
        $this->stdout("\n");
        $this->stdout("Results: {$report->passedChecks} passed, {$report->warningChecks} warnings, {$report->failedChecks} failed, {$report->criticalChecks} critical\n");
        $this->stdout("\n");

        foreach ($report->results as $result) {
            $icon = match (true) {
                $result->isPassed() => "\033[32m✓\033[0m",
                $result->isWarning() => "\033[33m⚠\033[0m",
                $result->isFailed() => "\033[31m✗\033[0m",
                default => "\033[90m-\033[0m",
            };

            $this->stdout("  {$icon} [{$result->severity->label()}] {$result->checkName}: {$result->message}\n");

            if ($result->remediation) {
                $this->stdout("    → {$result->remediation}\n");
            }
        }

        $this->stdout("\n");

        return $report->criticalChecks > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Show the last scan status.
     */
    public function actionStatus(): int
    {
        $report = Plugin::getInstance()->scanner->getLastScan($this->siteId);

        if (!$report) {
            $this->stdout("No scans found. Run 'garrison/scan/run' to perform a scan.\n");
            return ExitCode::OK;
        }

        $this->stdout("Last Scan\n");
        $this->stdout("=========\n");
        $this->stdout("Date: {$report->dateCreated?->format('Y-m-d H:i:s')}\n");
        $this->stdout("Status: {$report->status->label()}\n");
        $this->stdout("Risk Score: {$report->riskScore}/100 ({$report->riskLabel()})\n");
        $this->stdout("Triggered by: {$report->triggeredBy}\n");
        $this->stdout("Duration: {$report->duration}s\n");
        $this->stdout("Checks: {$report->totalChecks} total, {$report->passedChecks} passed, {$report->warningChecks} warnings, {$report->failedChecks} failed, {$report->criticalChecks} critical\n");

        return ExitCode::OK;
    }
}
