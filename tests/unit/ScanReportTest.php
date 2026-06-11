<?php

namespace justinholtweb\garrison\tests\unit;

use Codeception\Test\Unit;
use justinholtweb\garrison\enums\ScanStatus;
use justinholtweb\garrison\enums\Severity;
use justinholtweb\garrison\models\ScanReport;
use justinholtweb\garrison\models\ScanResult;

/**
 * Covers the aggregation logic in ScanReport: status precedence and capped
 * risk-score accumulation.
 */
class ScanReportTest extends Unit
{
    private function makeResult(ScanStatus $status, Severity $severity): ScanResult
    {
        $result = new ScanResult();
        $result->status = $status;
        $result->severity = $severity;

        return $result;
    }

    public function testEmptyReportPasses(): void
    {
        $report = new ScanReport();
        $report->calculateStatus();
        $this->assertSame(ScanStatus::Passed, $report->status);
        $this->assertSame(0, $report->riskScore);
    }

    public function testCriticalTakesPrecedence(): void
    {
        $report = new ScanReport();
        $report->addResult($this->makeResult(ScanStatus::Passed, Severity::Info));
        $report->addResult($this->makeResult(ScanStatus::Warning, Severity::Medium));
        $report->addResult($this->makeResult(ScanStatus::Critical, Severity::Critical));
        $report->calculateStatus();

        $this->assertSame(ScanStatus::Critical, $report->status);
        $this->assertSame(1, $report->passedChecks);
        $this->assertSame(1, $report->warningChecks);
        $this->assertSame(1, $report->criticalChecks);
    }

    public function testFailedBeatsWarning(): void
    {
        $report = new ScanReport();
        $report->addResult($this->makeResult(ScanStatus::Warning, Severity::Low));
        $report->addResult($this->makeResult(ScanStatus::Failed, Severity::High));
        $report->calculateStatus();

        $this->assertSame(ScanStatus::Failed, $report->status);
    }

    public function testRiskScoreAccumulatesBySeverity(): void
    {
        $report = new ScanReport();
        $report->addResult($this->makeResult(ScanStatus::Failed, Severity::High));   // 15
        $report->addResult($this->makeResult(ScanStatus::Warning, Severity::Medium)); // 8
        $report->calculateRiskScore();

        $this->assertSame(23, $report->riskScore);
    }

    public function testRiskScoreIsCappedAt100(): void
    {
        $report = new ScanReport();
        for ($i = 0; $i < 10; $i++) {
            $report->addResult($this->makeResult(ScanStatus::Critical, Severity::Critical)); // 25 each
        }
        $report->calculateRiskScore();

        $this->assertSame(100, $report->riskScore);
    }

    public function testPassingResultsAddNoRisk(): void
    {
        $report = new ScanReport();
        $report->addResult($this->makeResult(ScanStatus::Passed, Severity::Info));
        $report->calculateRiskScore();

        $this->assertSame(0, $report->riskScore);
    }
}
