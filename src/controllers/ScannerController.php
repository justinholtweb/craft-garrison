<?php

namespace justinholtweb\garrison\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\garrison\Plugin;

class ScannerController extends Controller
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

        return $this->renderTemplate('garrison/scanner/_index', [
            'lastScan' => $lastScan,
            'checks' => Plugin::getInstance()->scanner->getChecks(),
            'selectedSubnavItem' => 'scanner',
        ]);
    }

    public function actionRun(): \yii\web\Response
    {
        $this->requirePostRequest();
        $this->requirePermission('garrison:runScans');

        $siteId = Craft::$app->getRequest()->getBodyParam('siteId');
        $userId = Craft::$app->getUser()->getId();

        $report = Plugin::getInstance()->scanner->runScan(
            $siteId ? (int) $siteId : null,
            'manual',
            $userId,
        );

        Craft::$app->getSession()->setNotice(
            Craft::t('garrison', 'Security scan complete. Risk score: {score}', [
                'score' => $report->riskScore,
            ])
        );

        return $this->redirect('garrison/scanner/results/' . $report->id);
    }

    public function actionResults(int $scanId): \yii\web\Response
    {
        $report = Plugin::getInstance()->scanner->getScanById($scanId);

        if (!$report) {
            throw new \yii\web\NotFoundHttpException('Scan not found.');
        }

        return $this->renderTemplate('garrison/scanner/_results', [
            'report' => $report,
            'selectedSubnavItem' => 'scanner',
        ]);
    }

    public function actionHistory(): \yii\web\Response
    {
        $scans = Plugin::getInstance()->scanner->getScanHistory();

        return $this->renderTemplate('garrison/scanner/_history', [
            'scans' => $scans,
            'selectedSubnavItem' => 'scanner',
        ]);
    }
}
