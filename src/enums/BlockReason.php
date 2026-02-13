<?php

namespace justinholtweb\garrison\enums;

enum BlockReason: string
{
    case RateLimit = 'rateLimit';
    case IpBlocked = 'ipBlocked';
    case GeoBlocked = 'geoBlocked';
    case WafRule = 'wafRule';
    case LoginLockout = 'loginLockout';
    case HttpBasicAuth = 'httpBasicAuth';

    public function label(): string
    {
        return match ($this) {
            self::RateLimit => 'Rate Limited',
            self::IpBlocked => 'IP Blocked',
            self::GeoBlocked => 'Geo-Blocked',
            self::WafRule => 'WAF Rule',
            self::LoginLockout => 'Login Lockout',
            self::HttpBasicAuth => 'HTTP Basic Auth',
        };
    }
}
