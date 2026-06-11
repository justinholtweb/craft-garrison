# Garrison — Security Suite for Craft CMS

Comprehensive security plugin for Craft CMS 5. Vulnerability scanning, active request protection, audit logging, file integrity monitoring, and multi-channel notifications in a single install.

## Editions

| Feature | Lite (Free) | Plus | Pro |
|---------|:-----------:|:----:|:---:|
| Security scanner (14 checks) | ✓ | ✓ | ✓ |
| Remediation guidance per check | ✓ | ✓ | ✓ |
| Login brute-force protection & lockout | ✓ | ✓ | ✓ |
| Audit logging | 30 days | 90 days | 365 days |
| Console commands | ✓ | ✓ | ✓ |
| Scan history | Last 10 | Unlimited | Unlimited |
| Multi-site scans | ✓ | ✓ | ✓ |
| Scheduled scans (queue-based) | — | ✓ | ✓ |
| Email / Slack / Discord / webhook alerts | — | ✓ | ✓ |
| IP allow/block rules (CP + frontend, CIDR) | — | ✓ | ✓ |
| Rate limiting | — | ✓ | ✓ |
| File integrity monitoring | — | ✓ | ✓ |
| REST API (authenticated) | — | ✓ | ✓ |
| WAF / request filtering | — | — | ✓ |
| Geo-blocking | — | — | ✓ |
| Dashboard threat analytics | — | — | ✓ |

## Requirements

- Craft CMS 5.0.0 or later
- PHP 8.2 or later

## Installation

Open your terminal and run:

```bash
composer require justinholtweb/craft-garrison
php craft plugin/install garrison
```

Or install from the Craft Plugin Store in your control panel.

## Configuration

Copy the default config to your project:

```bash
cp vendor/justinholtweb/craft-garrison/src/config.php config/garrison.php
```

Multi-environment config is supported. Key settings:

```php
return [
    '*' => [
        'maxLoginAttempts' => 5,
        'lockoutDuration' => 300,
        'enableAuditLog' => true,
        'scanSchedule' => 'daily',
    ],
    'production' => [
        'enableRateLimiting' => true,
        'enableFileIntegrity' => true,
        'enableNotifications' => true,
        'emailRecipients' => ['admin@example.com'],
        'slackWebhookUrl' => '$SLACK_WEBHOOK_URL',
    ],
];
```

## Modules

### Scanner

Runs 14 security checks against your site and produces a risk score (0–100). Every check returns plain-language remediation guidance:

1. **CMS Configuration** — dev mode, admin changes, test email, elevated session duration, wildcard GraphQL origins
2. **HTTPS** — verifies the site is served over TLS
3. **CSRF Protection** — confirms CSRF tokens are enabled
4. **File Permissions** — checks `.env`, `config/`, `web/index.php` for unsafe permissions
5. **PHP Version** — flags EOL or soon-to-expire PHP versions
6. **Application Security Key** — confirms a strong `securityKey` is set
7. **Cookie Security** — `useSecureCookies` and `sameSiteCookieValue` policy
8. **Upload Sanitization** — `sanitizeSvgUploads` and `sanitizeCpImageUploads`
9. **Web Root Exposure** — sensitive files (`.env`, composer manifests, `.git`) under the public root
10. **Software Updates** — pending Craft / plugin updates (uses cached update info)
11. **GraphQL Introspection** — flags introspection left on in production
12. **X-Powered-By Header** — flags the `sendPoweredByHeader` information disclosure
13. **Session Duration** — flags unusually long authenticated sessions
14. **Admin Usernames** — flags predictable admin usernames (`admin`, `root`, …)

Risk score weights: Critical (+25), High (+15), Medium (+8), Low (+3).

To disable specific checks, set the `enabledChecks` array in `config/garrison.php`.

### Shield

Active request-level protection, evaluated on `Application::EVENT_BEFORE_REQUEST`. Checks run cheapest first; control-panel traffic is exempt from rate limiting, geo-blocking, and the WAF to avoid locking out administrators:

