<?php

namespace justinholtweb\garrison\enums;

enum NotificationChannel: string
{
    case Email = 'email';
    case Slack = 'slack';
    case Discord = 'discord';
    case Webhook = 'webhook';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Slack => 'Slack',
            self::Discord => 'Discord',
            self::Webhook => 'Webhook',
        };
    }
}
