<?php

namespace justinholtweb\garrison\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use justinholtweb\garrison\models\Edition;
use justinholtweb\garrison\Plugin;
use yii\console\ExitCode;

/**
 * File integrity monitoring commands.
 */
class IntegrityController extends Controller
{
    /**
     * Record a fresh SHA-256 baseline of all monitored files.
     */
    public function actionBaseline(): int
    {
        if (!Edition::isPlus()) {
            $this->stderr("File integrity monitoring requires Garrison Plus or Pro.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $count = Plugin::getInstance()->beacon->createBaseline();
        $this->stdout("Baseline recorded for $count files.\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Compare monitored files against the baseline and report any changes.
     */
    public function actionCheck(): int
    {
        if (!Edition::isPlus()) {
            $this->stderr("File integrity monitoring requires Garrison Plus or Pro.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $changes = Plugin::getInstance()->beacon->checkIntegrity();
        $total = count($changes['modified']) + count($changes['deleted']) + count($changes['added']);

        if ($total === 0) {
            $this->stdout("No changes detected.\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        foreach (['modified', 'deleted', 'added'] as $type) {
            foreach ($changes[$type] as $path) {
                $this->stdout(sprintf("  [%s] %s\n", strtoupper($type), $path));
            }
        }

        $this->stdout("\n$total change(s) detected.\n", Console::FG_YELLOW);

        return ExitCode::UNSPECIFIED_ERROR;
    }
}
