<?php

namespace justinholtweb\garrison\models;

use craft\base\Model;

class Settings extends Model
{
    // Scanner
    public ?array $enabledChecks = null;
    public ?string $scanSchedule = 'daily';
    public int $scanScheduleHour = 3;

    // Shield — Login Protection
    public int $maxLoginAttempts = 5;
    public int $lockoutDuration = 300;
    public int $loginAttemptWindow = 600;

    // Shield — Rate Limiting
    public bool $enableRateLimiting = false;
    public int $rateLimit = 60;
    public int $rateLimitWindow = 60;

    // Shield — IP Management
    public bool $enableIpRestriction = false;

    // Shield — WAF
    public bool $enableWaf = false;
    public array $wafRules = ['sql-injection', 'xss', 'path-traversal', 'user-agent'];

    // Shield — Geo-blocking
    public bool $enableGeoBlocking = false;
    public array $blockedCountries = [];
    public string $geoBlockMode = 'block';

    // Sentinel — Audit Logging
    public bool $enableAuditLog = true;
    public ?int $auditLogRetentionDays = null;

    // Sentinel — File Integrity
    public bool $enableFileIntegrity = false;
    public array $monitoredPaths = [
        'vendor/craftcms/cms/src/',
        'config/',
        '.env',
        'web/index.php',
    ];

    // Beacon — Notifications
    public bool $enableNotifications = false;
    public bool $notifyOnScanFailure = true;
    public bool $notifyOnThreatDetected = true;
    public bool $notifyOnLoginLockout = false;
    public array $emailRecipients = [];
    public string $slackWebhookUrl = '';
    public string $discordWebhookUrl = '';
    public string $webhookUrl = '';

    // Data Pruning
    public int $pruneBlockedRequestsDays = 90;
    public int $pruneLoginAttemptsDays = 30;

    public function defineRules(): array
    {
        return [
            [['scanSchedule'], 'in', 'range' => ['hourly', 'daily', 'weekly', 'monthly', null]],
            [['scanScheduleHour'], 'integer', 'min' => 0, 'max' => 23],
            [['maxLoginAttempts'], 'integer', 'min' => 1, 'max' => 100],
            [['lockoutDuration'], 'integer', 'min' => 60],
            [['loginAttemptWindow'], 'integer', 'min' => 60],
            [['rateLimit'], 'integer', 'min' => 1],
            [['rateLimitWindow'], 'integer', 'min' => 1],
            [['enableRateLimiting', 'enableIpRestriction', 'enableWaf', 'enableGeoBlocking', 'enableAuditLog', 'enableFileIntegrity', 'enableNotifications', 'notifyOnScanFailure', 'notifyOnThreatDetected', 'notifyOnLoginLockout'], 'boolean'],
            [['geoBlockMode'], 'in', 'range' => ['block', 'allow']],
            [['auditLogRetentionDays'], 'integer', 'min' => 1, 'skipOnEmpty' => true],
            [['slackWebhookUrl', 'discordWebhookUrl', 'webhookUrl'], 'url', 'skipOnEmpty' => true],
            [['pruneBlockedRequestsDays', 'pruneLoginAttemptsDays'], 'integer', 'min' => 1],
        ];
    }
}
