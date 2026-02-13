<?php

namespace justinholtweb\garrison\services;

use craft\base\Component;

class Shield extends Component
{
    /**
     * Check an incoming request against all shield rules.
     * Called from Application::EVENT_BEFORE_REQUEST.
     *
     * Check order (cheapest → most expensive):
     * 1. IP blocklist
     * 2. Lockout check
     * 3. IP allowlist
     * 4. Geo-blocking (Pro)
     * 5. Rate limiting
     * 6. WAF rules (Pro)
     *
     * Implemented in Phase 3.
     */
    public function handleRequest(): void
    {
        // Stub — implemented in Phase 3
    }
}
