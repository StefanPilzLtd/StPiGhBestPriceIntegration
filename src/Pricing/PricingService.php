<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Pricing;

use StPiGhBestPriceIntegration\Entitlements\{Feature, FeatureAccessGuard};

final readonly class PricingService
{
    public function __construct(private FeatureAccessGuard $guard)
    {
    }
    /** Preview only. Export prices always come from the real sales-channel context. */
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function preview(array $input): array
    {
        $this->guard->assert(Feature::AdvancedPricing);
        $cost = Decimal::amount($input['netCost'] ?? null);
        $overhead = Decimal::amount($input['netOverhead'] ?? null);
        $margin = Decimal::amount($input['marginPercent'] ?? null);
        $vat = Decimal::amount($input['vatPercent'] ?? null);
        if (bccomp($margin, '100', 6) >= 0 || bccomp($vat, '100', 6) > 0) {
            throw new \InvalidArgumentException('STPI_BESTPRICE_INVALID_PERCENTAGE');
        }
        $net = bcdiv(bcadd($cost, $overhead, 6), bcsub('1', bcdiv($margin, '100', 10), 10), 8);
        $gross = bcmul($net, bcadd('1', bcdiv($vat, '100', 10), 10), 8);
        return ['netMinimum' => Decimal::ceil($net), 'grossMinimum' => Decimal::ceil($gross),
            'source' => 'merchant-input', 'applied' => false, 'currency' => 'EUR'];
    }
}
