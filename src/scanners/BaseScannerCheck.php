<?php

namespace justinholtweb\garrison\scanners;

use justinholtweb\garrison\enums\CheckCategory;
use justinholtweb\garrison\enums\ScanStatus;
use justinholtweb\garrison\enums\Severity;
use justinholtweb\garrison\models\ScanResult;

abstract class BaseScannerCheck
{
    abstract public function getHandle(): string;

    abstract public function getName(): string;

    abstract public function getCategory(): CheckCategory;

    abstract public function getDefaultSeverity(): Severity;

    abstract protected function performCheck(?int $siteId = null): ScanResult;

    public function run(?int $siteId = null): ScanResult
    {
        $result = $this->performCheck($siteId);

        $result->checkHandle = $this->getHandle();
        $result->checkName = $this->getName();
        $result->category = $this->getCategory();

        if ($result->severity === Severity::Info && $result->isFailed()) {
            $result->severity = $this->getDefaultSeverity();
        }

        return $result;
    }

    protected function pass(string $message, ?array $details = null): ScanResult
    {
        $result = new ScanResult();
        $result->status = ScanStatus::Passed;
        $result->severity = Severity::Info;
        $result->message = $message;
        $result->details = $details;

        return $result;
    }

    protected function warning(string $message, Severity $severity, ?string $remediation = null, ?array $details = null): ScanResult
    {
        $result = new ScanResult();
        $result->status = ScanStatus::Warning;
        $result->severity = $severity;
        $result->message = $message;
        $result->remediation = $remediation;
        $result->details = $details;

        return $result;
    }

    protected function fail(string $message, Severity $severity, ?string $remediation = null, ?array $details = null): ScanResult
    {
        $result = new ScanResult();
        $result->status = $severity === Severity::Critical ? ScanStatus::Critical : ScanStatus::Failed;
        $result->severity = $severity;
        $result->message = $message;
        $result->remediation = $remediation;
        $result->details = $details;

        return $result;
    }

    protected function skip(string $message): ScanResult
    {
        $result = new ScanResult();
        $result->status = ScanStatus::Skipped;
        $result->severity = Severity::Info;
        $result->message = $message;

        return $result;
    }
}
