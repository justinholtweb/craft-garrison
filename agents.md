# Garrison — Development Log

## Project Summary

Garrison is a comprehensive security plugin for Craft CMS 5 competing with Sherlock by PutYourLightsOn. Three editions: Lite (free), Plus ($149), Pro ($249). Four core modules — Scanner, Shield, Sentinel, Beacon — plus a Dashboard service.

**Namespace:** `justinholtweb\garrison`
**Craft CMS:** ^5.0.0 | **PHP:** ^8.2
**Pattern source:** `/Users/jholt/Sites/craft-dispatch/` (Plugin.php, Edition.php, Install.php patterns)

---

## Phase 1: Foundation — COMPLETE

### What was built

**Plugin shell & configuration**
- `Plugin.php` — registers 5 service components, CP routes, permissions, widgets. Follows craft-dispatch pattern exactly.
- `composer.json` — full Plugin Store metadata including support section, changelogUrl, developerEmail.
- `config.php` — default multi-env config with all settings documented.
- `icon.svg` — fortress/garrison with two towers, battlements, arched gate, and flag. Uses `currentColor` for light/dark theme support.

**6 PHP 8.2 enums**
- `ScanStatus` (passed/warning/failed/critical/skipped) — with label(), color(), icon() methods
- `CheckCategory` (headers/permissions/config/updates/encryption/database/authentication)
- `Severity` (info/low/medium/high/critical) — with weight() for risk score calculation
- `AuditAction` (14 actions) — with category() mapping to auth/content/settings/system
- `BlockReason` (rateLimit/ipBlocked/geoBlocked/wafRule/loginLockout/httpBasicAuth)
- `NotificationChannel` (email/slack/discord/webhook)

**6 models**
- `Edition` — LITE/PLUS/PRO constants, is(), isAtLeast(), isPlus(), isPro(), requiresPlus(), requiresPro(). Also maxScanHistory() and auditLogRetentionDays() for edition-gated limits.
- `Settings` — all plugin settings with Yii2 validation rules. Covers scanner, shield, sentinel, beacon, and pruning config.
- `ScanResult` — single check result with status, severity, message, details, remediation.
- `ScanReport` — complete scan with addResult(), calculateStatus(), calculateRiskScore(), riskLabel(), riskColor(). Risk algorithm: Critical +25, High +15, Medium +8, Low +3, Warning +1, capped at 100.
- `AccessRule` — IP/CIDR rule with matchesIp() supporting exact, CIDR, and wildcard patterns.
- `RiskScore` — simple model with getLabel(), getColor(), getGrade() (A–F).

**7 ActiveRecord classes**
- `ScanRecord`, `ScanResultRecord`, `AuditLogRecord`, `FileBaselineRecord`, `AccessRuleRecord`, `BlockedRequestRecord`, `LoginAttemptRecord`

**Install migration**
- Creates all 7 tables (`garrison_scans`, `garrison_scan_results`, `garrison_audit_log`, `garrison_file_baselines`, `garrison_access_rules`, `garrison_blocked_requests`, `garrison_login_attempts`) with indexes and foreign keys to `sites` and `users`.

**Scanner service (fully functional)**
- `Scanner.php` — orchestrates checks, persists results, builds reports from DB records, prunes old scans per edition limit.
- `BaseScannerCheck` — abstract base with pass(), fail(), warning(), skip() helpers that return ScanResult.
- 5 working checks:
  - `CmsConfigCheck` — dev mode, allowAdminChanges, testToEmailAddress, elevatedSessionDuration, GraphQL origins
  - `HttpsCheck` — site URL scheme + current connection detection
  - `CsrfCheck` — enableCsrfProtection config
  - `FilePermissionsCheck` — .env permissions, config/ writable, web/index.php writable, storage dir exists
  - `PhpVersionCheck` — EOL lookup table through PHP 8.4, warns 6 months before EOL

**4 stub services**
- `Shield.php` — handleRequest() stub for Phase 3
- `Sentinel.php` — log() stub for Phase 2
- `Beacon.php` — notify() stub for Phase 4
- `Dashboard.php` — getAnalytics() stub for Phase 5

**6 controllers**
- `DashboardController` — renders dashboard with last scan data
- `ScannerController` — index, run (POST), results, history actions
- `ShieldController` — index, ip-management, rate-limits, login-protection, waf
- `SentinelController` — index, audit-log, file-integrity
- `SettingsController` — index, notifications, scanner, advanced, save (POST)
- `ApiController` — stub with edition gate (Plus+)

**Console commands**
- `garrison/scan/run` — runs scan with colored terminal output (green ✓, yellow ⚠, red ✗), returns non-zero exit on critical findings
- `garrison/scan/status` — displays last scan summary

**4 queue jobs**
- `RunScanJob` — triggers scanner from queue for scheduled scans
- `FileIntegrityCheckJob` — stub for Phase 4
- `SendNotificationJob` — stub for Phase 4
- `PruneDataJob` — deletes old blocked requests and login attempts based on retention settings

**2 widgets**
- `SecurityScoreWidget` — shows risk score number + grade on Craft dashboard
- `RecentThreatsWidget` — placeholder for Phase 5

**3 events**
- `ScanEvent` — fired after scan, carries ScanReport
- `ThreatDetectedEvent` — carries type, ipAddress, reason, details
- `AuditEvent` — carries action, category, userId, targetType, targetId, details

