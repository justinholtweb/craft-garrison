<?php

namespace justinholtweb\garrison\models;

use craft\base\Model;
use justinholtweb\garrison\enums\ScanStatus;

class ScanReport extends Model
{
    public ?int $id = null;
    public ?int $siteId = null;
    public ScanStatus $status = ScanStatus::Passed;
    public int $riskScore = 0;
    public int $totalChecks = 0;
    public int $passedChecks = 0;
    public int $warningChecks = 0;
    public int $failedChecks = 0;
    public int $criticalChecks = 0;
    public float $duration = 0.0;
    public string $triggeredBy = 'manual';
    public ?int $userId = null;
    public ?\DateTime $dateCreated = null;

    /** @var ScanResult[] */
    public array $results = [];

    public function addResult(ScanResult $result): void
    {
        $this->results[] = $result;
        $this->totalChecks++;

        match ($result->status) {
            ScanStatus::Passed => $this->passedChecks++,
            ScanStatus::Warning => $this->warningChecks++,
            ScanStatus::Failed => $this->failedChecks++,
            ScanStatus::Critical => $this->criticalChecks++,
            default => null,
        };
    }

    public function calculateStatus(): void
    {
        if ($this->criticalChecks > 0) {
            $this->status = ScanStatus::Critical;
        } elseif ($this->failedChecks > 0) {
            $this->status = ScanStatus::Failed;
        } elseif ($this->warningChecks > 0) {
            $this->status = ScanStatus::Warning;
        } else {
            $this->status = ScanStatus::Passed;
        }
    }

    public function calculateRiskScore(): void
    {
        $score = 0;

        foreach ($this->results as $result) {
            if ($result->isFailed() || $result->isWarning()) {
                $score += $result->severity->weight();
            }
        }

        $this->riskScore = min(100, $score);
    }

    public function riskLabel(): string
    {
        return match (true) {
            $this->riskScore <= 10 => 'Excellent',
            $this->riskScore <= 30 => 'Good',
            $this->riskScore <= 50 => 'Fair',
            $this->riskScore <= 70 => 'Poor',
            default => 'Critical',
        };
    }

    public function riskColor(): string
    {
        return match (true) {
            $this->riskScore <= 10 => 'green',
            $this->riskScore <= 30 => 'blue',
            $this->riskScore <= 50 => 'orange',
            $this->riskScore <= 70 => 'red',
            default => 'red',
        };
    }
}
