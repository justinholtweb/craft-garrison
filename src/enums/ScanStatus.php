<?php

namespace justinholtweb\garrison\enums;

enum ScanStatus: string
{
    case Passed = 'passed';
    case Warning = 'warning';
    case Failed = 'failed';
    case Critical = 'critical';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Passed => 'Passed',
            self::Warning => 'Warning',
            self::Failed => 'Failed',
            self::Critical => 'Critical',
            self::Skipped => 'Skipped',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Passed => 'green',
            self::Warning => 'orange',
            self::Failed => 'red',
            self::Critical => 'red',
            self::Skipped => 'grey',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Passed => 'check',
            self::Warning => 'alert',
            self::Failed => 'remove',
            self::Critical => 'remove',
            self::Skipped => 'disabled',
        };
    }
}
