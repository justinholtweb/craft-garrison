<?php

namespace justinholtweb\garrison\models;

use craft\base\Model;

class AccessRule extends Model
{
    public ?int $id = null;
    public string $type = 'block'; // allow or block
    public string $scope = 'all'; // cp, frontend, all
    public ?string $ipPattern = null;
    public ?string $countryCode = null;
    public ?string $label = null;
    public bool $enabled = true;
    public ?\DateTime $expiresAt = null;
    public ?int $createdBy = null;

    public function defineRules(): array
    {
        return [
            [['type'], 'in', 'range' => ['allow', 'block']],
            [['scope'], 'in', 'range' => ['cp', 'frontend', 'all']],
            [['ipPattern'], 'required'],
            [['ipPattern'], 'string', 'max' => 100],
            [['countryCode'], 'string', 'max' => 2],
            [['label'], 'string', 'max' => 255],
            [['enabled'], 'boolean'],
        ];
    }

    public function matchesIp(string $ip): bool
    {
        $pattern = $this->ipPattern;

        // Exact match
        if ($pattern === $ip) {
            return true;
        }

        // CIDR match
        if (str_contains($pattern, '/')) {
            return $this->matchesCidr($ip, $pattern);
        }

        // Wildcard match
        if (str_contains($pattern, '*')) {
            $regex = '/^' . str_replace(['*', '.'], ['[0-9]+', '\\.'], $pattern) . '$/';
            return (bool) preg_match($regex, $ip);
        }

        return false;
    }

    private function matchesCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);

        if ($ip === false || $subnet === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);

        return ($ip & $mask) === ($subnet & $mask);
    }
}
