<?php

namespace justinholtweb\garrison\events;

use yii\base\Event;

class ThreatDetectedEvent extends Event
{
    public string $type;
    public string $ipAddress;
    public string $reason;
    public ?array $details = null;
}
