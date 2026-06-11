<?php

namespace justinholtweb\garrison\scanners;

use Craft;
use justinholtweb\garrison\enums\CheckCategory;
use justinholtweb\garrison\enums\Severity;
use justinholtweb\garrison\models\ScanResult;

/**
 * Checks cookie hardening: secure flag and SameSite policy. Session and CSRF
 * cookies sent without the Secure flag can leak over plaintext connections.
 */
class SecureCookiesCheck extends BaseScannerCheck
{
    public function getHandle(): string
    {
        return 'secure-cookies';
    }

    public function getName(): string
    {
        return 'Cookie Security';
    }

    public function getCategory(): CheckCategory
    {
        return CheckCategory::Encryption;
    }

    public function getDefaultSeverity(): Severity
    {
        return Severity::High;
    }

    protected function performCheck(?int $siteId = null): ScanResult
    {
        $config = Craft::$app->getConfig()->getGeneral();
        $issues = [];

        // useSecureCookies may be true, false, or 'auto'. Explicit false is the risk.
        if ($config->useSecureCookies === false) {
            $issues[] = 'Secure cookies are disabled';
        }

        $sameSite = $config->sameSiteCookieValue;
        if ($sameSite === null || strtolower((string) $sameSite) === 'none') {
            $issues[] = 'SameSite cookie policy is not restricted';
        }

        if (empty($issues)) {
            return $this->pass('Cookies use the Secure flag and a restricted SameSite policy.');
        }

        return $this->warning(
            implode('. ', $issues) . '.',
            Severity::Medium,
            "Set 'useSecureCookies' => true and 'sameSiteCookieValue' => 'Lax' (or 'Strict') in config/general.php.",
            ['useSecureCookies' => $config->useSecureCookies, 'sameSiteCookieValue' => $sameSite],
        );
    }
}
