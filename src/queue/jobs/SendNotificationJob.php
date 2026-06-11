<?php

namespace justinholtweb\garrison\queue\jobs;

use craft\queue\BaseJob;
use justinholtweb\garrison\Plugin;

class SendNotificationJob extends BaseJob
{
    public string $type = '';
    public string $subject = '';
    public string $body = '';
    public array $data = [];

    public function execute($queue): void
    {
        Plugin::getInstance()->beacon->dispatch($this->subject, $this->body, $this->data);
    }

    protected function defaultDescription(): ?string
    {
        return 'Sending Garrison notification';
    }
}
