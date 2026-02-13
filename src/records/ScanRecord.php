<?php

namespace justinholtweb\garrison\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $siteId
 * @property string $status
 * @property int $riskScore
 * @property int $totalChecks
 * @property int $passedChecks
 * @property int $warningChecks
 * @property int $failedChecks
 * @property int $criticalChecks
 * @property float $duration
 * @property string $triggeredBy
 * @property int|null $userId
 */
class ScanRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%garrison_scans}}';
    }
}
