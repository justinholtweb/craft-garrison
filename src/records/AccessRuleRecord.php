<?php

namespace justinholtweb\garrison\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $type
 * @property string $scope
 * @property string $ipPattern
 * @property string|null $countryCode
 * @property string|null $label
 * @property bool $enabled
 * @property string|null $expiresAt
 * @property int|null $createdBy
 */
class AccessRuleRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%garrison_access_rules}}';
    }
}
