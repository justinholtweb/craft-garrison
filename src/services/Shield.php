<?php

namespace justinholtweb\garrison\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use justinholtweb\garrison\enums\BlockReason;
use justinholtweb\garrison\events\ThreatDetectedEvent;
use justinholtweb\garrison\models\AccessRule;
use justinholtweb\garrison\models\Edition;
use justinholtweb\garrison\Plugin;
use justinholtweb\garrison\records\AccessRuleRecord;
use justinholtweb\garrison\records\BlockedRequestRecord;
use justinholtweb\garrison\records\LoginAttemptRecord;
use yii\web\ForbiddenHttpException;
use yii\web\TooManyRequestsHttpException;

/**
 * Shield — active request protection.
 *
 * handleRequest() runs on Application::EVENT_BEFORE_REQUEST. Login protection
 * is enforced through the Craft login lifecycle (see Plugin::registerShield()).
 */
class Shield extends Component
{
    public const EVENT_THREAT_DETECTED = 'threatDetected';

    /**
     * Inspect the incoming request and block it if it violates any active rule.
     *
     * Checks run cheapest first: IP rules, then (for non–control-panel traffic)
     * geo-blocking, rate limiting, and WAF inspection.
     */
    public function handleRequest(): void
    {
        $request = Craft::$app->getRequest();

        if ($request->getIsConsoleRequest()) {
            return;
        }

        $ip = $request->getUserIP();
        if ($ip === null) {
            return;
        }

        $settings = Plugin::getInstance()->getSettings();
        $isCp = $request->getIsCpRequest();

        // 1. IP allow/block rules (apply to both the CP and the front end).
        if ($settings->enableIpRestriction) {
            $this->enforceIpRules($ip, $isCp);
        }

        // Remaining checks are skipped for control-panel traffic to avoid
        // locking out legitimate authenticated administrators.
        if ($isCp) {
            return;
        }

        // 2. Geo-blocking (Pro).
        if ($settings->enableGeoBlocking && Edition::isPro()) {
            $this->enforceGeoRules($ip, $settings);
        }

        // 3. Rate limiting (Pro).
        if ($settings->enableRateLimiting && Edition::isPro()) {
            $this->enforceRateLimit($ip, $settings);
        }

        // 4. WAF inspection (Pro).
        if ($settings->enableWaf && Edition::isPro()) {
            $this->enforceWaf($request, $ip, $settings);
        }
    }

    // Login protection
    // -------------------------------------------------------------------------

    /**
     * Record a login attempt and, on failure, lock the IP out once it crosses
     * the configured threshold.
     */
    public function recordLoginAttempt(string $ip, ?string $username, bool $successful): void
    {
        $record = new LoginAttemptRecord();
        $record->ipAddress = $ip;
        $record->username = $username ? substr($username, 0, 255) : null;
        $record->successful = $successful;
        $record->save(false);

        if ($successful) {
            // Clear the failure streak so a fresh login isn't immediately re-locked.
            LoginAttemptRecord::deleteAll([
                'and',
                ['ipAddress' => $ip, 'successful' => false],
            ]);
            return;
        }

        if ($this->isLockedOut($ip)) {
            $this->onLockout($ip, $username);
        }
    }

    public function getRecentFailedAttempts(string $ip): int
    {
        $settings = Plugin::getInstance()->getSettings();
        $cutoff = Db::prepareDateForDb(
            (new \DateTime('now', new \DateTimeZone('UTC')))->modify("-{$settings->loginAttemptWindow} seconds")
        );

        return (int) LoginAttemptRecord::find()
            ->where(['ipAddress' => $ip, 'successful' => false])
            ->andWhere(['>=', 'dateCreated', $cutoff])
            ->count();
    }

    public function isLockedOut(string $ip): bool
    {
        $settings = Plugin::getInstance()->getSettings();

        return $this->getRecentFailedAttempts($ip) >= $settings->maxLoginAttempts;
    }

    /**
     * Throw if the IP is currently locked out. Called at the start of the login
     * flow so the password is never even checked while locked.
     */
    public function enforceLoginLockout(string $ip): void
    {
        if ($this->isLockedOut($ip)) {
            $this->blockRequest($ip, BlockReason::LoginLockout, [
                'failedAttempts' => $this->getRecentFailedAttempts($ip),
            ]);
        }
    }

    // IP management
    // -------------------------------------------------------------------------

    /**
     * @return AccessRule[]
     */
    public function getAccessRules(?string $type = null): array
    {
        $query = AccessRuleRecord::find()->orderBy(['dateCreated' => SORT_DESC]);
        if ($type !== null) {
            $query->where(['type' => $type]);
        }

        /** @var AccessRuleRecord[] $records */
        $records = $query->all();

        return array_map(fn(AccessRuleRecord $r) => $this->ruleFromRecord($r), $records);
    }

