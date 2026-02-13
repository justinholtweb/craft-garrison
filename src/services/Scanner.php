<?php

namespace justinholtweb\garrison\services;

use Craft;
use craft\base\Component;
use justinholtweb\garrison\enums\ScanStatus;
use justinholtweb\garrison\models\Edition;
use justinholtweb\garrison\models\ScanReport;
use justinholtweb\garrison\models\ScanResult;
use justinholtweb\garrison\Plugin;
use justinholtweb\garrison\records\ScanRecord;
use justinholtweb\garrison\records\ScanResultRecord;
use justinholtweb\garrison\scanners\BaseScannerCheck;
use justinholtweb\garrison\scanners\CmsConfigCheck;
use justinholtweb\garrison\scanners\CsrfCheck;
use justinholtweb\garrison\scanners\FilePermissionsCheck;
use justinholtweb\garrison\scanners\HttpsCheck;
use justinholtweb\garrison\scanners\PhpVersionCheck;

class Scanner extends Component
{
    /** @var BaseScannerCheck[] */
    private array $checks = [];

    public function init(): void
    {
        parent::init();
        $this->registerDefaultChecks();
    }

    public function getChecks(): array
    {
        return $this->checks;
    }

    public function registerCheck(BaseScannerCheck $check): void
    {
        $this->checks[$check->getHandle()] = $check;
    }

    public function runScan(?int $siteId = null, string $triggeredBy = 'manual', ?int $userId = null): ScanReport
    {
        $startTime = microtime(true);

        if ($siteId === null) {
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        }

        $settings = Plugin::getInstance()->getSettings();
        $enabledChecks = $settings->enabledChecks;

        $report = new ScanReport();
        $report->siteId = $siteId;
        $report->triggeredBy = $triggeredBy;
        $report->userId = $userId;

        foreach ($this->checks as $handle => $check) {
            // Skip disabled checks
            if ($enabledChecks !== null && !in_array($handle, $enabledChecks)) {
                continue;
            }

            $result = $check->run($siteId);
            $report->addResult($result);
        }

        $report->calculateStatus();
        $report->calculateRiskScore();
        $report->duration = round(microtime(true) - $startTime, 3);

        // Persist
        $this->saveScanReport($report);

        // Enforce scan history limit
        $this->pruneOldScans($siteId);

        return $report;
    }

    public function getLastScan(?int $siteId = null): ?ScanReport
    {
        if ($siteId === null) {
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        }

        $record = ScanRecord::find()
            ->where(['siteId' => $siteId])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->one();

        if (!$record) {
            return null;
        }

        return $this->buildReportFromRecord($record);
    }

    public function getScanById(int $id): ?ScanReport
    {
        $record = ScanRecord::findOne($id);

        if (!$record) {
            return null;
        }

        return $this->buildReportFromRecord($record);
    }

    public function getScanHistory(?int $siteId = null, int $limit = 50, int $offset = 0): array
    {
        if ($siteId === null) {
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        }

        $records = ScanRecord::find()
            ->where(['siteId' => $siteId])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit($limit)
            ->offset($offset)
            ->all();

        return array_map(fn($record) => $this->buildReportFromRecord($record, false), $records);
    }

    private function registerDefaultChecks(): void
    {
        $this->registerCheck(new CmsConfigCheck());
        $this->registerCheck(new HttpsCheck());
        $this->registerCheck(new CsrfCheck());
        $this->registerCheck(new FilePermissionsCheck());
        $this->registerCheck(new PhpVersionCheck());
    }

    private function saveScanReport(ScanReport $report): void
    {
        $scanRecord = new ScanRecord();
        $scanRecord->siteId = $report->siteId;
        $scanRecord->status = $report->status->value;
        $scanRecord->riskScore = $report->riskScore;
        $scanRecord->totalChecks = $report->totalChecks;
        $scanRecord->passedChecks = $report->passedChecks;
        $scanRecord->warningChecks = $report->warningChecks;
        $scanRecord->failedChecks = $report->failedChecks;
        $scanRecord->criticalChecks = $report->criticalChecks;
        $scanRecord->duration = $report->duration;
        $scanRecord->triggeredBy = $report->triggeredBy;
        $scanRecord->userId = $report->userId;
        $scanRecord->save(false);

        $report->id = $scanRecord->id;

        foreach ($report->results as $result) {
            $resultRecord = new ScanResultRecord();
            $resultRecord->scanId = $scanRecord->id;
            $resultRecord->checkHandle = $result->checkHandle;
            $resultRecord->checkName = $result->checkName;
            $resultRecord->category = $result->category->value;
            $resultRecord->status = $result->status->value;
            $resultRecord->severity = $result->severity->value;
            $resultRecord->message = $result->message;
            $resultRecord->details = $result->details;
            $resultRecord->remediation = $result->remediation;
            $resultRecord->save(false);
        }
    }

    private function buildReportFromRecord(ScanRecord $record, bool $withResults = true): ScanReport
    {
        $report = new ScanReport();
        $report->id = $record->id;
        $report->siteId = $record->siteId;
        $report->status = ScanStatus::from($record->status);
        $report->riskScore = $record->riskScore;
        $report->totalChecks = $record->totalChecks;
        $report->passedChecks = $record->passedChecks;
        $report->warningChecks = $record->warningChecks;
        $report->failedChecks = $record->failedChecks;
        $report->criticalChecks = $record->criticalChecks;
        $report->duration = $record->duration;
        $report->triggeredBy = $record->triggeredBy;
        $report->userId = $record->userId;
        $report->dateCreated = $record->dateCreated ? new \DateTime($record->dateCreated) : null;

        if ($withResults) {
            $resultRecords = ScanResultRecord::find()
                ->where(['scanId' => $record->id])
                ->all();

            foreach ($resultRecords as $resultRecord) {
                $result = new ScanResult();
                $result->checkHandle = $resultRecord->checkHandle;
                $result->checkName = $resultRecord->checkName;
                $result->category = \justinholtweb\garrison\enums\CheckCategory::from($resultRecord->category);
                $result->status = ScanStatus::from($resultRecord->status);
                $result->severity = \justinholtweb\garrison\enums\Severity::from($resultRecord->severity);
                $result->message = $resultRecord->message;
                $result->details = $resultRecord->details;
                $result->remediation = $resultRecord->remediation;
                $report->results[] = $result;
            }
        }

        return $report;
    }

    private function pruneOldScans(int $siteId): void
    {
        $maxHistory = Edition::maxScanHistory();
        if ($maxHistory >= PHP_INT_MAX) {
            return;
        }

        $oldScans = ScanRecord::find()
            ->where(['siteId' => $siteId])
            ->orderBy(['dateCreated' => SORT_DESC])
            ->offset($maxHistory)
            ->all();

        foreach ($oldScans as $scan) {
            $scan->delete();
        }
    }
}
