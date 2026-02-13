<?php

namespace justinholtweb\garrison\queue\jobs;

use craft\queue\BaseJob;

class SendNotificationJob extends BaseJob
{
    public string $type = '';
    public string $subject = '';
    public string $body = '';
    public array $data = [];

    public function execute($queue): void
    {
        // Implemented in Phase 4
    }

    protected function defaultDescription(): ?string
    {
        return 'Sending Garrison notification';
    }
}
