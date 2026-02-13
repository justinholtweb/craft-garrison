<?php

/**
 * Garrison default config
 *
 * Copy this file to config/garrison.php and adjust as needed.
 * Multi-environment config is supported — use '*', 'dev', 'staging', 'production' keys.
 */

return [
    // Scanner
    'enabledChecks' => null, // null = all checks enabled; array of handles to enable specific checks
    'scanSchedule' => 'daily', // 'hourly', 'daily', 'weekly', 'monthly', or null to disable
    'scanScheduleHour' => 3, // Hour of day (0-23) for daily/weekly/monthly scans

    // Shield — Login Protection (Lite+)
    'maxLoginAttempts' => 5,
    'lockoutDuration' => 300, // seconds
    'loginAttemptWindow' => 600, // seconds

    // Shield — Rate Limiting (Plus+)
    'enableRateLimiting' => false,
    'rateLimit' => 60, // requests per window
    'rateLimitWindow' => 60, // seconds

    // Shield — IP Management (Plus+)
    'enableIpRestriction' => false,

    // Shield — WAF (Pro)
    'enableWaf' => false,
    'wafRules' => ['sql-injection', 'xss', 'path-traversal', 'user-agent'],

    // Shield — Geo-blocking (Pro)
    'enableGeoBlocking' => false,
    'blockedCountries' => [],
    'geoBlockMode' => 'block', // 'block' or 'allow' (allowlist mode)

    // Sentinel — Audit Logging (Lite+)
    'enableAuditLog' => true,
    'auditLogRetentionDays' => null, // null = use edition default (30/90/365)

    // Sentinel — File Integrity (Plus+)
    'enableFileIntegrity' => false,
    'monitoredPaths' => [
        'vendor/craftcms/cms/src/',
        'config/',
        '.env',
        'web/index.php',
    ],

    // Beacon — Notifications (Plus+)
    'enableNotifications' => false,
    'notifyOnScanFailure' => true,
    'notifyOnThreatDetected' => true,
    'notifyOnLoginLockout' => false,
    'emailRecipients' => [], // array of email addresses
    'slackWebhookUrl' => '',
    'discordWebhookUrl' => '',
    'webhookUrl' => '',

    // Data Pruning
    'pruneBlockedRequestsDays' => 90,
    'pruneLoginAttemptsDays' => 30,
];
