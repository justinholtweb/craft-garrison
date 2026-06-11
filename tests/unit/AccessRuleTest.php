<?php

namespace justinholtweb\garrison\tests\unit;

use Codeception\Test\Unit;
use justinholtweb\garrison\models\AccessRule;

/**
 * Covers the IP-matching logic behind Shield's allow/block rules: the part most
 * prone to off-by-one CIDR-mask bugs.
 */
class AccessRuleTest extends Unit
{
    private function rule(string $pattern): AccessRule
    {
        $rule = new AccessRule();
        $rule->ipPattern = $pattern;

        return $rule;
    }

    public function testExactMatch(): void
    {
        $this->assertTrue($this->rule('203.0.113.5')->matchesIp('203.0.113.5'));
        $this->assertFalse($this->rule('203.0.113.5')->matchesIp('203.0.113.6'));
    }

    public function testCidrSlash24(): void
    {
        $rule = $this->rule('203.0.113.0/24');
        $this->assertTrue($rule->matchesIp('203.0.113.0'));
        $this->assertTrue($rule->matchesIp('203.0.113.255'));
        $this->assertFalse($rule->matchesIp('203.0.114.0'));
    }

    public function testCidrSlash8(): void
    {
        $rule = $this->rule('10.0.0.0/8');
        $this->assertTrue($rule->matchesIp('10.1.2.3'));
        $this->assertTrue($rule->matchesIp('10.255.255.255'));
        $this->assertFalse($rule->matchesIp('11.0.0.1'));
    }

    public function testCidrSlash32IsSingleHost(): void
    {
        $rule = $this->rule('192.168.1.50/32');
        $this->assertTrue($rule->matchesIp('192.168.1.50'));
        $this->assertFalse($rule->matchesIp('192.168.1.51'));
    }

    public function testWildcard(): void
    {
        $rule = $this->rule('203.0.113.*');
        $this->assertTrue($rule->matchesIp('203.0.113.1'));
        $this->assertTrue($rule->matchesIp('203.0.113.200'));
        $this->assertFalse($rule->matchesIp('203.0.114.1'));
    }

    public function testInvalidIpDoesNotMatch(): void
    {
        $this->assertFalse($this->rule('10.0.0.0/8')->matchesIp('not-an-ip'));
    }
}
