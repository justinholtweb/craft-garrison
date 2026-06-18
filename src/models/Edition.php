<?php

namespace justinholtweb\garrison\models;

use justinholtweb\garrison\Plugin;

class Edition
{
    public const LITE = 'lite';
    public const PRO = 'pro';

    public static function is(string $edition): bool
    {
        return Plugin::getInstance()->edition === $edition;
    }

    public static function isAtLeast(string $edition): bool
    {
        $order = [self::LITE => 0, self::PRO => 1];
        $current = $order[Plugin::getInstance()->edition] ?? 0;
        $required = $order[$edition] ?? 0;

        return $current >= $required;
    }

    public static function isLite(): bool
    {
        return true; // All editions include Free features
    }

    public static function isPro(): bool
    {
        return self::isAtLeast(self::PRO);
    }

    public static function requiresPro(string $feature = ''): void
    {
        if (!self::isPro()) {
            throw new \yii\base\InvalidConfigException(
                $feature ? "$feature requires Garrison Pro." : 'This feature requires Garrison Pro.'
            );
        }
    }

    /**
     * Friendly display name for the active edition.
     */
    public static function name(): string
    {
        return self::isPro() ? 'Pro' : 'Free';
    }

    public static function maxScanHistory(): int
    {
        return self::isPro() ? PHP_INT_MAX : 10;
    }

    public static function auditLogRetentionDays(): int
    {
        return self::isPro() ? 365 : 30;
    }
}
