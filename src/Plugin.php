<?php

namespace justinholtweb\garrison;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\Dashboard as CraftDashboard;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use justinholtweb\garrison\widgets\RecentThreatsWidget;
use justinholtweb\garrison\widgets\SecurityScoreWidget;
use justinholtweb\garrison\models\Edition;
use justinholtweb\garrison\models\Settings;
use justinholtweb\garrison\services\Beacon;
use justinholtweb\garrison\services\Dashboard;
use justinholtweb\garrison\services\Scanner;
use justinholtweb\garrison\services\Sentinel;
use justinholtweb\garrison\services\Shield;
use yii\base\Event;

/**
 * Garrison — Security Suite for Craft CMS
 *
 * @property Scanner $scanner
 * @property Shield $shield
 * @property Sentinel $sentinel
 * @property Beacon $beacon
 * @property Dashboard $dashboard
 * @property Settings $settings
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => [
                'scanner' => Scanner::class,
                'shield' => Shield::class,
                'sentinel' => Sentinel::class,
                'beacon' => Beacon::class,
                'dashboard' => Dashboard::class,
            ],
        ];
    }

    public static function editions(): array
    {
        return [
            Edition::LITE,
            Edition::PLUS,
            Edition::PRO,
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->registerCpRoutes();
        $this->registerPermissions();
        $this->registerWidgets();
    }

    public function getCpNavItem(): ?array
    {
        $nav = parent::getCpNavItem();
        $nav['label'] = 'Garrison';

        $nav['subnav'] = [];

        if (Craft::$app->getUser()->checkPermission('garrison:accessPlugin')) {
            $nav['subnav']['dashboard'] = [
                'label' => Craft::t('garrison', 'Dashboard'),
                'url' => 'garrison',
            ];

            $nav['subnav']['scanner'] = [
                'label' => Craft::t('garrison', 'Scanner'),
                'url' => 'garrison/scanner',
            ];

            $nav['subnav']['shield'] = [
                'label' => Craft::t('garrison', 'Shield'),
                'url' => 'garrison/shield',
            ];

            $nav['subnav']['sentinel'] = [
                'label' => Craft::t('garrison', 'Sentinel'),
                'url' => 'garrison/sentinel',
            ];
        }

        if (Craft::$app->getUser()->getIsAdmin() ||
            Craft::$app->getUser()->checkPermission('garrison:manageSettings')) {
            $nav['subnav']['settings'] = [
                'label' => Craft::t('garrison', 'Settings'),
                'url' => 'garrison/settings',
            ];
        }

        return $nav;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('garrison/settings/_index', [
            'settings' => $this->getSettings(),
            'plugin' => $this,
        ]);
    }

    private function registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                // Dashboard
                $event->rules['garrison'] = 'garrison/dashboard/index';

                // Scanner
                $event->rules['garrison/scanner'] = 'garrison/scanner/index';
                $event->rules['garrison/scanner/results/<scanId:\d+>'] = 'garrison/scanner/results';
                $event->rules['garrison/scanner/history'] = 'garrison/scanner/history';

                // Shield
                $event->rules['garrison/shield'] = 'garrison/shield/index';
                $event->rules['garrison/shield/ip-management'] = 'garrison/shield/ip-management';
                $event->rules['garrison/shield/rate-limits'] = 'garrison/shield/rate-limits';
                $event->rules['garrison/shield/login-protection'] = 'garrison/shield/login-protection';
                $event->rules['garrison/shield/waf'] = 'garrison/shield/waf';

                // Sentinel
                $event->rules['garrison/sentinel'] = 'garrison/sentinel/index';
                $event->rules['garrison/sentinel/audit-log'] = 'garrison/sentinel/audit-log';
                $event->rules['garrison/sentinel/file-integrity'] = 'garrison/sentinel/file-integrity';

                // Settings
                $event->rules['garrison/settings'] = 'garrison/settings/index';
                $event->rules['garrison/settings/notifications'] = 'garrison/settings/notifications';
                $event->rules['garrison/settings/scanner'] = 'garrison/settings/scanner';
                $event->rules['garrison/settings/advanced'] = 'garrison/settings/advanced';
            }
        );
    }

    private function registerWidgets(): void
    {
        Event::on(
            CraftDashboard::class,
            CraftDashboard::EVENT_REGISTER_WIDGET_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = SecurityScoreWidget::class;
                $event->types[] = RecentThreatsWidget::class;
            }
        );
    }

    private function registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('garrison', 'Garrison'),
                    'permissions' => [
                        'garrison:accessPlugin' => [
                            'label' => Craft::t('garrison', 'Access Garrison'),
                            'nested' => [
                                'garrison:runScans' => [
                                    'label' => Craft::t('garrison', 'Run security scans'),
                                ],
                                'garrison:viewAuditLog' => [
                                    'label' => Craft::t('garrison', 'View audit log'),
                                ],
                                'garrison:manageShield' => [
                                    'label' => Craft::t('garrison', 'Manage shield rules'),
                                ],
                            ],
                        ],
                        'garrison:manageSettings' => [
                            'label' => Craft::t('garrison', 'Manage Garrison settings'),
                        ],
                    ],
                ];
            }
        );
    }
}
