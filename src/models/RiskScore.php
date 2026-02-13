<?php

namespace justinholtweb\garrison\models;

use craft\base\Model;

class RiskScore extends Model
{
    public int $score = 0;

    public function getLabel(): string
    {
        return match (true) {
            $this->score <= 10 => 'Excellent',
            $this->score <= 30 => 'Good',
            $this->score <= 50 => 'Fair',
            $this->score <= 70 => 'Poor',
            default => 'Critical',
        };
    }

    public function getColor(): string
    {
        return match (true) {
            $this->score <= 10 => 'green',
            $this->score <= 30 => 'blue',
            $this->score <= 50 => 'orange',
            $this->score <= 70 => 'red',
            default => 'red',
        };
    }

    public function getGrade(): string
    {
        return match (true) {
            $this->score <= 10 => 'A',
            $this->score <= 30 => 'B',
            $this->score <= 50 => 'C',
            $this->score <= 70 => 'D',
            default => 'F',
        };
    }
}
