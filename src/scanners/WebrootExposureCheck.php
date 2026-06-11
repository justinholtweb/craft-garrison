<?php

namespace justinholtweb\garrison\scanners;

use Craft;
use justinholtweb\garrison\enums\CheckCategory;
use justinholtweb\garrison\enums\Severity;
use justinholtweb\garrison\models\ScanResult;

/**
 * Detects sensitive files served from the public web root. A .env, composer
 * manifest, or .git directory under the document root can leak credentials and
 * source history to anyone who guesses the URL.
 */
class WebrootExposureCheck extends BaseScannerCheck
{
    private const SENSITIVE = ['.env', '.env.example', 'composer.json', 'composer.lock', '.git', 'craft'];

    public function getHandle(): string
    {
        return 'webroot-exposure';
    }

    public function getName(): string
    {
        return 'Web Root Exposure';
    }

    public function getCategory(): CheckCategory
    {
        return CheckCategory::Permissions;
    }

    public function getDefaultSeverity(): Severity
    {
        return Severity::Critical;
    }

    protected function performCheck(?int $siteId = null): ScanResult
    {
        $webroot = Craft::getAlias('@webroot', false);

        if (!is_string($webroot) || !is_dir($webroot)) {
            return $this->skip('Could not determine the web root path.');
        }

        $exposed = [];
        foreach (self::SENSITIVE as $name) {
            if (file_exists($webroot . DIRECTORY_SEPARATOR . $name)) {
                $exposed[] = $name;
            }
        }

        if (empty($exposed)) {
            return $this->pass('No sensitive files were found in the public web root.');
        }

        return $this->fail(
            sprintf('Sensitive file(s) exposed in the web root: %s.', implode(', ', $exposed)),
            Severity::Critical,
            'Move these files above the web root, or block access to them in your web server config.',
            ['exposed' => $exposed],
        );
    }
}
