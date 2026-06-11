<?php

namespace justinholtweb\garrison\scanners;

use Craft;
use justinholtweb\garrison\enums\CheckCategory;
use justinholtweb\garrison\enums\Severity;
use justinholtweb\garrison\models\ScanResult;

/**
 * Verifies a strong application security key is configured. The key signs
 * cookies and encrypts sensitive data, so a missing or weak key undermines
 * every other protection.
 */
class SecurityKeyCheck extends BaseScannerCheck
{
    private const MIN_LENGTH = 32;

    public function getHandle(): string
    {
        return 'security-key';
    }

    public function getName(): string
    {
        return 'Application Security Key';
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
        $key = Craft::$app->getConfig()->getGeneral()->securityKey;

        if ($key === '') {
            return $this->fail(
                'No application security key is set.',
                Severity::Critical,
                'Set a CRAFT_SECURITY_KEY environment variable. Generate one with: php craft setup/security-key.',
            );
        }

        if (strlen($key) < self::MIN_LENGTH) {
            return $this->fail(
                sprintf('The security key is short (%d characters).', strlen($key)),
                Severity::High,
                'Use a long, random security key of at least 32 characters. Regenerate with: php craft setup/security-key.',
            );
        }

        return $this->pass('A strong application security key is configured.');
    }
}
