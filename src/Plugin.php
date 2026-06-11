<?php

namespace justinholtweb\garrison;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\controllers\UsersController;
use craft\events\ElementEvent;
use craft\events\LoginFailureEvent;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\UserEvent;
use craft\helpers\ElementHelper;
use craft\services\Dashboard as CraftDashboard;
use craft\services\Elements;
use craft\services\Plugins;
use craft\services\UserPermissions;
use craft\services\Users;
use craft\web\UrlManager;
use craft\web\User as WebUser;
use justinholtweb\garrison\enums\AuditAction;
use justinholtweb\garrison\models\Edition;
use justinholtweb\garrison\models\Settings;
use justinholtweb\garrison\queue\jobs\RunScanJob;
use justinholtweb\garrison\services\Beacon;
use justinholtweb\garrison\services\Dashboard;
use justinholtweb\garrison\services\Scanner;
use justinholtweb\garrison\services\Sentinel;
use justinholtweb\garrison\services\Shield;
use justinholtweb\garrison\widgets\RecentThreatsWidget;
use justinholtweb\garrison\widgets\SecurityScoreWidget;
use yii\base\Application;
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
        $this->registerShield();
        $this->registerAuditLog();
        $this->registerScheduler();
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
            function(RegisterUrlRulesEvent $event) {
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

                // REST API (Plus+)
                $event->rules['garrison/api/v1/scan/last'] = 'garrison/api/scan-last';
                $event->rules['garrison/api/v1/scan/run'] = 'garrison/api/scan-run';
                $event->rules['garrison/api/v1/scan/<id:\d+>'] = 'garrison/api/scan';
                $event->rules['garrison/api/v1/shield/status'] = 'garrison/api/shield-status';
                $event->rules['garrison/api/v1/sentinel/log'] = 'garrison/api/sentinel-log';
            }
        );
    }

    private function registerWidgets(): void
    {
        Event::on(
            CraftDashboard::class,
            CraftDashboard::EVENT_REGISTER_WIDGET_TYPES,
            function(RegisterComponentTypesEvent $event) {
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
            function(RegisterUserPermissionsEvent $event) {
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

    /**
     * Wire active request protection: per-request inspection plus the login
     * lifecycle (lockout enforcement, attempt recording).
     */
    private function registerShield(): void
    {
        // Inspect every request as early as possible.
        Event::on(
            Application::class,
            Application::EVENT_BEFORE_REQUEST,
            function() {
                Plugin::getInstance()->shield->handleRequest();
            }
        );

        // Enforce login lockout before the password is ever checked.
        Event::on(
            UsersController::class,
            UsersController::EVENT_BEFORE_FIND_LOGIN_USER,
            function() {
                $ip = Craft::$app->getRequest()->getUserIP();
                if ($ip !== null) {
                    Plugin::getInstance()->shield->enforceLoginLockout($ip);
                }
            }
        );

        // Record failed logins.
        Event::on(
            UsersController::class,
            UsersController::EVENT_LOGIN_FAILURE,
            function(LoginFailureEvent $event) {
                $ip = Craft::$app->getRequest()->getUserIP();
                if ($ip === null) {
                    return;
                }
                $username = $event->user->username
                    ?? Craft::$app->getRequest()->getBodyParam('loginName');
                Plugin::getInstance()->shield->recordLoginAttempt($ip, $username, false);
            }
        );

        // Record successful logins (clears the failure streak).
        Event::on(
            WebUser::class,
            WebUser::EVENT_AFTER_LOGIN,
            function(\yii\web\UserEvent $event) {
                $ip = Craft::$app->getRequest()->getUserIP();
                if ($ip !== null) {
                    Plugin::getInstance()->shield->recordLoginAttempt(
                        $ip,
                        $event->identity->username ?? null,
                        true,
                    );
                }
            }
        );
    }

    /**
     * Queue scheduled scans (Plus+). Evaluated at most once a minute on web
     * requests; enqueues a scan once the configured interval has elapsed since
     * the last one.
     */
    private function registerScheduler(): void
    {
        Event::on(
            Application::class,
            Application::EVENT_BEFORE_REQUEST,
            function() {
                $this->maybeQueueScheduledScan();
            }
        );
    }

    private function maybeQueueScheduledScan(): void
    {
        $settings = $this->getSettings();
        if (empty($settings->scanSchedule) || !Edition::isPlus()) {
            return;
        }

        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            return;
        }

        // Throttle the check itself so it runs once a minute, not once a request.
        $cache = Craft::$app->getCache();
        if ($cache->get('garrison:scheduler:checked')) {
            return;
        }
        $cache->set('garrison:scheduler:checked', true, 60);

        $interval = match ($settings->scanSchedule) {
            'hourly' => 3600,
            'daily' => 86400,
            'weekly' => 604800,
            'monthly' => 2592000,
            default => 0,
        };
        if ($interval === 0) {
            return;
        }

        $lastScan = Plugin::getInstance()->scanner->getLastScan();
        $lastRun = $lastScan?->dateCreated?->getTimestamp() ?? 0;

        if ((time() - $lastRun) >= $interval) {
            Craft::$app->getQueue()->push(new RunScanJob());
        }
    }

    /**
     * Wire audit logging. Listeners only attach when the audit log is enabled,
     * and each handler funnels through Sentinel::log().
     */
    private function registerAuditLog(): void
    {
        if (!$this->getSettings()->enableAuditLog) {
            return;
        }

        $sentinel = fn() => Plugin::getInstance()->sentinel;

        Event::on(WebUser::class, WebUser::EVENT_AFTER_LOGIN, function(\yii\web\UserEvent $event) use ($sentinel) {
            $sentinel()->log(AuditAction::Login->value, 'auth', [
                'targetType' => 'user',
                'targetId' => $event->identity->id ?? null,
                'targetTitle' => $event->identity->username ?? null,
            ]);
        });

        Event::on(UsersController::class, UsersController::EVENT_LOGIN_FAILURE, function(LoginFailureEvent $event) use ($sentinel) {
            $sentinel()->log(AuditAction::LoginFailed->value, 'auth', [
                'details' => ['username' => $event->user->username ?? Craft::$app->getRequest()->getBodyParam('loginName')],
            ]);
        });

        Event::on(WebUser::class, WebUser::EVENT_AFTER_LOGOUT, function(\yii\web\UserEvent $event) use ($sentinel) {
            $sentinel()->log(AuditAction::Logout->value, 'auth', [
                'userId' => $event->identity->id ?? null,
                'userName' => $event->identity->username ?? null,
            ]);
        });

        // Plugin lifecycle.
        foreach ([
            Plugins::EVENT_AFTER_INSTALL_PLUGIN => AuditAction::PluginInstalled,
            Plugins::EVENT_AFTER_UNINSTALL_PLUGIN => AuditAction::PluginUninstalled,
            Plugins::EVENT_AFTER_ENABLE_PLUGIN => AuditAction::PluginEnabled,
            Plugins::EVENT_AFTER_DISABLE_PLUGIN => AuditAction::PluginDisabled,
        ] as $eventName => $action) {
            Event::on(Plugins::class, $eventName, function(PluginEvent $event) use ($sentinel, $action) {
                $sentinel()->log($action->value, 'system', [
                    'targetType' => 'plugin',
                    'targetTitle' => $event->plugin->handle ?? null,
                ]);
            });
        }

        // User suspend / activate.
        Event::on(Users::class, Users::EVENT_AFTER_SUSPEND_USER, function(UserEvent $event) use ($sentinel) {
            $sentinel()->log(AuditAction::UserSuspended->value, 'auth', [
                'targetType' => 'user',
                'targetId' => $event->user->id,
                'targetTitle' => $event->user->username,
            ]);
        });
        Event::on(Users::class, Users::EVENT_AFTER_ACTIVATE_USER, function(UserEvent $event) use ($sentinel) {
            $sentinel()->log(AuditAction::UserActivated->value, 'auth', [
                'targetType' => 'user',
                'targetId' => $event->user->id,
                'targetTitle' => $event->user->username,
            ]);
        });

        // Element creation / deletion (skips drafts, revisions, and propagation
        // to keep the log focused on meaningful changes).
        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, function(ElementEvent $event) use ($sentinel) {
            if (!$event->isNew || ElementHelper::isDraftOrRevision($event->element) || $event->element->propagating) {
                return;
            }
            $sentinel()->log(AuditAction::ElementSaved->value, 'content', [
                'targetType' => $event->element::displayName(),
                'targetId' => $event->element->id,
                'targetTitle' => (string) $event->element,
            ]);
        });
        Event::on(Elements::class, Elements::EVENT_AFTER_DELETE_ELEMENT, function(ElementEvent $event) use ($sentinel) {
            if (ElementHelper::isDraftOrRevision($event->element) || $event->element->propagating) {
                return;
            }
            $sentinel()->log(AuditAction::ElementDeleted->value, 'content', [
                'targetType' => $event->element::displayName(),
                'targetId' => $event->element->id,
                'targetTitle' => (string) $event->element,
            ]);
        });
    }
}
