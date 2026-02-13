<?php

namespace justinholtweb\garrison\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property string $filePath
 * @property string $fileHash
 * @property int $fileSize
 * @property string $filePermissions
 * @property string $status
 * @property string|null $lastChecked
 */
class FileBaselineRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%garrison_file_baselines}}';
    }
}