1. **IP allow/block rules** — exact, CIDR, or wildcard patterns, scoped to CP / frontend / everywhere → 403
2. **Login lockout** — failed-attempt threshold enforced before the password is checked → 403 / 429
3. **Geo-blocking** (Pro) — block or allowlist by country, resolved from an upstream country header (Cloudflare `CF-IPCountry` by default) → 403
4. **Rate limiting** (Plus+) — fixed-window per-IP counter backed by Craft's cache → 429
5. **WAF rules** (Pro) — regex signatures for SQL injection, XSS, path traversal, and malicious user agents → 403

Every block is recorded in the database and fires a `ThreatDetectedEvent`.

### Sentinel

Monitoring and audit trail:

- **Audit log** — records logins, failed logins, logouts, element creation/deletion, plugin install/uninstall/enable/disable, and user suspend/activate events, with the acting user, IP, and user agent
- **File integrity** (Plus+) — SHA-256 baselines stored in the database; detects modified, deleted, and added files across the monitored paths

Default monitored paths: `vendor/craftcms/cms/src/`, `config/`, `.env`, `web/index.php`.

### Beacon

Multi-channel notifications (Plus+), delivered via a queue job so a slow webhook never blocks a request:

- **Email** — via Craft's built-in mailer
- **Slack** — incoming webhook URL
- **Discord** — webhook URL
- **Webhook** — generic POST with a JSON payload

Triggers: scan failure, threat detection, login lockout, and file integrity changes. Threat notifications are de-duplicated per IP so floods don't spam your channels.

## Console Commands

```bash
# Run a security scan
php craft garrison/scan/run
php craft garrison/scan/run --siteId=1

# Show the last scan status
php craft garrison/scan/status

# IP management (Plus+)
php craft garrison/shield/block 203.0.113.4 --label="abuse"
php craft garrison/shield/allow 10.0.0.0/8 --scope=cp
php craft garrison/shield/list
php craft garrison/shield/remove 3

# File integrity (Plus+)
php craft garrison/integrity/baseline
php craft garrison/integrity/check
```

## REST API (Plus+)

The API is authenticated through Craft's session and permission system — callers must be signed-in control-panel users with the relevant Garrison permissions. All endpoints return JSON and live under the control-panel trigger (e.g. `/admin`).

```
GET  garrison/api/v1/scan/last       Last scan summary
POST garrison/api/v1/scan/run        Run a scan (requires "Run security scans")
GET  garrison/api/v1/scan/<id>       A specific scan with full results
GET  garrison/api/v1/shield/status   Blocked-request count and rule count
GET  garrison/api/v1/sentinel/log    Recent audit-log entries (requires "View audit log")
```

## Scheduled Scans (Plus+)

Set `scanSchedule` to `hourly`, `daily`, `weekly`, or `monthly`. Garrison enqueues a `RunScanJob` once the interval has elapsed since the last scan; the check is throttled to run at most once a minute and only on web requests. For deterministic timing you can instead drive `php craft garrison/scan/run` from system cron.

## Permissions

| Permission | Description |
|-----------|-------------|
| Access Garrison | View the Garrison CP section |
| Run security scans | Execute manual scans |
| View audit log | Access the Sentinel audit log |
| Manage shield rules | Add/remove IP rules, manage file baselines |
| Manage Garrison settings | Access plugin settings |

## Events

| Event | Class | When |
|-------|-------|------|
| `EVENT_AFTER_SCAN` | `services\Scanner` (`ScanEvent`) | After a scan completes |
| `EVENT_THREAT_DETECTED` | `services\Shield` (`ThreatDetectedEvent`) | When Shield blocks a request |

## Widgets

- **Security Score** — current risk score on the Craft dashboard
- **Recent Threats** — recently blocked requests

## Development

```bash
composer test      # run the unit suite (Codeception)
composer ecs       # check coding standard
composer phpstan   # static analysis (level 5)
```

## License

This is a commercial plugin licensed through the [Craft Plugin Store](https://plugins.craftcms.com). The Lite edition is free. See [LICENSE.md](LICENSE.md).
