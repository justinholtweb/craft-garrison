<?php

namespace justinholtweb\garrison\scanners;

use Craft;
use justinholtweb\garrison\enums\CheckCategory;
use justinholtweb\garrison\enums\Severity;
use justinholtweb\garrison\models\ScanResult;

class CsrfCheck extends BaseScannerCheck
{
    public function getHandle(): string
    {
        return 'csrf';
    }

    public function getName(): string
    {
        return 'CSRF Protection';
    }

    public function getCategory(): CheckCategory
    {
        return CheckCategory::Config;
    }

    public function getDefaultSeverity(): Severity
    {
        return Severity::Critical;
    }

    protected function performCheck(?int $siteId = null): ScanResult
    {
        $config = Craft::$app->getConfig()->getGeneral();

        if ($config->enableCsrfProtection) {
            return $this->pass('CSRF protection is enabled.');
        }

        return $this->fail(
            'CSRF protection is disabled.',
            Severity::Critical,
            'Enable CSRF protection in your general.php config: \'enableCsrfProtection\' => true.',
        );
    }
}
