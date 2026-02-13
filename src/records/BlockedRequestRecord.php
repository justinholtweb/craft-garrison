<?php

namespace justinholtweb\garrison\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $ipAddress
 * @property string $reason
 * @property array|null $details
 * @property string|null $requestUri
 * @property string|null $requestMethod
 * @property string|null $userAgent
 * @property string|null $countryCode
 */
class BlockedRequestRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%garrison_blocked_requests}}';
    }
}
