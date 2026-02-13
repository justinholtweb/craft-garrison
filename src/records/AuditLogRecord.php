<?php

namespace justinholtweb\garrison\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int|null $siteId
 * @property int|null $userId
 * @property string|null $userName
 * @property string $action
 * @property string $category
 * @property string|null $targetType
 * @property int|null $targetId
 * @property string|null $targetTitle
 * @property array|null $details
 * @property string|null $ipAddress
 * @property string|null $userAgent
 */
class AuditLogRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%garrison_audit_log}}';
    }
}
