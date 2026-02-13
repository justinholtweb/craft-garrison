# Garrison — Security Suite for Craft CMS

Comprehensive security plugin for Craft CMS 5. Vulnerability scanning, active protection, audit logging, and multi-channel notifications in a single install.

## Editions

| Feature | Lite (Free) | Plus | Pro |
|---------|:-----------:|:----:|:---:|
| Security scanner (14 checks) | ✓ | ✓ | ✓ |
| CMS config / headers / CSP checks | ✓ | ✓ | ✓ |
| Login brute-force protection | ✓ | ✓ | ✓ |
| Audit logging | 30 days | 90 days | 365 days |
| Console commands | ✓ | ✓ | ✓ |
| Scan history | Last 10 | Unlimited | Unlimited |
| Scheduled scans (queue-based) | — | ✓ | ✓ |
| CP alerts on failed scan | — | ✓ | ✓ |
| Email notifications | — | ✓ | ✓ |
| Slack / Discord / Webhook | — | ✓ | ✓ |
| IP restriction (CP + frontend, CIDR) | — | ✓ | ✓ |
| HTTP Basic Auth | — | ✓ | ✓ |
| File integrity monitoring | — | ✓ | ✓ |
| REST API | — | ✓ | ✓ |
| Multi-site scans | — | ✓ | ✓ |
| Rate limiting | — | ✓ | ✓ |
| WAF / request filtering | — | — | ✓ |
| Geo-blocking | — | — | ✓ |
| Dashboard analytics + trends | — | — | ✓ |
| Risk score trending | — | — | ✓ |
| Auto-remediation suggestions | — | — | ✓ |

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

Runs 14 security checks against your site and produces a risk score (0–100):

- **CMS Configuration** — dev mode, admin changes, test email, session duration, GraphQL origins
- **HTTPS** — verifies site is served over TLS
- **CSRF Protection** — confirms CSRF tokens are enabled
- **File Permissions** — checks .env, config/, web/index.php for unsafe permissions
- **PHP Version** — flags EOL or soon-to-expire PHP versions
- **HTTP Headers** — X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
- **Content Security Policy** — checks for CSP header presence and configuration
- **CORS** — validates cross-origin resource sharing settings
- **CMS Version** — flags outdated Craft CMS installations
- **Plugin Versions** — checks for plugins with available updates
- **Database Security** — table prefix usage, default credentials
- **Admin Accounts** — flags accounts using weak or default usernames
- **SSL Certificate** — checks certificate expiry
- **File Permissions (detailed)** — world-readable/writable sensitive paths

Risk score weights: Critical (+25), High (+15), Medium (+8), Low (+3), Warning (+1).

### Shield

Active request-level protection. Checks run on every request, ordered cheapest to most expensive:

1. **IP blocklist** — in-memory cached lookup → 403
2. **Login lockout** — brute-force threshold check → 403
3. **IP allowlist** — optional restrict-to-list mode → 403
4. **Geo-blocking** (Pro) — country-based blocking → 403
5. **Rate limiting** — cache-based atomic increments → 429
6. **WAF rules** (Pro) — compiled regex patterns for SQL injection, XSS, path traversal, user-agent filtering → 403

### Sentinel

Monitoring and audit trail:

- **Audit log** — tracks logins, logouts, failed logins, element saves/deletes, plugin installs, project config changes, user events
- **File integrity** (Plus+) — SHA-256 baselines stored in the database; detects modified, added, or deleted files in monitored paths

Default monitored paths: `vendor/craftcms/cms/src/`, `config/`, `.env`, `web/index.php`.

### Beacon

Multi-channel notifications (Plus+):

- **Email** — via Craft's built-in mailer
- **Slack** — webhook URL
- **Discord** — webhook URL
- **Webhook** — generic POST with JSON payload

Triggers: scan failure, threat detection, login lockout.

## Console Commands

```bash
# Run a security scan
php craft garrison/scan/run
php craft garrison/scan/run --site-id=1

# Check last scan status
php craft garrison/scan/status

# IP management
php craft garrison/shield/block-ip 1.2.3.4
php craft garrison/shield/unblock-ip 1.2.3.4
php craft garrison/shield/list

# File integrity
php craft garrison/sentinel/baseline
php craft garrison/sentinel/check

# Data pruning
php craft garrison/prune
```

## REST API (Plus+)

```
GET  garrison/api/v1/scan/run       Run a scan
GET  garrison/api/v1/scan/last      Last scan results
GET  garrison/api/v1/scan/all       All scans
GET  garrison/api/v1/scan/<id>      Specific scan
GET  garrison/api/v1/shield/status  Shield status
POST garrison/api/v1/shield/block   Block an IP
POST garrison/api/v1/shield/unblock Unblock an IP
GET  garrison/api/v1/sentinel/log   Audit log
```

## Permissions

| Permission | Description |
|-----------|-------------|
| Access Garrison | View the Garrison CP section |
| Run security scans | Execute manual scans |
| View audit log | Access the Sentinel audit log |
| Manage shield rules | Add/remove IP rules and shield settings |
| Manage Garrison settings | Access plugin settings |

## Events

Garrison fires custom events for extensibility:

| Event | Class | When |
|-------|-------|------|
| `ScanEvent` | `justinholtweb\garrison\events\ScanEvent` | After a scan completes |
| `ThreatDetectedEvent` | `justinholtweb\garrison\events\ThreatDetectedEvent` | When Shield blocks a request |
| `AuditEvent` | `justinholtweb\garrison\events\AuditEvent` | When an audit log entry is created |

## Widgets

- **Security Score** — displays current risk score on the Craft dashboard
- **Recent Threats** — shows recently blocked requests (Pro)

## License

This plugin requires a license purchased through the [Craft Plugin Store](https://plugins.craftcms.com). The Lite edition is free. See [LICENSE.md](LICENSE.md).
