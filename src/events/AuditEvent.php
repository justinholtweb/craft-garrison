<?php

namespace justinholtweb\garrison\events;

use yii\base\Event;

class AuditEvent extends Event
{
    public string $action;
    public string $category;
    public ?int $userId = null;
    public ?string $targetType = null;
    public ?int $targetId = null;
    public ?array $details = null;
}
