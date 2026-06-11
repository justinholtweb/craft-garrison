<?php

namespace justinholtweb\garrison\controllers;

use craft\web\Controller;
use justinholtweb\garrison\models\Edition;
use justinholtweb\garrison\Plugin;
use yii\web\Response;

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

    public function actionIndex(): Response
    {
        $plugin = Plugin::getInstance();
        $isPro = Edition::isPro();

        return $this->renderTemplate('garrison/dashboard/_index', [
            'lastScan' => $plugin->scanner->getLastScan(),
            'summary' => $plugin->dashboard->getSummary(),
            'riskTrend' => $isPro ? $plugin->dashboard->getRiskTrend(30) : [],
            'threatStats' => $isPro ? $plugin->dashboard->getThreatStats(30) : [],
            'isPro' => $isPro,
            'plugin' => $plugin,
            'selectedSubnavItem' => 'dashboard',
        ]);
    }
}
