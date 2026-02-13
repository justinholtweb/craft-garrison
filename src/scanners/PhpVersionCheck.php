<?php

namespace justinholtweb\garrison\scanners;

use justinholtweb\garrison\enums\CheckCategory;
use justinholtweb\garrison\enums\Severity;
use justinholtweb\garrison\models\ScanResult;

class PhpVersionCheck extends BaseScannerCheck
{
    /**
     * PHP versions and their EOL dates (active support end).
     * Updated periodically. See https://www.php.net/supported-versions.php
     */
    private const VERSION_EOL = [
        '8.0' => '2023-11-26',
        '8.1' => '2024-11-25',
        '8.2' => '2025-12-08',
        '8.3' => '2026-11-23',
        '8.4' => '2027-11-23',
    ];

    public function getHandle(): string
    {
        return 'php-version';
    }

    public function getName(): string
    {
        return 'PHP Version';
    }

    public function getCategory(): CheckCategory
    {
        return CheckCategory::Updates;
    }

    public function getDefaultSeverity(): Severity
    {
        return Severity::High;
    }

    protected function performCheck(?int $siteId = null): ScanResult
    {
        $version = PHP_VERSION;
        $majorMinor = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

        // Check if version is EOL
        $eolDate = self::VERSION_EOL[$majorMinor] ?? null;

        if ($eolDate && strtotime($eolDate) < time()) {
            return $this->fail(
                sprintf('PHP %s has reached end of life (EOL: %s).', $version, $eolDate),
                Severity::Critical,
                'Upgrade to a supported PHP version. See https://www.php.net/supported-versions.php.',
                ['version' => $version, 'eolDate' => $eolDate],
            );
        }

        // Check if version will be EOL within 6 months
        if ($eolDate && strtotime($eolDate) < strtotime('+6 months')) {
            return $this->warning(
                sprintf('PHP %s will reach end of life on %s.', $version, $eolDate),
                Severity::Medium,
                'Plan to upgrade to a newer PHP version before end of life.',
                ['version' => $version, 'eolDate' => $eolDate],
            );
        }

        return $this->pass(
            sprintf('PHP %s is a supported version.', $version),
            ['version' => $version],
        );
    }
}
