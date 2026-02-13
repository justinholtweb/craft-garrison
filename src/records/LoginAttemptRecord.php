<?php

namespace justinholtweb\garrison\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $ipAddress
 * @property string|null $username
 * @property bool $successful
 */
class LoginAttemptRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%garrison_login_attempts}}';
    }
}
