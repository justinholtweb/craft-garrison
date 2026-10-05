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
    /**
     * @param string[] $identities on success, every name the user could have typed (username,
     *                             email) — only failures against those are cleared
     */
    public function recordLoginAttempt(string $ip, ?string $username, bool $successful, array $identities = []): void
    {
        $record = new LoginAttemptRecord();
        $record->ipAddress = $ip;
        $record->username = $username ? substr($username, 0, 255) : null;
        $record->successful = $successful;
        $record->save(false);

        if ($successful) {
            // Clear this account's failure streak so a fresh login isn't immediately re-locked —
            // and only this account's. Clearing the whole IP let anyone with one valid account
            // reset the lockout between guesses at another.
            $names = array_values(array_unique(array_filter(
                array_map(fn($name) => is_string($name) ? substr($name, 0, 255) : '', [$username, ...$identities]),
                fn($name) => $name !== '',
            )));

            if ($names !== []) {
                LoginAttemptRecord::deleteAll([
                    'and',
                    ['ipAddress' => $ip, 'successful' => false],
                    ['username' => $names],
                ]);
            }

            return;
        }

        // Start a lockout once the threshold is crossed — once, not on every later failure.
        if ($this->getLockoutEnd($ip) === null
            && $this->getRecentFailedAttempts($ip) >= Plugin::getInstance()->getSettings()->maxLoginAttempts) {
            $this->startLockout($ip);
            $this->onLockout($ip, $username);
        }
    }

    /**
     * Failed attempts that count towards the next lockout: inside `loginAttemptWindow`, and after
     * the last lockout ended — the attempts that caused a lockout don't cause another one.
     */
    public function getRecentFailedAttempts(string $ip): int
    {
        $settings = Plugin::getInstance()->getSettings();
        $since = time() - $settings->loginAttemptWindow;
        $lock = $this->lockout($ip);

        if ($lock !== null) {
            $since = max($since, $lock['until']);
        }

        return (int) LoginAttemptRecord::find()
            ->where(['ipAddress' => $ip, 'successful' => false])
            ->andWhere(['>=', 'dateCreated', Db::prepareDateForDb(new \DateTime('@' . $since))])
            ->count();
    }

    /**
     * When the IP's lockout ends, or null if it isn't locked out.
     */
    public function getLockoutEnd(string $ip): ?\DateTime
    {
        $lock = $this->lockout($ip);

        if ($lock !== null && $lock['until'] > time()) {
            return new \DateTime('@' . $lock['until']);
        }

        return null;
    }

    public function isLockedOut(string $ip): bool
    {
        if ($this->getLockoutEnd($ip) !== null) {
            return true;
        }

        // Over the threshold with no lockout on record — the cache was cleared, or the lockout
        // hasn't been started yet. Locked until enough of those attempts leave the window.
        return $this->getRecentFailedAttempts($ip) >= Plugin::getInstance()->getSettings()->maxLoginAttempts;
    }

    /**
     * Lock the IP out for `lockoutDuration` seconds from now.
     *
     * Kept in Craft's cache — clearing caches lifts it — for `lockoutDuration` plus
     * `loginAttemptWindow`, so that after it ends the attempts that caused it stop counting.
     */
    private function startLockout(string $ip): void
    {
        $settings = Plugin::getInstance()->getSettings();

        Craft::$app->getCache()->set(
            $this->lockoutKey($ip),
            ['until' => time() + $settings->lockoutDuration],
            $settings->lockoutDuration + $settings->loginAttemptWindow,
        );
    }

    /**
     * @return array{until:int}|null
     */
    private function lockout(string $ip): ?array
    {
        $lock = Craft::$app->getCache()->get($this->lockoutKey($ip));

        return is_array($lock) && isset($lock['until']) ? ['until' => (int) $lock['until']] : null;
    }

    private function lockoutKey(string $ip): string
    {
        return 'garrison:lockout:' . md5($ip);
    }

    /**
     * Throw if the IP is currently locked out. Called at the start of the login
     * flow so the password is never even checked while locked.
     */
    public function enforceLoginLockout(string $ip): void
    {
        if ($this->isLockedOut($ip)) {
            $this->blockRequest($ip, BlockReason::LoginLockout, [
                'lockedUntil' => $this->getLockoutEnd($ip)?->format(\DateTime::ATOM),
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
     * SQL-injection signatures. Each one needs SQL context — a quote or bracket breaking out, a
     * UNION SELECT, a stacked statement — because the plain words are ordinary English: "select a
     * size from the list" is a product page, and `--` is punctuation (and turns up in a few percent
     * of Craft's CSRF tokens, which until 5.1.7 blocked that many ordinary form posts).
     */
    private const SQLI_PATTERN = '/(?:'
        . '\bunion\b(?:\s|\/\*.*?\*\/)+(?:all\s+|distinct\s+)?select\b'          // UNION SELECT
        . '|[\'"`)]\s*(?:or|and)\s+[\'"`]?\w*[\'"`]?\s*(?:=|like\b)'               // ' OR 'a'='a
        . '|\bor\b\s+1\s*=\s*1\b'                                                   // OR 1=1
        . '|[\'"`]\s*(?:--|\/\*)'                                                    // admin'--
        . '|[\'"`)]\s*;\s*(?:drop|delete|truncate|insert|update|alter|create|exec)\b' // '; DROP …
        . '|\bdrop\s+table\b'
        . '|\binsert\s+into\b[\s\S]+?\bvalues\s*\('
        . '|\b(?:sleep|benchmark|pg_sleep)\(\s*\d'                                   // MySQL wants no space before (
        . '|\bwaitfor\s+delay\b'
        . '|\binformation_schema\b'
        . '|\bload_file\s*\('
        . '|\binto\s+(?:out|dump)file\b'
        . ')/i';

    /**
     * Return the handle of the first WAF rule the request trips, or null.
     */
    public function matchWafRules($request, array $enabledRules): ?string
    {
        // Each value on its own: joined into one string, a quote closing one field and a `--`
        // opening the next would read as an injection that neither field contains.
        $values = $this->flatten(array_merge(
            array_values($request->getQueryParams()),
            $this->bodyValues($request),
        ));
        $values[] = (string) $request->getUrl();

        $patterns = [
            'sql-injection' => self::SQLI_PATTERN,
            'xss' => '/(<script\b|javascript:|onerror\s*=|onload\s*=|<iframe\b)/i',
            'path-traversal' => '#(\.\./|\.\.\\\\|/etc/passwd|\bphp://|\bfile://)#i',
        ];

        foreach ($patterns as $handle => $pattern) {
            if (!in_array($handle, $enabledRules, true)) {
                continue;
            }
            foreach ($values as $value) {
                if (preg_match($pattern, $value)) {
                    return $handle;
                }
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
     * The request body's values, as the application will read them.
     *
     * Parsed fields rather than the raw body: the raw body is URL-encoded, and it carries the CSRF
     * token, which is random and was tripping signatures. Password fields are skipped too — they
     * are hashed, never queried or echoed, and a strong password is exactly the kind of string a
     * signature fires on. A body Craft can't parse into fields (XML, plain text) is inspected raw.
     *
     * @return array<mixed>
     */
    private function bodyValues($request): array
    {
        $params = method_exists($request, 'getBodyParams') ? $request->getBodyParams() : [];

        if (!is_array($params) || $params === []) {
            $raw = (string) $request->getRawBody();

            return $raw === '' ? [] : [$raw];
        }

        $csrfParam = is_object($request) && property_exists($request, 'csrfParam') ? $request->csrfParam : null;
        if (is_string($csrfParam)) {
            unset($params[$csrfParam]);
        }

        return $this->withoutPasswords($params);
    }

    /**
     * @param array<mixed> $params
     * @return array<mixed>
     */
    private function withoutPasswords(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_string($key) && stripos($key, 'password') !== false) {
                unset($params[$key]);
            } elseif (is_array($value)) {
                $params[$key] = $this->withoutPasswords($value);
            }
        }

        return $params;
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
