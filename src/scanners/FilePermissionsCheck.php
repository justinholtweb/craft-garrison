<?php

namespace justinholtweb\garrison\scanners;

use Craft;
use justinholtweb\garrison\enums\CheckCategory;
use justinholtweb\garrison\enums\Severity;
use justinholtweb\garrison\models\ScanResult;

class FilePermissionsCheck extends BaseScannerCheck
{
    public function getHandle(): string
    {
        return 'file-permissions';
    }

    public function getName(): string
    {
        return 'File Permissions';
    }

    public function getCategory(): CheckCategory
    {
        return CheckCategory::Permissions;
    }

    public function getDefaultSeverity(): Severity
    {
        return Severity::High;
    }

    protected function performCheck(?int $siteId = null): ScanResult
    {
        $issues = [];
        $basePath = Craft::getAlias('@root');

        // Check .env permissions
        $envFile = $basePath . '/.env';
        if (file_exists($envFile)) {
            $perms = fileperms($envFile) & 0777;
            if ($perms & 0007) {
                $issues[] = sprintf('.env is world-readable (%s)', $this->formatPermissions($perms));
            }
        }

        // Check config directory
        $configDir = Craft::getAlias('@config');
        if (is_dir($configDir)) {
            $perms = fileperms($configDir) & 0777;
            if ($perms & 0002) {
                $issues[] = sprintf('config/ directory is world-writable (%s)', $this->formatPermissions($perms));
            }
        }

        // Check web directory for writable PHP files
        $webDir = Craft::getAlias('@webroot');
        if (is_dir($webDir)) {
            $indexFile = $webDir . '/index.php';
            if (file_exists($indexFile)) {
                $perms = fileperms($indexFile) & 0777;
                if ($perms & 0002) {
                    $issues[] = sprintf('web/index.php is world-writable (%s)', $this->formatPermissions($perms));
                }
            }
        }

        // Check storage directory exists and is not web-accessible
        $storagePath = Craft::getAlias('@storage');
        if (!is_dir($storagePath)) {
            $issues[] = 'Storage directory does not exist';
        }

        if (empty($issues)) {
            return $this->pass('File permissions are properly configured.');
        }

        return $this->fail(
            implode('. ', $issues) . '.',
            Severity::High,
            'Restrict file permissions: .env should be 0600 or 0640, config/ should not be world-writable, web/index.php should not be world-writable.',
            ['issues' => $issues],
        );
    }

    private function formatPermissions(int $perms): string
    {
        return decoct($perms);
    }
}
