<?php

namespace justinholtweb\garrison\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\garrison\Plugin;

class DashboardController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('garrison:accessPlugin');

        return true;
    }

    public function actionIndex(): \yii\web\Response
    {
        $lastScan = Plugin::getInstance()->scanner->getLastScan();

        return $this->renderTemplate('garrison/dashboard/_index', [
            'lastScan' => $lastScan,
            'plugin' => Plugin::getInstance(),
            'selectedSubnavItem' => 'dashboard',
        ]);
    }
}
