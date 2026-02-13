<?php

namespace justinholtweb\garrison\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\garrison\models\Edition;

class ApiController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        Edition::requiresPlus('REST API');

        // API authentication will be implemented in Phase 4
        return true;
    }
}
