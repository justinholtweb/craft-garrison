<?php

namespace justinholtweb\garrison\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\garrison\models\Edition;
use justinholtweb\garrison\Plugin;
use yii\web\Response;

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

    public function actionIndex(): Response
    {
        $plugin = Plugin::getInstance();

        return $this->renderTemplate('garrison/sentinel/_index', [
            'recentLog' => $plugin->sentinel->getAuditLog([], 10),
            'baselineCount' => Edition::isPro() ? $plugin->beacon->getBaselineCount() : 0,
            'selectedSubnavItem' => 'sentinel',
        ]);
    }

    public function actionAuditLog(): Response
    {
        $this->requirePermission('garrison:viewAuditLog');

        $request = Craft::$app->getRequest();
        $page = max(1, (int) $request->getParam('page', 1));
        $perPage = 50;

        $criteria = array_filter([
            'action' => $request->getParam('action'),
            'category' => $request->getParam('category'),
        ]);

        $sentinel = Plugin::getInstance()->sentinel;
        $total = $sentinel->getAuditLogCount($criteria);

        return $this->renderTemplate('garrison/sentinel/_audit-log', [
            'entries' => $sentinel->getAuditLog($criteria, $perPage, ($page - 1) * $perPage),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'criteria' => $criteria,
            'selectedSubnavItem' => 'sentinel',
        ]);
    }

    public function actionFileIntegrity(): Response
    {
        Edition::requiresPro('File integrity monitoring');

        $beacon = Plugin::getInstance()->beacon;

        return $this->renderTemplate('garrison/sentinel/_file-integrity', [
            'baselineCount' => $beacon->getBaselineCount(),
            'changed' => $beacon->getChangedBaselines(),
            'monitoredPaths' => Plugin::getInstance()->getSettings()->monitoredPaths,
            'selectedSubnavItem' => 'sentinel',
        ]);
    }

    public function actionCreateBaseline(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('garrison:manageShield');
        Edition::requiresPro('File integrity monitoring');

        $count = Plugin::getInstance()->beacon->createBaseline();

        Craft::$app->getSession()->setNotice(
            Craft::t('garrison', 'Baseline created for {count} files.', ['count' => $count])
        );

        return $this->redirectToPostedUrl();
    }

    public function actionRunIntegrityCheck(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('garrison:manageShield');
        Edition::requiresPro('File integrity monitoring');

        $changes = Plugin::getInstance()->beacon->checkIntegrity();
        $total = count($changes['modified']) + count($changes['deleted']) + count($changes['added']);

        if ($total === 0) {
            Craft::$app->getSession()->setNotice(Craft::t('garrison', 'No file changes detected.'));
        } else {
            Craft::$app->getSession()->setError(
                Craft::t('garrison', '{count} file change(s) detected.', ['count' => $total])
            );
        }

        return $this->redirectToPostedUrl();
    }
}
