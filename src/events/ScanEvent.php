<?php

namespace justinholtweb\garrison\events;

use justinholtweb\garrison\models\ScanReport;
use yii\base\Event;

class ScanEvent extends Event
{
    public ScanReport $report;
}
