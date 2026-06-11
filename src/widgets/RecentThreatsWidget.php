<?php

namespace justinholtweb\garrison\widgets;

use Craft;
use craft\base\Widget;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use justinholtweb\garrison\enums\BlockReason;
use justinholtweb\garrison\Plugin;

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
        $blocked = Plugin::getInstance()->shield->getBlockedRequests(5);

        if (empty($blocked)) {
            return '<p style="text-align:center;color:var(--medium-text-color);padding:16px 0">'
                . Craft::t('garrison', 'No threats detected. All clear.')
                . '</p>';
        }

        $rows = '';
        foreach ($blocked as $request) {
            $reason = BlockReason::tryFrom($request->reason);
            $when = DateTimeHelper::toDateTime($request->dateCreated);
            $rows .= '<li style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--hairline-color)">'
                . '<span><strong>' . Html::encode($reason?->label() ?? $request->reason) . '</strong> '
                . '<span style="color:var(--light-text-color)">' . Html::encode($request->ipAddress) . '</span></span>'
                . '<span style="color:var(--light-text-color)">' . ($when ? $when->format('M j, H:i') : '') . '</span>'
                . '</li>';
        }

        return '<ul style="margin:0;padding:0;list-style:none">' . $rows . '</ul>'
            . '<p style="margin-top:10px"><a href="' . UrlHelper::cpUrl('garrison/shield/login-protection') . '">'
            . Craft::t('garrison', 'View all') . '</a></p>';
    }
}
