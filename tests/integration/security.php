<?php
/**
 * Saving Garrison's settings screens, and who can reset a login lockout — checked in the
 * plugin-testing harness, over HTTP where it matters.
 *
 *     docker exec -w /var/www/html ddev-plugin-testing-web php /var/www/craft-garrison/tests/integration/security.php
 *
 * Until 5.1.7 each settings screen posted only its own fields and Craft's savePluginSettings()
 * writes only the keys it is given, so saving Notifications reset Login Protection (and every other
 * screen) to its defaults — switching protections off without a word. Saves were also allowed with
 * allowAdminChanges off, and one successful login cleared every failed attempt from that IP, so a
 * valid account could reset the lockout between guesses at another one.
 *
 * Restores Garrison's stored settings and the harness .env when it finishes. Login attempts use
 * TEST-NET addresses, never the harness's own.
 */

$root = getcwd();
require $root . '/bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use craft\elements\User;
use craft\services\ProjectConfig;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use justinholtweb\garrison\Plugin;
use justinholtweb\garrison\records\BlockedRequestRecord;
use justinholtweb\garrison\records\LoginAttemptRecord;

$passed = 0;
$failed = 0;

function check(string $label, callable $test): void
{
    global $passed, $failed;

    try {
        $result = $test();
    } catch (Throwable $e) {
        $result = get_class($e) . ': ' . $e->getMessage();
    }

    if ($result === true) {
        $passed++;
        echo "  ✓ $label\n";
    } else {
        $failed++;
        echo "  ✗ $label\n    " . (is_string($result) ? $result : var_export($result, true)) . "\n";
    }
}

Craft::$app->getPlugins()->loadPlugins();

// craft-penny's broken beforeSaveElement handler (see craft-bird's checks.php) — detached in-process only.
if (Craft::$app->getPlugins()->isPluginEnabled('penny')) {
    yii\base\Event::off(craft\services\Elements::class, craft\services\Elements::EVENT_BEFORE_SAVE_ELEMENT);
}

$plugin = Plugin::getInstance();
$path = ProjectConfig::PATH_PLUGINS . '.garrison.settings';
$db = Craft::$app->getDb();
$run = bin2hex(random_bytes(3));
$password = 'Garrison-' . bin2hex(random_bytes(6));
$envFile = $root . '/.env';
$envBefore = file_get_contents($envFile);

// The web process reads project config from the database; read it the same way.
$stored = static function() use ($db, $path): array {
    // One row per leaf: `wafRules.0`, `wafRules.1`… Put the arrays back together.
    $out = [];
    foreach ((new craft\db\Query())->select(['path', 'value'])->from('{{%projectconfig}}')->where(['like', 'path', $path . '.%', false])->orderBy('path')->all($db) as $row) {
        craft\helpers\ArrayHelper::setValue($out, substr($row['path'], strlen($path) + 1), json_decode($row['value'], true));
    }

    return $out;
};
$before = $stored();
$cleanup = ['users' => [], 'marker' => null];

