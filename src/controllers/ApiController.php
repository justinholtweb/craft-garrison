<?php

namespace justinholtweb\garrison\controllers;

use Craft;
use craft\web\Controller;
use justinholtweb\garrison\models\Edition;

class ApiController extends Controller
{
    // Default to authenticated. Subclasses must explicitly opt in to anonymous actions
    // and implement their own auth (token, HMAC, etc.) before flipping this.
    protected array|bool|int $allowAnonymous = false;

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        Edition::requiresPlus('REST API');

        return true;
    }
}
