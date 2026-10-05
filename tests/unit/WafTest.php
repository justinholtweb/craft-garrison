<?php

namespace justinholtweb\garrison\tests\unit;

use Codeception\Test\Unit;
use justinholtweb\garrison\services\Shield;

/**
 * Covers Shield's WAF signature matching against a request stub, so the regex
 * rules can be exercised without booting Craft.
 */
class WafTest extends Unit
{
    private const ALL_RULES = ['sql-injection', 'xss', 'path-traversal', 'user-agent'];

    /**
     * @param array<string, string> $query
     * @param array<string, mixed> $params parsed body fields, as Craft's getBodyParams() returns them
     */
    private function request(array $query = [], string $body = '', string $url = '/', string $ua = 'Mozilla/5.0', array $params = []): object
    {
        return new class($query, $body, $url, $ua, $params) {
            public string $csrfParam = 'CRAFT_CSRF_TOKEN';

            public function __construct(
                private array $query,
                private string $body,
                private string $url,
                private string $ua,
                private array $params,
            ) {
            }

            public function getQueryParams(): array
            {
                return $this->query;
            }

            public function getBodyParams(): array
            {
                return $this->params;
            }

            public function getRawBody(): string
            {
                return $this->body !== '' ? $this->body : http_build_query($this->params);
            }

            public function getUrl(): string
            {
                return $this->url;
            }

            public function getUserAgent(): ?string
            {
                return $this->ua;
            }
        };
    }

    /**
     * @param array<string, mixed> $params
     */
    private function post(array $params): object
    {
        return $this->request([], '', '/index.php?p=actions/users/login', 'Mozilla/5.0', $params);
    }

    public function testCleanRequestPasses(): void
    {
        $shield = new Shield();
        $this->assertNull($shield->matchWafRules($this->request(['q' => 'hello world']), self::ALL_RULES));
    }

    public function testSqlInjectionInQuery(): void
    {
        $shield = new Shield();
        $request = $this->request(['id' => "1 UNION SELECT password FROM users"]);
        $this->assertSame('sql-injection', $shield->matchWafRules($request, self::ALL_RULES));
    }

    public function testXssInBody(): void
    {
        $shield = new Shield();
        $request = $this->request([], '<script>alert(1)</script>');
        $this->assertSame('xss', $shield->matchWafRules($request, self::ALL_RULES));
    }

    public function testPathTraversalInUrl(): void
    {
        $shield = new Shield();
        $request = $this->request([], '', '/?file=../../etc/passwd');
        $this->assertSame('path-traversal', $shield->matchWafRules($request, self::ALL_RULES));
    }

    public function testMaliciousUserAgent(): void
    {
        $shield = new Shield();
        $request = $this->request([], '', '/', 'sqlmap/1.5');
        $this->assertSame('user-agent', $shield->matchWafRules($request, self::ALL_RULES));
    }

    public function testEmptyUserAgentFlagged(): void
    {
        $shield = new Shield();
        $this->assertSame('user-agent', $shield->matchWafRules($this->request([], '', '/', ''), self::ALL_RULES));
    }

    public function testDisabledRuleIsNotMatched(): void
    {
        $shield = new Shield();
        $request = $this->request(['id' => "1 UNION SELECT password FROM users"]);
        // Only the XSS rule is enabled, so a SQL-injection payload must pass.
        $this->assertNull($shield->matchWafRules($request, ['xss']));
    }

    // Until 5.1.7 the WAF matched the raw body — CSRF token included — against patterns that
    // fired on `--` and on "select … from" in plain English.

    public function testCsrfTokenIsNotInspected(): void
    {
        $shield = new Shield();
        $request = $this->post(['CRAFT_CSRF_TOKEN' => 'aB3--x_Q9' . str_repeat('k', 40) . "' OR '1'='1", 'loginName' => 'admin']);
        $this->assertNull($shield->matchWafRules($request, self::ALL_RULES));
    }

    public function testPasswordFieldsAreNotInspected(): void
    {
        $shield = new Shield();
        $request = $this->post(['loginName' => 'admin', 'password' => "x' OR '1'='1' --", 'user' => ['newPassword' => '<script>']]);
        $this->assertNull($shield->matchWafRules($request, self::ALL_RULES));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ordinaryText(): array
    {
        return [
            'double hyphen as a dash' => ['Arrived on time -- great service'],
            'select … from in prose' => ['Select a size from the list below'],
            'quoted hashtag' => ['Voted "#1 in town" two years running'],
            'apostrophes and or' => ["It's fine or it isn't, that's the question"],
            'insert into in prose' => ['Insert into the slot and turn'],
            'sleep in prose' => ['Sleep (8 hours) matters'],
        ];
    }

    /**
     * @dataProvider ordinaryText
     */
    public function testOrdinaryTextPasses(string $text): void
    {
        $shield = new Shield();
        $this->assertNull($shield->matchWafRules($this->post(['message' => $text]), self::ALL_RULES));
        $this->assertNull($shield->matchWafRules($this->request(['q' => $text]), self::ALL_RULES));
    }

    public function testValuesAreMatchedOneAtATime(): void
    {
        // A quote ending one field and `--` starting the next is not an injection.
        $shield = new Shield();
        $request = $this->post(['quote' => 'She said "yes"', 'signature' => '-- Sam']);
        $this->assertNull($shield->matchWafRules($request, self::ALL_RULES));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function injections(): array
    {
        return [
            'comment after a quote' => ["admin'--"],
            'block comment after a quote' => ["admin'/*"],
            'tautology' => ["x' OR '1'='1"],
            'numeric tautology' => ['1 OR 1=1'],
            'stacked statement' => ["x'; DROP TABLE users"],
            'drop table' => ['1; drop table users'],
            'union with comment padding' => ['1 UNION/**/SELECT password FROM users'],
            'time-based' => ['1 AND SLEEP(5)'],
            'schema probe' => ['1 AND 1=(SELECT 1 FROM information_schema.tables)'],
            'insert … values' => ["x'); INSERT INTO users VALUES ('a')"],
        ];
    }

    /**
     * @dataProvider injections
     */
    public function testInjectionInABodyFieldIsCaught(string $payload): void
    {
        $shield = new Shield();
        $this->assertSame('sql-injection', $shield->matchWafRules($this->post(['search' => $payload]), self::ALL_RULES));
    }

    public function testUnparsedBodyIsStillInspectedRaw(): void
    {
        // XML, plain text — nothing Craft turns into fields.
        $shield = new Shield();
        $request = $this->request([], "<order><id>1 UNION SELECT password FROM users</id></order>");
        $this->assertSame('sql-injection', $shield->matchWafRules($request, ['sql-injection']));
    }
}
