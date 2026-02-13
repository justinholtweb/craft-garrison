<?php

namespace justinholtweb\garrison\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $scanId
 * @property string $checkHandle
 * @property string $checkName
 * @property string $category
 * @property string $status
 * @property string $severity
 * @property string|null $message
 * @property array|null $details
 * @property string|null $remediation
 */
class ScanResultRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%garrison_scan_results}}';
    }
}
