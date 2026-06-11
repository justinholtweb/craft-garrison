<?php

namespace justinholtweb\garrison\scanners;

use Craft;
use justinholtweb\garrison\enums\CheckCategory;
use justinholtweb\garrison\enums\Severity;
use justinholtweb\garrison\models\ScanResult;

/**
 * Warns when GraphQL schema introspection is enabled in production. Public
 * introspection lets attackers map the entire API surface.
 */
class GqlIntrospectionCheck extends BaseScannerCheck
{
    public function getHandle(): string
    {
        return 'gql-introspection';
    }

    public function getName(): string
    {
        return 'GraphQL Introspection';
    }

    public function getCategory(): CheckCategory
    {
        return CheckCategory::Config;
    }

    public function getDefaultSeverity(): Severity
    {
        return Severity::Medium;
    }

    protected function performCheck(?int $siteId = null): ScanResult
    {
        $config = Craft::$app->getConfig()->getGeneral();

        // Only a concern when admin changes are locked down (i.e. production).
        if ($config->allowAdminChanges) {
            return $this->skip('Admin changes are allowed; introspection is expected in development.');
        }

        if ($config->enableGraphqlIntrospection) {
            return $this->warning(
                'GraphQL introspection is enabled in a production environment.',
                Severity::Medium,
                "Set 'enableGraphqlIntrospection' => false in config/general.php for production.",
            );
        }

        return $this->pass('GraphQL introspection is disabled in production.');
    }
}
