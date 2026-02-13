<?php

namespace justinholtweb\garrison\models;

use justinholtweb\garrison\Plugin;

class Edition
{
    public const LITE = 'lite';
    public const PLUS = 'plus';
    public const PRO = 'pro';

    public static function is(string $edition): bool
    {
        return Plugin::getInstance()->edition === $edition;
    }

    public static function isAtLeast(string $edition): bool
    {
        $order = [self::LITE => 0, self::PLUS => 1, self::PRO => 2];
        $current = $order[Plugin::getInstance()->edition] ?? 0;
        $required = $order[$edition] ?? 0;

        return $current >= $required;
    }

    public static function isLite(): bool
    {
        return true; // All editions include Lite features
    }

    public static function isPlus(): bool
    {
        return self::isAtLeast(self::PLUS);
    }

    public static function isPro(): bool
    {
        return self::isAtLeast(self::PRO);
    }

    public static function requiresPlus(string $feature = ''): void
    {
        if (!self::isPlus()) {
            throw new \yii\base\InvalidConfigException(
                $feature ? "$feature requires Garrison Plus or Pro." : 'This feature requires Garrison Plus or Pro.'
            );
        }
    }

    public static function requiresPro(string $feature = ''): void
    {
        if (!self::isPro()) {
            throw new \yii\base\InvalidConfigException(
                $feature ? "$feature requires Garrison Pro." : 'This feature requires Garrison Pro.'
            );
        }
    }

    public static function maxScanHistory(): int
    {
        return self::isPlus() ? PHP_INT_MAX : 10;
    }

    public static function auditLogRetentionDays(): int
    {
        if (self::isPro()) {
            return 365;
        }

        if (self::isPlus()) {
            return 90;
        }

        return 30;
    }
}
