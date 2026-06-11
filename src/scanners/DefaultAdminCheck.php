<?php

namespace justinholtweb\garrison\scanners;

use craft\elements\User;
use justinholtweb\garrison\enums\CheckCategory;
use justinholtweb\garrison\enums\Severity;
use justinholtweb\garrison\models\ScanResult;

/**
 * Flags predictable admin usernames such as "admin". A known username removes
 * half of the work in a credential-stuffing or brute-force attack.
 */
class DefaultAdminCheck extends BaseScannerCheck
{
    private const PREDICTABLE = ['admin', 'administrator', 'root', 'craft', 'test'];

    public function getHandle(): string
    {
        return 'default-admin-username';
    }

    public function getName(): string
    {
        return 'Admin Usernames';
    }

    public function getCategory(): CheckCategory
    {
        return CheckCategory::Authentication;
    }

    public function getDefaultSeverity(): Severity
    {
        return Severity::Medium;
    }

    protected function performCheck(?int $siteId = null): ScanResult
    {
        $found = [];

        foreach (self::PREDICTABLE as $username) {
            $exists = User::find()
                ->admin(true)
                ->username($username)
                ->status(null)
                ->exists();

            if ($exists) {
                $found[] = $username;
            }
        }

        if (empty($found)) {
            return $this->pass('No admin accounts use a predictable username.');
        }

        return $this->warning(
            sprintf('Admin account(s) use a predictable username: %s.', implode(', ', $found)),
            Severity::Medium,
            'Rename predictable admin usernames to something non-obvious to slow down brute-force attacks.',
            ['usernames' => $found],
        );
    }
}
