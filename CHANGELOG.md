# Changelog

## 5.1.7 - 2026-10-05

> {warning} Saving any Garrison settings screen used to reset every setting on the *other* screens
> to its default — so saving Notifications could switch Login Protection, rate limiting or the WAF
> off. Check **Garrison → Settings** and the **Shield** screens after updating and turn back on
> anything you expect to be on.

### Fixed
- Each settings screen posts only its own fields, and Craft stores only the fields it's given, so
  saving one screen erased the rest from project config. Saves now merge into the settings already
  stored. Settings from `config/garrison.php` stay in that file and aren't copied into project
  config.

- The WAF blocked ordinary visitors. It matched the raw request body, CSRF token included, and its
  SQL-injection rule fired on any `--` — which turns up in a few percent of Craft's CSRF tokens —
  and on plain English such as "select a size from the list". It now inspects the request's fields
  one at a time, skips the CSRF token and password fields, and its SQL rules need SQL context: a
  quote breaking out, `UNION SELECT`, a stacked statement, a time-delay function.
- `lockoutDuration` was saved and shown in the control panel but never used: a lockout lasted as
  long as the failed attempts stayed inside `loginAttemptWindow`. A lockout now starts when the
  threshold is crossed and lasts `lockoutDuration`. After it ends, the attempts that caused it no
  longer count. Lockouts are kept in Craft's cache, so clearing caches lifts one early.

### Security
- Settings could be saved with `allowAdminChanges` off. Saving now needs an admin on an environment
  that allows admin changes. Anyone else (users with **Manage Garrison settings**, or an admin where
  changes are off) sees the screens read-only, with no Save button and a note saying why. Before,
  **Manage Garrison settings** showed a Settings link that led to a 403.
- A successful login cleared every failed attempt from that IP address, so someone with one valid
  account could reset the lockout between guesses at another. It now clears only the failures
  recorded against the account that signed in, by username or email.

## 5.1.6 - 2026-08-19

### Fixed
- The **Settings → Plugins → Garrison** screen rendered Garrison's full settings page inside Craft's own settings page, nesting a form inside a form and leaving two `action` inputs in the markup — so saving from there could post to the wrong action. That entry now redirects to Garrison's own settings screen at `garrison/settings`.

## 5.1.5 - 2026-07-22

### Updated

- Masked icon replaced, thanks for noticing Jalen Davenport

## 5.1.4 - 2026-07-19

### Changed
- Expanded the unit-test suite from 19 to 42 tests, adding coverage for the `Severity` enum (values, labels, colors, and the risk weights that drive scan scoring), `ScanResult` status predicates, and `RiskScore` banding boundaries. No functional changes to the plugin.

### Notes
- Versions 5.1.1–5.1.3 were skipped: a `5.1.3` tag had already been published against the 5.1.0 commit, so this release jumps to 5.1.4 to stay ahead of it in version ordering.

## 5.1.0 - 2026-06-18

### Changed
- Consolidated editions from three (Free/Plus/Pro) to two: **Free** and **Pro**. All features that previously required Plus — scheduled scans, notifications, IP allow/block rules, rate limiting, file integrity monitoring, and the REST API — are now part of **Pro**. The free edition keeps its `lite` handle internally but is presented as "Free" throughout the UI.
- Audit-log retention is now 30 days on Free and 365 days on Pro (the 90-day Plus tier is gone).

## 5.0.2 - 2026-06-17

### Fixed
- Settings and Shield pages threw a Twig runtime error (`Variable "forms" does not exist`). The templates that use Craft's `forms` macros now import them with `{% import '_includes/forms' as forms %}`.

## 5.0.1 - 2026-06-17

### Fixed
- Install migration failed on utf8mb4 databases with "Specified key was too long; max key length is 3072 bytes" when creating the UNIQUE index on `garrison_file_baselines.filePath`. The column is now `VARCHAR(768)` (the maximum width for a single-column index on utf8mb4), down from `VARCHAR(1024)`. Paths are stored relative to the web root, so this does not affect functionality.

## 5.0.0 - 2026-06-11

### Added

#### Scanner (all editions)
- Security scanner with 14 checks: CMS configuration, HTTPS, CSRF, file permissions, PHP version, application security key, cookie security, upload sanitization, web-root exposure, software updates, GraphQL introspection, X-Powered-By header, session duration, and predictable admin usernames
- 0–100 risk score with per-check remediation guidance
- Scan history (last 10 on Free, unlimited on Pro) and multi-site scans
- `garrison/scan/run` and `garrison/scan/status` console commands

#### Shield — active protection
- Login brute-force protection with IP lockout, enforced before the password is checked (all editions)
- IP allow/block rules with exact, CIDR, and wildcard matching, scoped to CP / frontend / everywhere (Pro)
- Rate limiting with a per-IP fixed window (Pro)
- WAF request filtering for SQL injection, XSS, path traversal, and malicious user agents (Pro)
- Geo-blocking via an upstream country header (Pro)
- `garrison/shield/block`, `allow`, `list`, and `remove` console commands

#### Sentinel — audit & integrity
- Audit logging of authentication, element, plugin, and user events with edition-based retention (30 days Free / 365 days Pro)
- File integrity monitoring with SHA-256 baselines and change detection (Pro)
- `garrison/integrity/baseline` and `garrison/integrity/check` console commands

#### Beacon — notifications (Pro)
- Queue-based notifications over email, Slack, Discord, and generic webhooks
- Triggers for scan failures, detected threats, login lockouts, and file integrity changes, de-duplicated per IP

#### Dashboard & API
- Dashboard with risk score, last-scan summary, and Shield activity; threat analytics on Pro
- Security Score and Recent Threats control-panel widgets
- Authenticated REST API for scans, Shield status, and the audit log (Pro)
- Queue-based scheduled scans (Pro)

### Notes
- Requires Craft CMS 5.0+ and PHP 8.2+.
