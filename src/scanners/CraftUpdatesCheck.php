<?php

namespace justinholtweb\garrison\scanners;

use Craft;
use justinholtweb\garrison\enums\CheckCategory;
use justinholtweb\garrison\enums\Severity;
use justinholtweb\garrison\models\ScanResult;

/**
 * Flags pending Craft / plugin updates. Uses cached update info only so the
 * scan never makes a blocking network request; if no cache is available it
 * skips rather than guessing.
 */
class CraftUpdatesCheck extends BaseScannerCheck
{
    public function getHandle(): string
    {
        return 'craft-updates';
    }

    public function getName(): string
    {
        return 'Software Updates';
    }

    public function getCategory(): CheckCategory
    {
        return CheckCategory::Updates;
    }

    public function getDefaultSeverity(): Severity
    {
        return Severity::High;
    }

    protected function performCheck(?int $siteId = null): ScanResult
    {
        $updates = Craft::$app->getUpdates();

        if (!$updates->getIsUpdateInfoCached()) {
            return $this->skip('Update information is not cached. Visit Utilities → Updates to refresh.');
        }

        try {
            $allUpdates = $updates->getUpdates();
        } catch (\Throwable $e) {
            return $this->skip('Could not read update information.');
        }

        if ($updates->getIsCriticalUpdateAvailable()) {
            return $this->fail(
                'A critical security update is available.',
                Severity::Critical,
                'Apply the available critical update as soon as possible from Utilities → Updates.',
            );
        }

        $pending = [];
        if ($allUpdates->cms->getHasReleases()) {
            $pending[] = 'Craft CMS';
        }
        foreach ($allUpdates->plugins as $handle => $pluginUpdate) {
            if ($pluginUpdate->getHasReleases()) {
                $pending[] = $handle;
            }
        }

        if (empty($pending)) {
            return $this->pass('Craft and all plugins are up to date.');
        }

        return $this->warning(
            sprintf('%d update(s) available: %s.', count($pending), implode(', ', $pending)),
            Severity::Medium,
            'Review and apply available updates from Utilities → Updates.',
            ['pending' => $pending],
        );
    }
}
