<?php

namespace justinholtweb\garrison\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use justinholtweb\garrison\models\AccessRule;
use justinholtweb\garrison\Plugin;
use yii\console\ExitCode;

/**
 * Manage Shield IP allow/block rules from the command line.
 */
class ShieldController extends Controller
{
    /**
     * @var string Rule scope: all, cp, or frontend.
     */
    public string $scope = 'all';

    /**
     * @var string|null Optional label for the rule.
     */
    public ?string $label = null;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        if (in_array($actionID, ['block', 'allow'], true)) {
            $options[] = 'scope';
            $options[] = 'label';
        }

        return $options;
    }

    /**
     * Block an IP address, CIDR range, or wildcard pattern.
     */
    public function actionBlock(string $ip): int
    {
        return $this->addRule('block', $ip);
    }

    /**
     * Allow an IP address, CIDR range, or wildcard pattern.
     */
    public function actionAllow(string $ip): int
    {
        return $this->addRule('allow', $ip);
    }

    /**
     * List all IP rules.
     */
    public function actionList(): int
    {
        $rules = Plugin::getInstance()->shield->getAccessRules();

        if (empty($rules)) {
            $this->stdout("No IP rules defined.\n");
            return ExitCode::OK;
        }

        foreach ($rules as $rule) {
            $this->stdout(sprintf(
                "#%d  %-6s  %-18s  scope=%-8s  %s\n",
                $rule->id,
                strtoupper($rule->type),
                $rule->ipPattern,
                $rule->scope,
                $rule->label ?? '',
            ));
        }

        return ExitCode::OK;
    }

    /**
     * Remove an IP rule by ID.
     */
    public function actionRemove(int $id): int
    {
        $ok = Plugin::getInstance()->shield->deleteAccessRule($id);
        $this->stdout($ok ? "Rule #$id removed.\n" : "Rule #$id not found.\n");

        return $ok ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    private function addRule(string $type, string $ip): int
    {
        $rule = new AccessRule();
        $rule->type = $type;
        $rule->scope = $this->scope;
        $rule->ipPattern = $ip;
        $rule->label = $this->label;

        if (!Plugin::getInstance()->shield->saveAccessRule($rule)) {
            $this->stderr("Invalid rule: " . implode(', ', $rule->getFirstErrors()) . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout(sprintf("%s rule added for %s (#%d).\n", ucfirst($type), $ip, $rule->id), Console::FG_GREEN);

        return ExitCode::OK;
    }
}
