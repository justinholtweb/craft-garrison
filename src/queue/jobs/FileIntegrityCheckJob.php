<?php

namespace justinholtweb\garrison\queue\jobs;

use craft\queue\BaseJob;

class FileIntegrityCheckJob extends BaseJob
{
    public function execute($queue): void
    {
        // Implemented in Phase 4
    }

    protected function defaultDescription(): ?string
    {
        return 'Running Garrison file integrity check';
    }
}
