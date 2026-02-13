<?php

namespace justinholtweb\garrison\services;

use craft\base\Component;

class Beacon extends Component
{
    /**
     * Send a notification across all configured channels.
     * Implemented in Phase 4.
     */
    public function notify(string $type, string $subject, string $body, array $data = []): void
    {
        // Stub — implemented in Phase 4
    }
}
