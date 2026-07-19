<?php

namespace justinholtweb\garrison\tests\unit;

use Codeception\Test\Unit;
use justinholtweb\garrison\models\RiskScore;

/**
 * Covers the RiskScore banding: label, color, and grade across every tier
 * boundary (<=10, <=30, <=50, <=70, and above).
 */
class RiskScoreTest extends Unit
{
    private function scoreOf(int $score): RiskScore
    {
        $risk = new RiskScore();
        $risk->score = $score;

        return $risk;
    }

    public function testDefaultScoreIsExcellent(): void
    {
        $risk = new RiskScore();
        $this->assertSame(0, $risk->score);
        $this->assertSame('Excellent', $risk->getLabel());
        $this->assertSame('A', $risk->getGrade());
    }

    /**
     * @dataProvider bandProvider
     */
    public function testLabelGradeAndColorByBand(int $score, string $label, string $grade, string $color): void
    {
        $risk = $this->scoreOf($score);
        $this->assertSame($label, $risk->getLabel(), "label at score $score");
        $this->assertSame($grade, $risk->getGrade(), "grade at score $score");
        $this->assertSame($color, $risk->getColor(), "color at score $score");
    }

    /**
     * @return array<string, array{int, string, string, string}>
     */
    public function bandProvider(): array
    {
        return [
            // score, label, grade, color
            'zero' => [0, 'Excellent', 'A', 'green'],
            'excellent edge' => [10, 'Excellent', 'A', 'green'],
            'good lower' => [11, 'Good', 'B', 'blue'],
            'good edge' => [30, 'Good', 'B', 'blue'],
            'fair lower' => [31, 'Fair', 'C', 'orange'],
            'fair edge' => [50, 'Fair', 'C', 'orange'],
            'poor lower' => [51, 'Poor', 'D', 'red'],
            'poor edge' => [70, 'Poor', 'D', 'red'],
            'critical lower' => [71, 'Critical', 'F', 'red'],
            'critical capped' => [100, 'Critical', 'F', 'red'],
        ];
    }
}
