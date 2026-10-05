<?php

namespace justinholtweb\garrison\controllers;

use Craft;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\services\ProjectConfig;
use craft\web\Controller;
use justinholtweb\garrison\Plugin;

class SettingsController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Admins and "Manage Garrison settings" can look (the screens go read-only for anyone who
        // can't save). Saving writes project config, so actionSave() needs an admin with
        // allowAdminChanges on.
        if (!Craft::$app->getUser()->getIsAdmin()) {
            $this->requirePermission('garrison:manageSettings');
        }

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
        $this->requireAdmin();

        $plugin = Plugin::getInstance();
        $request = Craft::$app->getRequest();

        $posted = $request->getBodyParam('settings', []);
        $posted = $this->normalizeListFields(is_array($posted) ? $posted : []);

        // Each screen posts only its own fields, and savePluginSettings() writes only the keys it
        // is given — so passing the post alone erased every setting saved from another screen,
        // switching protections off. Merge the post over what is already stored. Not over
        // getSettings(): that carries config/garrison.php overrides, which belong in that file,
        // not in project config.
        $stored = Craft::$app->getProjectConfig()->get(ProjectConfig::PATH_PLUGINS . '.' . $plugin->handle . '.settings') ?? [];
        $settingsData = array_merge(ProjectConfigHelper::unpackAssociativeArrays($stored), $posted);

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
                $data[$field] = $items ?: [];
            }
        }

        return $data;
    }
}
