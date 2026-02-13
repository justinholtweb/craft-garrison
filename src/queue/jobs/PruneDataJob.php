<?php

namespace justinholtweb\garrison\queue\jobs;

use Craft;
use craft\queue\BaseJob;
use justinholtweb\garrison\Plugin;
use justinholtweb\garrison\records\BlockedRequestRecord;
use justinholtweb\garrison\records\LoginAttemptRecord;

class PruneDataJob extends BaseJob
{
    public function execute($queue): void
    {
        $settings = Plugin::getInstance()->getSettings();

        // Prune old blocked requests
        $cutoff = (new \DateTime())
            ->modify("-{$settings->pruneBlockedRequestsDays} days")
            ->format('Y-m-d H:i:s');

        BlockedRequestRecord::deleteAll(['<', 'dateCreated', $cutoff]);

        // Prune old login attempts
        $cutoff = (new \DateTime())
            ->modify("-{$settings->pruneLoginAttemptsDays} days")
            ->format('Y-m-d H:i:s');

        LoginAttemptRecord::deleteAll(['<', 'dateCreated', $cutoff]);
    }

    protected function defaultDescription(): ?string
    {
        return 'Pruning old Garrison data';
    }
}
