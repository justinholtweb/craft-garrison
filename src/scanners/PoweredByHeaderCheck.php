<?php

namespace justinholtweb\garrison\scanners;

use Craft;
use justinholtweb\garrison\enums\CheckCategory;
use justinholtweb\garrison\enums\Severity;
use justinholtweb\garrison\models\ScanResult;

/**
 * Flags the X-Powered-By header, which advertises that the site runs Craft and
 * narrows an attacker's search for version-specific exploits.
 */
class PoweredByHeaderCheck extends BaseScannerCheck
{
    public function getHandle(): string
    {
        return 'powered-by-header';
    }

    public function getName(): string
    {
        return 'X-Powered-By Header';
    }

    public function getCategory(): CheckCategory
    {
        return CheckCategory::Headers;
    }

    public function getDefaultSeverity(): Severity
    {
        return Severity::Low;
    }

    protected function performCheck(?int $siteId = null): ScanResult
    {
        $config = Craft::$app->getConfig()->getGeneral();

        if ($config->sendPoweredByHeader) {
            return $this->warning(
                'The X-Powered-By header is enabled, disclosing that the site runs Craft CMS.',
                Severity::Low,
                "Set 'sendPoweredByHeader' => false in config/general.php.",
            );
        }

        return $this->pass('The X-Powered-By header is disabled.');
    }
}
