<?php

namespace justinholtweb\garrison\queue\jobs;

use craft\queue\BaseJob;
use justinholtweb\garrison\Plugin;

class RunScanJob extends BaseJob
{
    public ?int $siteId = null;

    public function execute($queue): void
    {
        Plugin::getInstance()->scanner->runScan(
            $this->siteId,
            'scheduled',
        );
    }

    protected function defaultDescription(): ?string
    {
        return 'Running Garrison security scan';
    }
}