    public function getAccessRuleById(int $id): ?AccessRule
    {
        $record = AccessRuleRecord::findOne($id);

        return $record ? $this->ruleFromRecord($record) : null;
    }

    public function saveAccessRule(AccessRule $rule): bool
    {
        if (!$rule->validate()) {
            return false;
        }

        $record = $rule->id ? AccessRuleRecord::findOne($rule->id) : new AccessRuleRecord();
        if (!$record) {
            return false;
        }

        $record->type = $rule->type;
        $record->scope = $rule->scope;
        $record->ipPattern = $rule->ipPattern;
        $record->countryCode = $rule->countryCode;
        $record->label = $rule->label;
        $record->enabled = $rule->enabled;
        $record->expiresAt = $rule->expiresAt ? Db::prepareDateForDb($rule->expiresAt) : null;
        $record->createdBy = $rule->createdBy ?? Craft::$app->getUser()->getId();
        $record->save(false);

        $rule->id = $record->id;

        return true;
    }

    public function deleteAccessRule(int $id): bool
    {
        $record = AccessRuleRecord::findOne($id);

        return $record ? (bool) $record->delete() : false;
    }

    // Threat / blocked-request reporting
    // -------------------------------------------------------------------------

    /**
     * @return BlockedRequestRecord[]
     */
    public function getBlockedRequests(int $limit = 50, int $offset = 0): array
    {
        /** @var BlockedRequestRecord[] $records */
        $records = BlockedRequestRecord::find()
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit($limit)
            ->offset($offset)
            ->all();

        return $records;
    }

    public function getBlockedRequestCount(?string $since = null): int
    {
        $query = BlockedRequestRecord::find();
        if ($since !== null) {
            $query->where(['>=', 'dateCreated', $since]);
        }

        return (int) $query->count();
    }

    /**
     * Record a blocked request, fire the threat event, queue notifications, and
     * terminate the request with an appropriate HTTP error.
     *
     * @throws ForbiddenHttpException|TooManyRequestsHttpException
     */
    public function blockRequest(string $ip, BlockReason $reason, array $details = []): never
    {
        $request = Craft::$app->getRequest();

        $record = new BlockedRequestRecord();
        $record->ipAddress = $ip;
        $record->reason = $reason->value;
        $record->details = $details ?: null;
        $record->requestUri = substr((string) $request->getUrl(), 0, 2048);
        $record->requestMethod = $request->getMethod();
        $record->userAgent = substr((string) $request->getUserAgent(), 0, 500);
        $record->countryCode = $details['countryCode'] ?? null;
        $record->save(false);

        $event = new ThreatDetectedEvent();
        $event->type = $reason->value;
        $event->ipAddress = $ip;
        $event->reason = $reason->label();
        $event->details = $details;
        $this->trigger(self::EVENT_THREAT_DETECTED, $event);

        Plugin::getInstance()->beacon->notifyThreat($reason, $ip, $details);

        if ($reason === BlockReason::RateLimit) {
            throw new TooManyRequestsHttpException(Craft::t('garrison', 'Too many requests.'));
        }

        throw new ForbiddenHttpException(Craft::t('garrison', 'Access denied.'));
    }

    // Internal enforcement
    // -------------------------------------------------------------------------

    private function enforceIpRules(string $ip, bool $isCp): void
    {
        $scope = $isCp ? 'cp' : 'frontend';
        $rules = array_filter(
            $this->getAccessRules(),
            fn(AccessRule $r) => $r->enabled
                && in_array($r->scope, [$scope, 'all'], true)
                && !$this->isExpired($r)
        );

        // Explicit block always wins.
        foreach ($rules as $rule) {
            if ($rule->type === 'block' && $rule->ipPattern && $rule->matchesIp($ip)) {
                $this->blockRequest($ip, BlockReason::IpBlocked, ['rule' => $rule->ipPattern]);
            }
        }

        // Allowlist mode: if any allow rules exist for this scope, the IP must
        // match one of them.
        $allowRules = array_filter($rules, fn(AccessRule $r) => $r->type === 'allow');
        if (!empty($allowRules)) {
            foreach ($allowRules as $rule) {
                if ($rule->ipPattern && $rule->matchesIp($ip)) {
                    return;
                }
            }
            $this->blockRequest($ip, BlockReason::IpBlocked, ['mode' => 'allowlist']);
        }
    }

