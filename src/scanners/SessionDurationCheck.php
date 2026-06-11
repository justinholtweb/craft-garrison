<?php

namespace justinholtweb\garrison\scanners;

use Craft;
use craft\helpers\ConfigHelper;
use justinholtweb\garrison\enums\CheckCategory;
use justinholtweb\garrison\enums\Severity;
use justinholtweb\garrison\models\ScanResult;

/**
 * Warns when authenticated sessions stay valid for an unusually long time,
 * which widens the window for session hijacking on shared devices.
 */
class SessionDurationCheck extends BaseScannerCheck
{
    /** Two weeks, in seconds. */
    private const MAX_RECOMMENDED = 1209600;

    public function getHandle(): string
    {
        return 'session-duration';
    }

    public function getName(): string
    {
        return 'Session Duration';
    }

    public function getCategory(): CheckCategory
    {
        return CheckCategory::Authentication;
    }

    public function getDefaultSeverity(): Severity
    {
        return Severity::Low;
    }

    protected function performCheck(?int $siteId = null): ScanResult
    {
        $config = Craft::$app->getConfig()->getGeneral();
        $seconds = ConfigHelper::durationInSeconds($config->userSessionDuration);

        if ($seconds === 0) {
            return $this->pass('Sessions expire when the browser is closed.');
        }

        if ($seconds > self::MAX_RECOMMENDED) {
            $days = round($seconds / 86400, 1);

            return $this->warning(
                sprintf('User sessions stay valid for %s days.', $days),
                Severity::Low,
                "Consider lowering 'userSessionDuration' in config/general.php to two weeks or less.",
                ['seconds' => $seconds],
            );
        }

        return $this->pass('User session duration is within a reasonable window.');
    }
}
