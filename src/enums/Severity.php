<?php

namespace justinholtweb\garrison\enums;

enum Severity: string
{
    case Info = 'info';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::Info => 0,
            self::Low => 3,
            self::Medium => 8,
            self::High => 15,
            self::Critical => 25,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Info => 'blue',
            self::Low => 'grey',
            self::Medium => 'orange',
            self::High => 'red',
            self::Critical => 'red',
        };
    }
}
