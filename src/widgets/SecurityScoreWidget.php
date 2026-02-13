<?php

namespace justinholtweb\garrison\widgets;

use Craft;
use craft\base\Widget;
use justinholtweb\garrison\Plugin;

class SecurityScoreWidget extends Widget
{
    public static function displayName(): string
    {
        return Craft::t('garrison', 'Security Score');
    }

    public static function icon(): ?string
    {
        return 'shield';
    }

    public function getBodyHtml(): ?string
    {
        $lastScan = Plugin::getInstance()->scanner->getLastScan();

        if (!$lastScan) {
            return '<p style="text-align:center;color:var(--medium-text-color)">No scans yet. <a href="' . \craft\helpers\UrlHelper::cpUrl('garrison/scanner') . '">Run a scan</a>.</p>';
        }

        $color = $lastScan->riskColor();

        return <<<HTML
<div style="text-align:center;padding:16px 0">
    <div style="font-size:42px;font-weight:700;color:var(--{$color}-color, {$color})">{$lastScan->riskScore}</div>
    <div style="font-size:13px;font-weight:600;text-transform:uppercase;color:var(--{$color}-color, {$color})">{$lastScan->riskLabel()}</div>
    <div style="margin-top:8px;font-size:12px;color:var(--light-text-color)">{$lastScan->passedChecks}/{$lastScan->totalChecks} checks passed</div>
</div>
HTML;
    }
}
