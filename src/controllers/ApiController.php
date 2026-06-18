<?php

namespace justinholtweb\garrison\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\garrison\models\Edition;
use justinholtweb\garrison\Plugin;
use yii\web\Response;

/**
 * Read-mostly JSON API for Garrison (Pro).
 *
 * Authentication uses Craft's standard session/permission system — callers must
 * be authenticated control-panel users with the relevant Garrison permissions.
 * Every endpoint returns JSON.
 */
class ApiController extends Controller
{
    // Authenticated only. Token-based headless auth is intentionally out of
    // scope for 1.0 — the API rides Craft's session + permission checks.
    protected array|bool|int $allowAnonymous = false;

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        Edition::requiresPro('REST API');
        $this->requirePermission('garrison:accessPlugin');

        return true;
    }

    /**
     * GET garrison/api/v1/scan/last — most recent scan summary.
     */
    public function actionScanLast(): Response
    {
        $report = Plugin::getInstance()->scanner->getLastScan();

        if ($report === null) {
            return $this->asJson(['scan' => null]);
        }

        return $this->asJson(['scan' => $this->scanToArray($report)]);
    }

    /**
     * POST garrison/api/v1/scan/run — run a scan and return its result.
     */
    public function actionScanRun(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('garrison:runScans');

        $siteId = Craft::$app->getRequest()->getBodyParam('siteId');
        $report = Plugin::getInstance()->scanner->runScan(
            $siteId ? (int)$siteId : null,
            'api',
            Craft::$app->getUser()->getId(),
        );

        return $this->asJson(['scan' => $this->scanToArray($report)]);
    }

    /**
     * GET garrison/api/v1/scan/<id> — a specific scan with full results.
     */
    public function actionScan(int $id): Response
    {
        $report = Plugin::getInstance()->scanner->getScanById($id);

        if ($report === null) {
            return $this->asJson(['scan' => null])->setStatusCode(404);
        }

        $data = $this->scanToArray($report);
        $data['results'] = array_map(fn($r) => [
            'check' => $r->checkHandle,
            'name' => $r->checkName,
            'status' => $r->status->value,
            'severity' => $r->severity->value,
            'message' => $r->message,
            'remediation' => $r->remediation,
        ], $report->results);

        return $this->asJson(['scan' => $data]);
    }

    /**
     * GET garrison/api/v1/shield/status — blocked-request counts.
     */
    public function actionShieldStatus(): Response
    {
        $shield = Plugin::getInstance()->shield;

        return $this->asJson([
            'blockedTotal' => $shield->getBlockedRequestCount(),
            'rules' => count($shield->getAccessRules()),
        ]);
    }

    /**
     * GET garrison/api/v1/sentinel/log — recent audit-log entries.
     */
    public function actionSentinelLog(): Response
    {
        $this->requirePermission('garrison:viewAuditLog');

        $limit = min(200, (int)Craft::$app->getRequest()->getParam('limit', 50));
        $entries = Plugin::getInstance()->sentinel->getAuditLog([], $limit);

        return $this->asJson([
            'entries' => array_map(fn($e) => [
                'action' => $e->action,
                'category' => $e->category,
                'user' => $e->userName,
                'ip' => $e->ipAddress,
                'target' => $e->targetTitle,
                'date' => $e->dateCreated,
            ], $entries),
        ]);
    }

    private function scanToArray($report): array
    {
        return [
            'id' => $report->id,
            'status' => $report->status->value,
            'riskScore' => $report->riskScore,
            'riskLabel' => $report->riskLabel(),
            'passed' => $report->passedChecks,
            'warnings' => $report->warningChecks,
            'failed' => $report->failedChecks,
            'critical' => $report->criticalChecks,
            'date' => $report->dateCreated?->format('c'),
        ];
    }
}
