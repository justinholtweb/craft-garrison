<?php

namespace justinholtweb\garrison\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\garrison\models\AccessRule;
use justinholtweb\garrison\models\Edition;
use justinholtweb\garrison\Plugin;
use yii\web\Response;

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

    public function actionIndex(): Response
    {
        return $this->renderTemplate('garrison/shield/_index', [
            'settings' => Plugin::getInstance()->getSettings(),
            'blockedCount' => Plugin::getInstance()->shield->getBlockedRequestCount(),
            'selectedSubnavItem' => 'shield',
        ]);
    }

    public function actionIpManagement(): Response
    {
        Edition::requiresPro('IP management');

        return $this->renderTemplate('garrison/shield/_ip-management', [
            'rules' => Plugin::getInstance()->shield->getAccessRules(),
            'selectedSubnavItem' => 'shield',
        ]);
    }

    public function actionSaveRule(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('garrison:manageShield');
        Edition::requiresPro('IP management');

        $request = Craft::$app->getRequest();

        $rule = new AccessRule();
        $rule->id = $request->getBodyParam('ruleId') ?: null;
        $rule->type = $request->getBodyParam('type', 'block');
        $rule->scope = $request->getBodyParam('scope', 'all');
        $rule->ipPattern = trim((string) $request->getBodyParam('ipPattern'));
        $rule->label = $request->getBodyParam('label') ?: null;
        $rule->enabled = (bool) $request->getBodyParam('enabled', true);

        if (!Plugin::getInstance()->shield->saveAccessRule($rule)) {
            Craft::$app->getSession()->setError(Craft::t('garrison', 'Couldn\'t save IP rule.'));
            Craft::$app->getUrlManager()->setRouteParams(['rule' => $rule]);
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('garrison', 'IP rule saved.'));

        return $this->redirectToPostedUrl();
    }

    public function actionDeleteRule(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('garrison:manageShield');
        Edition::requiresPro('IP management');

        $id = (int) Craft::$app->getRequest()->getRequiredBodyParam('id');
        Plugin::getInstance()->shield->deleteAccessRule($id);

        Craft::$app->getSession()->setNotice(Craft::t('garrison', 'IP rule deleted.'));

        return $this->redirectToPostedUrl();
    }

    public function actionRateLimits(): Response
    {
        Edition::requiresPro('Rate limiting');

        return $this->renderTemplate('garrison/shield/_rate-limits', [
            'settings' => Plugin::getInstance()->getSettings(),
            'selectedSubnavItem' => 'shield',
        ]);
    }

    public function actionLoginProtection(): Response
    {
        return $this->renderTemplate('garrison/shield/_login-protection', [
            'settings' => Plugin::getInstance()->getSettings(),
            'blocked' => Plugin::getInstance()->shield->getBlockedRequests(25),
            'selectedSubnavItem' => 'shield',
        ]);
    }

    public function actionWaf(): Response
    {
        Edition::requiresPro('WAF');

        return $this->renderTemplate('garrison/shield/_waf', [
            'settings' => Plugin::getInstance()->getSettings(),
            'selectedSubnavItem' => 'shield',
        ]);
    }
}
