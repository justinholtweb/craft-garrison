<?php

namespace justinholtweb\garrison\enums;

enum CheckCategory: string
{
    case Headers = 'headers';
    case Permissions = 'permissions';
    case Config = 'config';
    case Updates = 'updates';
    case Encryption = 'encryption';
    case Database = 'database';
    case Authentication = 'authentication';

    public function label(): string
    {
        return match ($this) {
            self::Headers => 'HTTP Headers',
            self::Permissions => 'File Permissions',
            self::Config => 'Configuration',
            self::Updates => 'Updates',
            self::Encryption => 'Encryption & SSL',
            self::Database => 'Database',
            self::Authentication => 'Authentication',
        };
    }
}
