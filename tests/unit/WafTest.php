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
     */
    private function request(array $query = [], string $body = '', string $url = '/', string $ua = 'Mozilla/5.0'): object
    {
        return new class($query, $body, $url, $ua) {
            public function __construct(
                private array $query,
                private string $body,
                private string $url,
                private string $ua,
            ) {
            }

            public function getQueryParams(): array
            {
                return $this->query;
            }

            public function getRawBody(): string
            {
                return $this->body;
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
}
