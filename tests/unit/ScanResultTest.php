<?php

namespace justinholtweb\garrison\tests\unit;

use Codeception\Test\Unit;
use justinholtweb\garrison\enums\ScanStatus;
use justinholtweb\garrison\models\ScanResult;

/**
 * Covers the status predicates on ScanResult, which drive both report
 * aggregation and risk scoring (isFailed() spans Failed and Critical).
 */
class ScanResultTest extends Unit
{
    private function resultWith(ScanStatus $status): ScanResult
    {
        $result = new ScanResult();
        $result->status = $status;

        return $result;
    }

    public function testDefaultStatusIsSkipped(): void
    {
        $result = new ScanResult();
        $this->assertSame(ScanStatus::Skipped, $result->status);
        $this->assertFalse($result->isPassed());
        $this->assertFalse($result->isFailed());
        $this->assertFalse($result->isWarning());
    }

    public function testIsPassed(): void
    {
        $this->assertTrue($this->resultWith(ScanStatus::Passed)->isPassed());
        $this->assertFalse($this->resultWith(ScanStatus::Warning)->isPassed());
        $this->assertFalse($this->resultWith(ScanStatus::Failed)->isPassed());
    }

    public function testIsWarning(): void
    {
        $this->assertTrue($this->resultWith(ScanStatus::Warning)->isWarning());
        $this->assertFalse($this->resultWith(ScanStatus::Passed)->isWarning());
        $this->assertFalse($this->resultWith(ScanStatus::Failed)->isWarning());
    }

    public function testIsFailedSpansFailedAndCritical(): void
    {
        $this->assertTrue($this->resultWith(ScanStatus::Failed)->isFailed());
        $this->assertTrue($this->resultWith(ScanStatus::Critical)->isFailed());
    }

    public function testIsFailedExcludesOtherStatuses(): void
    {
        $this->assertFalse($this->resultWith(ScanStatus::Passed)->isFailed());
        $this->assertFalse($this->resultWith(ScanStatus::Warning)->isFailed());
        $this->assertFalse($this->resultWith(ScanStatus::Skipped)->isFailed());
    }

    public function testPredicatesAreMutuallyExclusive(): void
    {
        foreach (ScanStatus::cases() as $status) {
            $result = $this->resultWith($status);
            $trueCount = (int)$result->isPassed() + (int)$result->isFailed() + (int)$result->isWarning();
            // Skipped matches none; every other status matches exactly one predicate.
            $this->assertLessThanOrEqual(1, $trueCount, "{$status->name} matched more than one predicate");
        }
    }
}
