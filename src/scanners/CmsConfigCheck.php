<?php

namespace justinholtweb\garrison\scanners;

use Craft;
use justinholtweb\garrison\enums\CheckCategory;
use justinholtweb\garrison\enums\Severity;
use justinholtweb\garrison\models\ScanResult;

class CmsConfigCheck extends BaseScannerCheck
{
    public function getHandle(): string
    {
        return 'cms-config';
    }

    public function getName(): string
    {
        return 'CMS Configuration';
    }

    public function getCategory(): CheckCategory
    {
        return CheckCategory::Config;
    }

    public function getDefaultSeverity(): Severity
    {
        return Severity::High;
    }

    protected function performCheck(?int $siteId = null): ScanResult
    {
        $issues = [];
        $config = Craft::$app->getConfig()->getGeneral();

        // Dev mode should be off in production
        if ($config->devMode) {
            $issues[] = 'Dev mode is enabled';
        }

        // Allow admin changes should be off in production
        if ($config->allowAdminChanges) {
            $issues[] = 'Admin changes are allowed (should be disabled in production)';
        }

        // Test email should not be set in production
        if (!empty($config->testToEmailAddress)) {
            $issues[] = 'Test email address is configured';
        }

        // Elevated session duration should be reasonable
        if ($config->elevatedSessionDuration > 3600) {
            $issues[] = 'Elevated session duration is longer than 1 hour';
        }

        // Check for permissive CORS
        if (!empty($config->allowedGraphqlOrigins) && $config->allowedGraphqlOrigins === '*') {
            $issues[] = 'GraphQL origins allow all domains';
        }

        if (empty($issues)) {
            return $this->pass('CMS configuration follows security best practices.');
        }

        $severity = in_array('Dev mode is enabled', $issues) ? Severity::Critical : Severity::High;

        return $this->fail(
            implode('. ', $issues) . '.',
            $severity,
            'Review your general.php config and disable dev mode, admin changes, and test email in production.',
            ['issues' => $issues],
        );
    }
}