**17 Twig templates**
- Layout: `_layouts/plugin.twig` extending `_layouts/cp`
- Dashboard: index with risk score card, stat grid, last scan summary
- Scanner: index (results table + run form), results (full detail view), history (paginated list)
- Shield: index (nav cards to sub-pages), ip-management, rate-limits, login-protection, waf (stubs)
- Sentinel: index (nav cards), audit-log, file-integrity (stubs)
- Settings: index (login protection + audit + scan schedule + pruning), notifications, scanner (check list), advanced
- Components: `_risk-gauge.twig`, `_scan-result-row.twig`, `_edition-badge.twig`

**CSS/JS assets**
- `CpAsset.php` + `garrison.css` (200+ lines: cards, risk score display, stat grid, status/severity badges, remediation callouts, edition badges, nav cards, responsive layout)
- `garrison.js` — scan button loading state
- `DashboardAsset.php` — stub for Phase 5 chart assets

**Translations**
- `translations/en/garrison.php` — 80+ translation strings covering all UI text

### CP routes registered

```
garrison                              → dashboard/index
garrison/scanner                      → scanner/index
garrison/scanner/results/<scanId:\d+> → scanner/results
garrison/scanner/history              → scanner/history
garrison/shield                       → shield/index
garrison/shield/ip-management         → shield/ip-management
garrison/shield/rate-limits           → shield/rate-limits
garrison/shield/login-protection      → shield/login-protection
garrison/shield/waf                   → shield/waf
garrison/sentinel                     → sentinel/index
garrison/sentinel/audit-log           → sentinel/audit-log
garrison/sentinel/file-integrity      → sentinel/file-integrity
garrison/settings                     → settings/index
garrison/settings/notifications       → settings/notifications
garrison/settings/scanner             → settings/scanner
garrison/settings/advanced            → settings/advanced
```

### Permissions registered

- `garrison:accessPlugin` — top-level access
  - `garrison:runScans`
  - `garrison:viewAuditLog`
  - `garrison:manageShield`
- `garrison:manageSettings`

---

## Plugin Store Prep — COMPLETE

- `composer.json` — full metadata: handle, name, description, developer, developerUrl, developerEmail, documentationUrl, changelogUrl, support section with email/issues/docs
- `LICENSE.md` — standard Craft License (proprietary, 5 clauses, recommended for commercial plugins)
- `README.md` — comprehensive docs: edition matrix, requirements, installation, multi-env config example, all 4 modules documented, console commands, REST API endpoints, permissions table, events table, widgets
- `CHANGELOG.md` — follows `## X.Y.Z - YYYY-MM-DD` format (currently `Unreleased`)
- `icon.svg` — fortress with towers, battlements, arched gate, flag (stroked, for Settings page)
- `icon-mask.svg` — solid fill-only fortress silhouette (no strokes, for CP nav sidebar)
- `.gitignore` — vendor, node_modules, IDE files

---

## Remaining Phases

### Phase 2: Complete Scanner + Audit Log
- 9 remaining scanner checks: HttpHeaders, ContentSecurityPolicy, Cors, CmsVersion, PluginVersion, DatabaseSecurity, AdminAccount, SslCertificate (placeholders exist in plan)
- Sentinel audit logging — hook into Craft events: User login/logout, Elements save/delete, Plugins install/uninstall, ProjectConfig changes
- CP alerts on failed scans
- Queue-based scheduled scanning (cache flag check → push RunScanJob)
- Scan history pagination
- User permissions enforcement throughout
- `ScanEvent` and `AuditEvent` firing

### Phase 3: Shield — Active Protection
- `LoginProtection.php` — brute-force detection + lockout (Lite+)
- `IpManager.php` — allow/block rules with CIDR matching (Plus+)
- `RateLimiter.php` — cache-based atomic request counting (Plus+)
- Shield service `handleRequest()` — wired to `Application::EVENT_BEFORE_REQUEST`
- `ShieldController` console commands — block-ip, unblock-ip, list
- Shield CP pages — login protection settings, IP management CRUD, rate limit config
- `ThreatDetectedEvent` firing
- `BlockedRequestRecord` logging

### Phase 4: Notifications + API + File Integrity
- `Beacon.php` — email (Craft mailer), Slack webhook, Discord webhook, generic webhook
- `SendNotificationJob` — queued multi-channel dispatch
- `ApiController` — full REST API with auth
- `FileIntegrityCheckJob` — SHA-256 baseline comparison
- Sentinel file integrity CP page
- HTTP Basic Auth middleware

### Phase 5: WAF + Geo-blocking + Dashboard
- `RequestFilter.php` — WAF engine with compiled regex
- WAF rules: `SqlInjectionRule`, `XssRule`, `PathTraversalRule`, `UserAgentRule`
- `GeoBlocker.php` — country-based blocking
- `Dashboard.php` — analytics aggregation with trends
- Dashboard CP page with charts
- `RecentThreatsWidget` implementation

### Phase 6: Polish
- Tests
- Complete translations
- Data pruning edge cases
- Config documentation
- CHANGELOG finalization with release date

---

## File Inventory (77 source files)

```
src/
├── Plugin.php
├── icon.svg
├── icon-mask.svg
├── config.php
├── enums/                          (6 files)
├── models/                         (6 files)
├── services/                       (5 files — Scanner functional, 4 stubs)
├── scanners/                       (6 files — base + 5 checks)
├── shield/rules/                   (empty — Phase 5)
├── controllers/                    (6 files)
├── console/controllers/            (1 file)
├── queue/jobs/                     (4 files — RunScan functional, 3 stubs)
├── records/                        (7 files)
├── events/                         (3 files)
├── widgets/                        (2 files)
├── migrations/                     (1 file — Install with 7 tables)
├── web/assets/cp/                  (CpAsset + CSS + JS)
├── web/assets/dashboard/           (DashboardAsset stub)
├── templates/                      (17 twig files across 6 directories)
└── translations/en/                (1 file — 80+ strings)
```
