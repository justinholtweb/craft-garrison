<?php

namespace justinholtweb\garrison\widgets;

use Craft;
use craft\base\Widget;

class RecentThreatsWidget extends Widget
{
    public static function displayName(): string
    {
        return Craft::t('garrison', 'Recent Threats');
    }

    public static function icon(): ?string
    {
        return 'alert';
    }

    public function getBodyHtml(): ?string
    {
        // Implemented in Phase 5 with blocked request data
        return '<p style="text-align:center;color:var(--medium-text-color);padding:16px 0">Threat monitoring available in Phase 3.</p>';
    }
}
