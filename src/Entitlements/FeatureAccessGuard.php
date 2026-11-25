<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Entitlements;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class FeatureAccessGuard
{
    public function __construct(private EntitlementService $entitlements)
    {
    }
    public function assert(Feature $feature): void
    {
        if (!$this->entitlements->allows($feature)) {
            throw new AccessDeniedHttpException('STPI_BESTPRICE_FEATURE_REQUIRED');
        }
    }
}
