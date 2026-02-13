<?php

namespace justinholtweb\garrison\scanners;

use Craft;
use justinholtweb\garrison\enums\CheckCategory;
use justinholtweb\garrison\enums\Severity;
use justinholtweb\garrison\models\ScanResult;

class HttpsCheck extends BaseScannerCheck
{
    public function getHandle(): string
    {
        return 'https';
    }

    public function getName(): string
    {
        return 'HTTPS';
    }

    public function getCategory(): CheckCategory
    {
        return CheckCategory::Encryption;
    }

    public function getDefaultSeverity(): Severity
    {
        return Severity::Critical;
    }

    protected function performCheck(?int $siteId = null): ScanResult
    {
        $site = $siteId
            ? Craft::$app->getSites()->getSiteById($siteId)
            : Craft::$app->getSites()->getPrimarySite();

        if (!$site) {
            return $this->skip('Could not determine site URL.');
        }

        $siteUrl = $site->getBaseUrl();

        if (str_starts_with($siteUrl, 'https://')) {
            return $this->pass('Site is served over HTTPS.', ['url' => $siteUrl]);
        }

        // Check if the current request is HTTPS (may differ from config)
        $request = Craft::$app->getRequest();
        if (!$request->getIsConsoleRequest() && $request->getIsSecureConnection()) {
            return $this->warning(
                'Current connection is HTTPS but site URL is configured as HTTP.',
                Severity::Medium,
                'Update your site URL in Settings > Sites to use https://.',
                ['url' => $siteUrl],
            );
        }

        return $this->fail(
            'Site is not served over HTTPS.',
            Severity::Critical,
            'Configure your web server to serve all traffic over HTTPS and update your site URL.',
            ['url' => $siteUrl],
        );
    }
}