register_shutdown_function(function() use (&$cleanup, $envFile, $envBefore, $before, $path, $root) {
    file_put_contents($envFile, $envBefore);
    // In a fresh process: this one's project config predates the web process's saves, and set()
    // from here writes nothing — it compares against that stale copy. A script also never reaches
    // the end of a request, which is where Craft writes the YAML.
    $restore = sys_get_temp_dir() . '/garrison-restore-' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($restore, '<?php
require ' . var_export($root . '/bootstrap.php', true) . ';
$app = require CRAFT_VENDOR_PATH . "/craftcms/cms/bootstrap/console.php";
$pc = Craft::$app->getProjectConfig();
$pc->set(' . var_export($path, true) . ', ' . var_export($before === [] ? null : $before, true) . ', "Restore Garrison settings after security.php");
$pc->saveModifiedConfigData();
$pc->writeYamlFiles(true);
');
    exec('php ' . escapeshellarg($restore), $out, $code);
    unlink($restore);
    $code === 0 or print("  ! Could not restore Garrison's settings: " . implode("\n", $out) . "\n");
    foreach ($cleanup['users'] as $user) {
        Craft::$app->getElements()->deleteElement($user, true);
    }
    LoginAttemptRecord::deleteAll(['like', 'ipAddress', '192.0.2.%', false]);
    if (isset($cleanup['marker'])) {
        BlockedRequestRecord::deleteAll(['like', 'requestUri', $cleanup['marker']]);
    }
    foreach ($cleanup['users'] as $user) {
        LoginAttemptRecord::deleteAll(['username' => [$user->username, $user->email]]);
    }
});

function client(string $username, string $password): Closure
{
    $http = new Client(['base_uri' => 'http://localhost/', 'cookies' => new CookieJar(), 'http_errors' => false, 'allow_redirects' => false]);
    $json = ['Accept' => 'application/json'];
    $csrf = static fn() => (string)(json_decode((string)$http->get('index.php?p=actions/users/session-info', ['headers' => $json])->getBody(), true)['csrfTokenValue'] ?? '');
    $http->post('index.php?p=actions/users/login', ['headers' => $json, 'form_params' => ['loginName' => $username, 'password' => $password, 'CRAFT_CSRF_TOKEN' => $csrf()]])->getStatusCode() === 200
        or throw new RuntimeException("Could not sign in as $username");

    return static function(string $method, string $uri, array $params = []) use ($http, $json, $csrf) {
        return $method === 'GET'
            ? $http->get("index.php?p=admin/$uri")
            : $http->post("index.php?p=admin/actions/$uri", ['headers' => $json, 'form_params' => $params + ['CRAFT_CSRF_TOKEN' => $csrf()]]);
    };
}

$asAdmin = client('admin', 'claudepassword');

echo "\nSaving one screen keeps the others\n";

check('Login Protection, then Notifications: both are kept', function() use ($asAdmin, $stored, $run) {
    $a = $asAdmin('POST', 'garrison/settings/save', ['settings' => ['maxLoginAttempts' => '7', 'loginAttemptWindow' => '900']])->getStatusCode();
    $b = $asAdmin('POST', 'garrison/settings/save', ['settings' => ['enableNotifications' => '1', 'webhookUrl' => "https://hooks.example.com/$run"]])->getStatusCode();
    $s = $stored();

    return in_array($a, [200, 302], true) && in_array($b, [200, 302], true) && (int)($s['maxLoginAttempts'] ?? 0) === 7 && (int)($s['loginAttemptWindow'] ?? 0) === 900
        && ($s['webhookUrl'] ?? null) === "https://hooks.example.com/$run"
        ?: "statuses $a/$b, stored " . json_encode($s);
});

check('…and a third screen leaves both of them alone', function() use ($asAdmin, $stored) {
    // The WAF stays off: on, it inspects this script's own logins.
    $status = $asAdmin('POST', 'garrison/settings/save', ['settings' => ['enableWaf' => '0', 'wafRules' => ['xss']]])->getStatusCode();
    $s = $stored();

    return in_array($status, [200, 302], true) && (int)($s['maxLoginAttempts'] ?? 0) === 7 && !empty($s['enableNotifications']) && ($s['wafRules'] ?? null) === ['xss']
        ?: "status $status, stored " . json_encode($s);
});

check('a WAF screen with every rule unticked saves an empty list', function() use ($asAdmin, $stored) {
    // checkboxGroupField posts its empty hidden input.
    $status = $asAdmin('POST', 'garrison/settings/save', ['settings' => ['enableWaf' => '0', 'wafRules' => '']])->getStatusCode();

    // An empty list stores no rows at all; what matters is that nothing is left ticked.
    return in_array($status, [200, 302], true) && ($stored()['wafRules'] ?? []) === [] && (int)($stored()['maxLoginAttempts'] ?? 0) === 7 ?: "status $status, " . json_encode($stored()['wafRules'] ?? 'missing');
});

echo "\nWho can change them\n";

$manager = new User(['username' => "garrison-manager-$run", 'email' => "garrison-manager-$run@example.com", 'newPassword' => $password]);
Craft::$app->getElements()->saveElement($manager, false);
Craft::$app->getUsers()->activateUser($manager);
Craft::$app->getUserPermissions()->saveUserPermissions($manager->id, ['accesscp', 'accessplugin-garrison', 'garrison:accessplugin', 'garrison:managesettings']);
$cleanup['users'][] = $manager;
// Two failures against the manager's email from the harness's own address — under any lockout
// threshold — which signing in by username must clear.
foreach ([1, 2] as $_) {
    (new LoginAttemptRecord(['ipAddress' => '127.0.0.1', 'username' => $manager->email, 'successful' => false]))->save(false);
}
$asManager = client($manager->username, $password);

check('signing in by username clears failures typed as the account’s email', function() use ($manager) {
    $left = (int)LoginAttemptRecord::find()->where(['ipAddress' => '127.0.0.1', 'username' => $manager->email, 'successful' => false])->count();

    return $left === 0 ?: "$left left";
});

check('“Manage Garrison settings” sees the screen read-only, with no Save', function() use ($asManager) {
    $response = $asManager('GET', 'garrison/settings');
    $html = (string)$response->getBody();

    return $response->getStatusCode() === 200 && str_contains($html, 'Only an admin can change these settings.')
        && str_contains($html, '<fieldset disabled>') && !str_contains($html, 'id="main-form"')
        ?: 'status ' . $response->getStatusCode();
});

check('…and can’t save', function() use ($asManager, $stored) {
    $status = $asManager('POST', 'garrison/settings/save', ['settings' => ['maxLoginAttempts' => '99']])->getStatusCode();

    return $status === 403 && (int)($stored()['maxLoginAttempts'] ?? 0) === 7 ?: "status $status";
});

check('an admin sees the form', function() use ($asAdmin) {
    $html = (string)$asAdmin('GET', 'garrison/settings')->getBody();

    return str_contains($html, 'id="main-form"') && !str_contains($html, '<fieldset disabled>') ?: 'no form';
});

echo "\nThe WAF on a live site\n";

// Notifications off first: a blocked request would otherwise post to the webhook saved above.
$asAdmin('POST', 'garrison/settings/save', ['settings' => ['enableNotifications' => '0']]);
$asAdmin('POST', 'garrison/settings/save', ['settings' => ['enableWaf' => '1', 'wafRules' => ['sql-injection', 'xss', 'path-traversal', 'user-agent']]]);
$anon = new Client(['base_uri' => 'http://localhost/', 'http_errors' => false, 'headers' => ['User-Agent' => 'Mozilla/5.0 (garrison security.php)']]);
$marker = "garrison-waf-$run";

check('an injection in a front-end request is blocked', function() use ($anon, $marker) {
    $status = $anon->get('index.php?p=' . $marker . '&q=' . rawurlencode("x' OR '1'='1"))->getStatusCode();

    return $status === 403 ?: "status $status";
});

check('twenty ordinary sign-ins, each with a fresh CSRF token and a `--` in a field, all get through', function() use ($password, $manager) {
    $blocked = 0;
    for ($i = 0; $i < 20; $i++) {
        $http = new Client(['base_uri' => 'http://localhost/', 'cookies' => new CookieJar(), 'http_errors' => false, 'headers' => ['User-Agent' => 'Mozilla/5.0 (garrison security.php)', 'Accept' => 'application/json']]);
        $csrf = (string)(json_decode((string)$http->get('index.php?p=actions/users/session-info')->getBody(), true)['csrfTokenValue'] ?? '');
        $status = $http->post('index.php?p=actions/users/login', ['form_params' => [
            'loginName' => $manager->username, 'password' => $password, 'note' => 'Arrived on time -- great', 'CRAFT_CSRF_TOKEN' => $csrf,
        ]])->getStatusCode();
        $blocked += $status === 403 ? 1 : 0;
    }

    return $blocked === 0 ?: "$blocked of 20 blocked";
});

$asAdmin('POST', 'garrison/settings/save', ['settings' => ['enableWaf' => '0']]);
$cleanup['marker'] = $marker;

// allowAdminChanges off, for the web process only: Craft reads CRAFT_* env vars into general config.
$envOff = preg_replace('/^CRAFT_ALLOW_ADMIN_CHANGES=.*$/m', 'CRAFT_ALLOW_ADMIN_CHANGES=false', $envBefore, -1, $replaced);
file_put_contents($envFile, $replaced ? $envOff : rtrim($envBefore) . "\nCRAFT_ALLOW_ADMIN_CHANGES=false\n");

check('with allowAdminChanges off an admin can’t save either', function() use ($asAdmin, $stored) {
    $status = $asAdmin('POST', 'garrison/settings/save', ['settings' => ['maxLoginAttempts' => '42']])->getStatusCode();

    return $status === 403 && (int)($stored()['maxLoginAttempts'] ?? 0) === 7 ?: "status $status";
});

check('…and is told why', function() use ($asAdmin) {
    $html = (string)$asAdmin('GET', 'garrison/settings')->getBody();

    return str_contains($html, 'allowAdminChanges is off') && !str_contains($html, 'id="main-form"') ?: 'no notice';
});

file_put_contents($envFile, $envBefore);

echo "\nLogin lockout\n";

$shield = $plugin->shield;
$settings = $plugin->getSettings();
$settings->maxLoginAttempts = 3;
$settings->loginAttemptWindow = 600;
$settings->lockoutDuration = 120;
$cache = Craft::$app->getCache();
$lockKey = static fn(string $ip) => 'garrison:lockout:' . md5($ip);
$fresh = static function(string $ip) use ($cache, $lockKey): void {
    LoginAttemptRecord::deleteAll(['ipAddress' => $ip]);
    $cache->delete($lockKey($ip));
};
$ip = '192.0.2.' . random_int(1, 254);
$victim = "victim-$run";
$fresh($ip);

check('one good login from the same IP doesn’t reset a lockout on another account', function() use ($shield, $ip, $victim, $run) {
    $shield->recordLoginAttempt($ip, $victim, false);
    $shield->recordLoginAttempt($ip, $victim, false);
    $shield->recordLoginAttempt($ip, "attacker-$run", true, ["attacker-$run@example.com"]);
    $shield->recordLoginAttempt($ip, $victim, false);

    return $shield->isLockedOut($ip) ?: 'not locked out';
});

check('the account’s own good login clears its streak — under its email too', function() use ($shield, $ip, $victim, $fresh) {
    $fresh($ip);
    $shield->recordLoginAttempt($ip, $victim, false);
    $shield->recordLoginAttempt($ip, "$victim@example.com", false);
    $shield->recordLoginAttempt($ip, $victim, true, ["$victim@example.com"]);

    return $shield->getRecentFailedAttempts($ip) === 0 ?: 'failures left: ' . $shield->getRecentFailedAttempts($ip);
});

// Until 5.1.7 lockoutDuration was saved and shown but never read: a lockout lasted as long as
// the failures stayed inside loginAttemptWindow.

check('a lockout lasts lockoutDuration from the attempt that crossed the threshold', function() use ($shield, $ip, $victim, $fresh) {
    $fresh($ip);
    foreach ([1, 2, 3] as $_) {
        $shield->recordLoginAttempt($ip, $victim, false);
    }
    $end = $shield->getLockoutEnd($ip)?->getTimestamp();

    return $shield->isLockedOut($ip) && $end !== null && abs($end - (time() + 120)) <= 5
        ?: 'ends ' . var_export($end, true) . ', expected about ' . (time() + 120);
});

check('when it ends, the attempts that caused it no longer count', function() use ($shield, $ip, $victim, $cache, $lockKey) {
    // Wind the clock back: the failures were 200 seconds ago — still inside the 600-second window —
    // so the 120-second lockout they caused ended 80 seconds ago.
    LoginAttemptRecord::updateAll(['dateCreated' => craft\helpers\Db::prepareDateForDb(new DateTime('@' . (time() - 200)))], ['ipAddress' => $ip]);
    $cache->set($lockKey($ip), ['until' => time() - 80], 600);
    $unlocked = !$shield->isLockedOut($ip) && $shield->getRecentFailedAttempts($ip) === 0;
    $shield->recordLoginAttempt($ip, $victim, false);

    return $unlocked && !$shield->isLockedOut($ip) && $shield->getRecentFailedAttempts($ip) === 1
        ?: json_encode(['unlocked' => $unlocked, 'locked now' => $shield->isLockedOut($ip), 'counted' => $shield->getRecentFailedAttempts($ip)]);
});

check('…and the next streak locks it again', function() use ($shield, $ip, $victim) {
    $shield->recordLoginAttempt($ip, $victim, false);
    $shield->recordLoginAttempt($ip, $victim, false);

    return $shield->isLockedOut($ip) && $shield->getLockoutEnd($ip) !== null ?: 'not locked again';
});

check('with the lockout gone from the cache, failures in the window still lock', function() use ($shield, $ip, $victim, $fresh, $cache, $lockKey) {
    $fresh($ip);
    foreach ([1, 2, 3] as $_) {
        $shield->recordLoginAttempt($ip, $victim, false);
    }
    $cache->delete($lockKey($ip));

    return $shield->isLockedOut($ip) ?: 'not locked out';
});

$fresh($ip);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
