<?php

namespace justinholtweb\garrison\tests\unit;

use Codeception\Test\Unit;
use justinholtweb\garrison\enums\Severity;

/**
 * Covers the Severity enum: backed values, human labels, risk weights, and
 * colors. The weights feed ScanReport::calculateRiskScore(), so pinning them
 * here guards against silent scoring drift.
 */
class SeverityTest extends Unit
{
    public function testBackedValues(): void
    {
        $this->assertSame('info', Severity::Info->value);
        $this->assertSame('low', Severity::Low->value);
        $this->assertSame('medium', Severity::Medium->value);
        $this->assertSame('high', Severity::High->value);
        $this->assertSame('critical', Severity::Critical->value);
    }

    public function testFromValueRoundTrips(): void
    {
        $this->assertSame(Severity::Critical, Severity::from('critical'));
        $this->assertNull(Severity::tryFrom('nonexistent'));
    }

    public function testWeightsAscendBySeverity(): void
    {
        $this->assertSame(0, Severity::Info->weight());
        $this->assertSame(3, Severity::Low->weight());
        $this->assertSame(8, Severity::Medium->weight());
        $this->assertSame(15, Severity::High->weight());
        $this->assertSame(25, Severity::Critical->weight());
    }

    public function testWeightsAreStrictlyMonotonic(): void
    {
        $order = [Severity::Info, Severity::Low, Severity::Medium, Severity::High, Severity::Critical];
        for ($i = 1; $i < count($order); $i++) {
            $this->assertGreaterThan(
                $order[$i - 1]->weight(),
                $order[$i]->weight(),
                "{$order[$i]->name} should weigh more than {$order[$i - 1]->name}"
            );
        }
    }

    public function testLabels(): void
    {
        $this->assertSame('Info', Severity::Info->label());
        $this->assertSame('Low', Severity::Low->label());
        $this->assertSame('Medium', Severity::Medium->label());
        $this->assertSame('High', Severity::High->label());
        $this->assertSame('Critical', Severity::Critical->label());
    }

    public function testColors(): void
    {
        $this->assertSame('blue', Severity::Info->color());
        $this->assertSame('grey', Severity::Low->color());
        $this->assertSame('orange', Severity::Medium->color());
        $this->assertSame('red', Severity::High->color());
        $this->assertSame('red', Severity::Critical->color());
    }
}
