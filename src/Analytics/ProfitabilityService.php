<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Analytics;

use StPiGhBestPriceIntegration\Entitlements\{Feature, FeatureAccessGuard};
use StPiGhBestPriceIntegration\Pricing\Decimal;

final readonly class ProfitabilityService
{
    public function __construct(private FeatureAccessGuard $guard)
    {
    }
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function calculate(array $input): array
    {
        $this->guard->assert(Feature::Analytics);
        $fields = ['netRevenue', 'purchaseCost', 'shippingCost', 'paymentCost', 'bestpriceCost', 'advertisingCost', 'returnCost', 'otherCost'];
        $missing = array_values(array_filter($fields, static fn (string $field) => !isset($input[$field]) || $input[$field] === ''));
        if ($missing !== []) {
            return ['complete' => false, 'missing' => $missing, 'contribution' => null, 'marginPercent' => null, 'source' => 'merchant-input'];
        }
        $revenue = Decimal::amount($input['netRevenue']);
        $costs = '0';
        foreach (array_slice($fields, 1) as $field) {
            $costs = bcadd($costs, Decimal::amount($input[$field]), 6);
        }
        $profit = bcsub($revenue, $costs, 6);
        return ['complete' => true, 'missing' => [], 'contribution' => Decimal::round($profit),
            'marginPercent' => bccomp($revenue, '0', 6) === 0 ? null : Decimal::round(bcmul(bcdiv($profit, $revenue, 8), '100', 6)),
            'breakEvenNetRevenue' => Decimal::round($costs), 'source' => 'merchant-input', 'currency' => 'EUR'];
    }
}
