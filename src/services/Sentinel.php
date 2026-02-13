<?php

namespace justinholtweb\garrison\services;

use craft\base\Component;

class Sentinel extends Component
{
    /**
     * Log an audit event.
     * Implemented in Phase 2.
     */
    public function log(string $action, string $category, array $attributes = []): void
    {
        // Stub — implemented in Phase 2
    }
}