    private function enforceGeoRules(string $ip, $settings): void
    {
        $country = $this->resolveCountry();
        if ($country === null) {
            return;
        }

        $listed = in_array($country, array_map('strtoupper', $settings->blockedCountries), true);
        $blocked = $settings->geoBlockMode === 'allow' ? !$listed : $listed;

        if ($blocked) {
            $this->blockRequest($ip, BlockReason::GeoBlocked, [
                'countryCode' => $country,
                'mode' => $settings->geoBlockMode,
            ]);
        }
    }

    private function enforceRateLimit(string $ip, $settings): void
    {
        $cache = Craft::$app->getCache();
        $key = "garrison:ratelimit:$ip";
        $count = (int) $cache->get($key);

        if ($count >= $settings->rateLimit) {
            $this->blockRequest($ip, BlockReason::RateLimit, [
                'limit' => $settings->rateLimit,
                'window' => $settings->rateLimitWindow,
            ]);
        }

        // First hit in the window seeds the counter with its TTL; later hits
        // increment without extending it (fixed-window limiter).
        if ($count === 0) {
            $cache->set($key, 1, $settings->rateLimitWindow);
        } else {
            $cache->set($key, $count + 1, $settings->rateLimitWindow);
        }
    }

    private function enforceWaf($request, string $ip, $settings): void
    {
        $rule = $this->matchWafRules($request, $settings->wafRules);
        if ($rule !== null) {
            $this->blockRequest($ip, BlockReason::WafRule, ['rule' => $rule]);
        }
    }

    /**
     * Return the handle of the first WAF rule the request trips, or null.
     */
    public function matchWafRules($request, array $enabledRules): ?string
    {
        $haystacks = array_merge(
            array_values($request->getQueryParams()),
            [$request->getRawBody()],
        );
        $values = $this->flatten($haystacks);
        $blob = strtolower(implode("\n", $values) . "\n" . $request->getUrl());

        $patterns = [
            'sql-injection' => '/(\bunion\b.+\bselect\b|\bselect\b.+\bfrom\b|\binsert\b.+\binto\b|\bdrop\b.+\btable\b|--|\bor\b\s+1\s*=\s*1)/i',
            'xss' => '/(<script\b|javascript:|onerror\s*=|onload\s*=|<iframe\b)/i',
            'path-traversal' => '#(\.\./|\.\.\\\\|/etc/passwd|\bphp://|\bfile://)#i',
        ];

        foreach ($patterns as $handle => $pattern) {
            if (in_array($handle, $enabledRules, true) && preg_match($pattern, $blob)) {
                return $handle;
            }
        }

        if (in_array('user-agent', $enabledRules, true)) {
            $ua = strtolower((string) $request->getUserAgent());
            if ($ua === '' || preg_match('/(sqlmap|nikto|nmap|masscan|nessus|acunetix|fimap)/i', $ua)) {
                return 'user-agent';
            }
        }

        return null;
    }

    private function ruleFromRecord(AccessRuleRecord $record): AccessRule
    {
        $rule = new AccessRule();
        $rule->id = $record->id;
        $rule->type = $record->type;
        $rule->scope = $record->scope;
        $rule->ipPattern = $record->ipPattern;
        $rule->countryCode = $record->countryCode;
        $rule->label = $record->label;
        $rule->enabled = (bool) $record->enabled;
        $rule->expiresAt = $record->expiresAt ? new \DateTime($record->expiresAt) : null;
        $rule->createdBy = $record->createdBy;

        return $rule;
    }

    private function isExpired(AccessRule $rule): bool
    {
        return $rule->expiresAt !== null && $rule->expiresAt < new \DateTime();
    }

    /**
     * Resolve the visitor's country from an upstream proxy header (Cloudflare's
     * CF-IPCountry by default). Geo-blocking is a no-op without one.
     */
    private function resolveCountry(): ?string
    {
        $header = Craft::$app->getRequest()->getHeaders()->get('CF-IPCountry');
        if ($header === null || $header === '' || strtoupper($header) === 'XX') {
            return null;
        }

        return strtoupper(substr($header, 0, 2));
    }

    private function onLockout(string $ip, ?string $username): void
    {
        $settings = Plugin::getInstance()->getSettings();

        if ($settings->enableAuditLog) {
            Plugin::getInstance()->sentinel->log('loginFailed', 'auth', [
                'ipAddress' => $ip,
                'details' => ['username' => $username, 'lockout' => true],
            ]);
        }

        if ($settings->notifyOnLoginLockout) {
            Plugin::getInstance()->beacon->notifyThreat(BlockReason::LoginLockout, $ip, [
                'username' => $username,
            ]);
        }
    }

    /**
     * @return string[]
     */
    private function flatten(array $values): array
    {
        $out = [];
        array_walk_recursive($values, function($value) use (&$out) {
            if (is_scalar($value)) {
                $out[] = (string) $value;
            }
        });

        return $out;
    }
}
