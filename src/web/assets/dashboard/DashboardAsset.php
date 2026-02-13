<?php

namespace justinholtweb\garrison\web\assets\dashboard;

use craft\web\AssetBundle;
use justinholtweb\garrison\web\assets\cp\CpAsset;

class DashboardAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->depends = [CpAsset::class];
        $this->css = [];
        $this->js = [];

        parent::init();
    }
}
