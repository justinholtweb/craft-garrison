<?php

namespace justinholtweb\garrison\models;

use craft\base\Model;
use justinholtweb\garrison\enums\CheckCategory;
use justinholtweb\garrison\enums\ScanStatus;
use justinholtweb\garrison\enums\Severity;

class ScanResult extends Model
{
    public ?string $checkHandle = null;
    public ?string $checkName = null;
    public ?CheckCategory $category = null;
    public ScanStatus $status = ScanStatus::Skipped;
    public Severity $severity = Severity::Info;
    public ?string $message = null;
    public ?array $details = null;
    public ?string $remediation = null;

    public function isPassed(): bool
    {
        return $this->status === ScanStatus::Passed;
    }

    public function isFailed(): bool
    {
        return in_array($this->status, [ScanStatus::Failed, ScanStatus::Critical]);
    }

    public function isWarning(): bool
    {
        return $this->status === ScanStatus::Warning;
    }
}
