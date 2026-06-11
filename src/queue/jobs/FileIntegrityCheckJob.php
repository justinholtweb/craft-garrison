<?php

namespace justinholtweb\garrison\queue\jobs;

use craft\queue\BaseJob;
use justinholtweb\garrison\Plugin;

class FileIntegrityCheckJob extends BaseJob
{
    public function execute($queue): void
    {
        Plugin::getInstance()->beacon->checkIntegrity();
    }

    protected function defaultDescription(): ?string
    {
        return 'Running Garrison file integrity check';
    }
}
