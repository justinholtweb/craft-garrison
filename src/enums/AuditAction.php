<?php

namespace justinholtweb\garrison\enums;

enum AuditAction: string
{
    case Login = 'login';
    case LoginFailed = 'loginFailed';
    case Logout = 'logout';
    case ElementSaved = 'elementSaved';
    case ElementDeleted = 'elementDeleted';
    case SettingChanged = 'settingChanged';
    case PluginInstalled = 'pluginInstalled';
    case PluginUninstalled = 'pluginUninstalled';
    case PluginEnabled = 'pluginEnabled';
    case PluginDisabled = 'pluginDisabled';
    case UserCreated = 'userCreated';
    case UserActivated = 'userActivated';
    case UserSuspended = 'userSuspended';
    case ProjectConfigChanged = 'projectConfigChanged';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Login',
            self::LoginFailed => 'Login Failed',
            self::Logout => 'Logout',
            self::ElementSaved => 'Element Saved',
            self::ElementDeleted => 'Element Deleted',
            self::SettingChanged => 'Setting Changed',
            self::PluginInstalled => 'Plugin Installed',
            self::PluginUninstalled => 'Plugin Uninstalled',
            self::PluginEnabled => 'Plugin Enabled',
            self::PluginDisabled => 'Plugin Disabled',
            self::UserCreated => 'User Created',
            self::UserActivated => 'User Activated',
            self::UserSuspended => 'User Suspended',
            self::ProjectConfigChanged => 'Project Config Changed',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::Login, self::LoginFailed, self::Logout => 'auth',
            self::ElementSaved, self::ElementDeleted => 'content',
            self::SettingChanged, self::ProjectConfigChanged => 'settings',
            self::PluginInstalled, self::PluginUninstalled, self::PluginEnabled, self::PluginDisabled => 'system',
            self::UserCreated, self::UserActivated, self::UserSuspended => 'auth',
        };
    }
}
