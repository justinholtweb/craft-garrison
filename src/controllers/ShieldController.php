<?php

namespace justinholtweb\garrison\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\garrison\Plugin;

class ShieldController extends Controller
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
        return $this->renderTemplate('garrison/shield/_index', [
            'settings' => Plugin::getInstance()->getSettings(),
            'selectedSubnavItem' => 'shield',
        ]);
    }

    public function actionIpManagement(): \yii\web\Response
    {
        return $this->renderTemplate('garrison/shield/_ip-management', [
            'selectedSubnavItem' => 'shield',
        ]);
    }

    public function actionRateLimits(): \yii\web\Response
    {
        return $this->renderTemplate('garrison/shield/_rate-limits', [
            'settings' => Plugin::getInstance()->getSettings(),
            'selectedSubnavItem' => 'shield',
        ]);
    }

    public function actionLoginProtection(): \yii\web\Response
    {
        return $this->renderTemplate('garrison/shield/_login-protection', [
            'settings' => Plugin::getInstance()->getSettings(),
            'selectedSubnavItem' => 'shield',
        ]);
    }

    public function actionWaf(): \yii\web\Response
    {
        return $this->renderTemplate('garrison/shield/_waf', [
            'settings' => Plugin::getInstance()->getSettings(),
            'selectedSubnavItem' => 'shield',
        ]);
    }
}
