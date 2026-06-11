<?php

namespace justinholtweb\garrison\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\garrison\Plugin;

class SettingsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireAdmin(false);

        return true;
    }

    public function actionIndex(): \yii\web\Response
    {
        return $this->renderTemplate('garrison/settings/_index', [
            'settings' => Plugin::getInstance()->getSettings(),
            'plugin' => Plugin::getInstance(),
            'selectedSubnavItem' => 'settings',
        ]);
    }

    public function actionNotifications(): \yii\web\Response
    {
        return $this->renderTemplate('garrison/settings/_notifications', [
            'settings' => Plugin::getInstance()->getSettings(),
            'selectedSubnavItem' => 'settings',
        ]);
    }

    public function actionScanner(): \yii\web\Response
    {
        return $this->renderTemplate('garrison/settings/_scanner', [
            'settings' => Plugin::getInstance()->getSettings(),
            'checks' => Plugin::getInstance()->scanner->getChecks(),
            'selectedSubnavItem' => 'settings',
        ]);
    }

    public function actionAdvanced(): \yii\web\Response
    {
        return $this->renderTemplate('garrison/settings/_advanced', [
            'settings' => Plugin::getInstance()->getSettings(),
            'selectedSubnavItem' => 'settings',
        ]);
    }

    public function actionSave(): ?\yii\web\Response
    {
        $this->requirePostRequest();

        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();
        $request = Craft::$app->getRequest();

        $settingsData = $request->getBodyParam('settings', []);
        $settingsData = $this->normalizeListFields($settingsData);

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settingsData)) {
            Craft::$app->getSession()->setError(Craft::t('garrison', 'Couldn\'t save settings.'));
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('garrison', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Convert comma/newline-separated string inputs into the arrays their
     * settings expect (country codes, email recipients).
     */
    private function normalizeListFields(array $data): array
    {
        foreach (['blockedCountries', 'emailRecipients'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $items = preg_split('/[\s,]+/', trim($data[$field]), -1, PREG_SPLIT_NO_EMPTY);
                $data[$field] = array_values($items ?: []);
            }
        }

        return $data;
    }
}
