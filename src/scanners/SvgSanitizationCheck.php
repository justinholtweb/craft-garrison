<?php

namespace justinholtweb\garrison\scanners;

use Craft;
use justinholtweb\garrison\enums\CheckCategory;
use justinholtweb\garrison\enums\Severity;
use justinholtweb\garrison\models\ScanResult;

/**
 * Verifies uploaded SVGs and control-panel images are sanitized. Unsanitized
 * SVG uploads are a common stored-XSS vector.
 */
class SvgSanitizationCheck extends BaseScannerCheck
{
    public function getHandle(): string
    {
        return 'svg-sanitization';
    }

    public function getName(): string
    {
        return 'Upload Sanitization';
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
        $config = Craft::$app->getConfig()->getGeneral();
        $issues = [];

        if (!$config->sanitizeSvgUploads) {
            $issues[] = 'SVG upload sanitization is disabled';
        }

        if (!$config->sanitizeCpImageUploads) {
            $issues[] = 'Control panel image sanitization is disabled';
        }

        if (empty($issues)) {
            return $this->pass('Uploaded SVGs and control panel images are sanitized.');
        }

        return $this->fail(
            implode('. ', $issues) . '.',
            Severity::High,
            "Set 'sanitizeSvgUploads' => true and 'sanitizeCpImageUploads' => true in config/general.php.",
            ['issues' => $issues],
        );
    }
}
