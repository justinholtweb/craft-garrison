<?php

namespace justinholtweb\garrison\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\garrison\Plugin;

class SentinelController extends Controller
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
        return $this->renderTemplate('garrison/sentinel/_index', [
            'selectedSubnavItem' => 'sentinel',
        ]);
    }

    public function actionAuditLog(): \yii\web\Response
    {
        return $this->renderTemplate('garrison/sentinel/_audit-log', [
            'selectedSubnavItem' => 'sentinel',
        ]);
    }

    public function actionFileIntegrity(): \yii\web\Response
    {
        return $this->renderTemplate('garrison/sentinel/_file-integrity', [
            'selectedSubnavItem' => 'sentinel',
        ]);
    }
}
