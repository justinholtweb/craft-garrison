<?php

namespace justinholtweb\garrison\services;

use Craft;
use craft\base\Component;
use craft\helpers\Db;
use justinholtweb\garrison\Plugin;
use justinholtweb\garrison\records\BlockedRequestRecord;
use justinholtweb\garrison\records\ScanRecord;

/**
 * Dashboard — aggregate analytics across the other modules.
 */
class Dashboard extends Component
{
    /**
     * High-level numbers for the dashboard landing page.
     *
     * @return array{riskScore: int|null, riskLabel: string|null, blocked7d: int, threats: int, baselineFiles: int}
     */
    public function getSummary(?int $siteId = null): array
    {
        $plugin = Plugin::getInstance();
        $lastScan = $plugin->scanner->getLastScan($siteId);

        return [
            'riskScore' => $lastScan?->riskScore,
            'riskLabel' => $lastScan?->riskLabel(),
            'blocked7d' => $plugin->shield->getBlockedRequestCount($this->daysAgo(7)),
            'threats' => $plugin->shield->getBlockedRequestCount(),
            'baselineFiles' => $plugin->beacon->getBaselineCount(),
        ];
    }

    /**
     * Risk-score history for trend charts.
     *
     * @return array<int, array{date: string, riskScore: int, status: string}>
     */
    public function getRiskTrend(int $days = 30, ?int $siteId = null): array
    {
        if ($siteId === null) {
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        }

        $records = ScanRecord::find()
            ->select(['riskScore', 'status', 'dateCreated'])
            ->where(['siteId' => $siteId])
            ->andWhere(['>=', 'dateCreated', $this->daysAgo($days)])
            ->orderBy(['dateCreated' => SORT_ASC])
            ->asArray()
            ->all();

        return array_map(fn(array $r) => [
            'date' => $r['dateCreated'],
            'riskScore' => (int) $r['riskScore'],
            'status' => $r['status'],
        ], $records);
    }

    /**
     * Blocked-request counts grouped by reason over the given window.
     *
     * @return array<string, int>
     */
    public function getThreatStats(int $days = 30): array
    {
        $rows = BlockedRequestRecord::find()
            ->select(['reason', 'count' => 'COUNT(*)'])
            ->where(['>=', 'dateCreated', $this->daysAgo($days)])
            ->groupBy(['reason'])
            ->asArray()
            ->all();

        $stats = [];
        foreach ($rows as $row) {
            $stats[$row['reason']] = (int) $row['count'];
        }

        return $stats;
    }

    private function daysAgo(int $days): string
    {
        return Db::prepareDateForDb(
            (new \DateTime('now', new \DateTimeZone('UTC')))->modify("-{$days} days")
        );
    }
}
