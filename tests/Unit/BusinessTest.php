<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use StPiGhBestPriceIntegration\Entitlements\{PurchaseAdapter, EntitlementService, Feature, FeatureAccessGuard};
use StPiGhBestPriceIntegration\Pricing\{Decimal, PricingService};
use StPiGhBestPriceIntegration\Analytics\ProfitabilityService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class BusinessTest extends TestCase
{
    private function entitlement(array $ids): EntitlementService
    {
        return new EntitlementService(new class ($ids) implements PurchaseAdapter {
            public function __construct(private array $ids)
            {
            }
            public function active(string $identifier): bool
            {
                return in_array($identifier, $this->ids, true);
            }
        });
    }
    public static function tiers(): array
    {
        return [ [[], 'basic', false, false], [['professional'], 'professional', true, false], [['advanced'], 'advanced', true, true], [['advanced','professional'], 'advanced', true, true], [['tracking-360','multi-account'], 'basic', false, false] ];
    }
    #[DataProvider('tiers')]
    public function testExclusiveTiers(array $ids, string $tier, bool $pricing, bool $multi): void
    {
        $e = $this->entitlement($ids);
        self::assertSame($tier, $e->tier()->value);
        self::assertTrue($e->allows(Feature::Feed));
        self::assertSame($pricing, $e->allows(Feature::AdvancedPricing));
        self::assertSame($multi, $e->allows(Feature::MultiAccount));
        self::assertFalse($e->allows(Feature::MarketIntelligence));
        self::assertSame([], $e->status()['purchasable']);
    }
    public function testDowngradeIsObservedBySameGuard(): void
    {
        $adapter = new class () implements PurchaseAdapter {
            public bool $licensed = true;
            public function active(string $identifier): bool
            {
                return $this->licensed && $identifier === 'advanced';
            }
        };
        $service = new EntitlementService($adapter);
        $guard = new FeatureAccessGuard($service);
        $guard->assert(Feature::MultiAccount);
        $adapter->licensed = false;
        self::assertTrue($service->allows(Feature::Feed));
        $this->expectException(AccessDeniedHttpException::class);
        $guard->assert(Feature::MultiAccount);
    }
    public static function protectedFeatures(): array
    {
        return array_map(static fn (Feature $f) => [$f], array_filter(Feature::cases(), static fn (Feature $f) => $f !== Feature::Feed));
    }
    #[DataProvider('protectedFeatures')]
    public function testDirectPremiumGuardFailsClosed(Feature $feature): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        (new FeatureAccessGuard($this->entitlement([])))->assert($feature);
    }
    public static function invalidAmounts(): array
    {
        return [['1,23'], ['-1'], ['1e5'], ['NaN'], ['INF'], [' 2'], ['0.0000001'], ['10000000000'], [12.5], [null]];
    }
    #[DataProvider('invalidAmounts')]
    public function testInvalidDecimalRejected(mixed $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Decimal::amount($value);
    }
    public function testExactHalfUpAndNegativeRounding(): void
    {
        self::assertSame('1.01', Decimal::round('1.005'));
        self::assertSame('-1.01', Decimal::round('-1.005'));
        self::assertSame('0.30', Decimal::round(bcadd('0.1', '0.2', 6)));
    }
    public function testPriceMarginIsOnRevenueNotMarkup(): void
    {
        $s = new PricingService(new FeatureAccessGuard($this->entitlement(['professional'])));
        $r = $s->preview(['netCost' => '75.00','netOverhead' => '5.00','marginPercent' => '20','vatPercent' => '24']);
        self::assertSame('100.00', $r['netMinimum']);
        self::assertSame('124.00', $r['grossMinimum']);
        self::assertFalse($r['applied']);
    }
    public function testOneHundredPercentMarginRejected(): void
    {
        $s = new PricingService(new FeatureAccessGuard($this->entitlement(['advanced'])));
        $this->expectException(\InvalidArgumentException::class);
        $s->preview(['netCost' => '1','netOverhead' => '0','marginPercent' => '100','vatPercent' => '0']);
    }
    public function testIncompleteProfitIsNeverPretendPrecise(): void
    {
        $s = new ProfitabilityService(new FeatureAccessGuard($this->entitlement(['professional'])));
        $r = $s->calculate(['netRevenue' => '100']);
        self::assertFalse($r['complete']);
        self::assertNull($r['contribution']);
        self::assertContains('purchaseCost', $r['missing']);
    }
    public function testNegativeProfitAndZeroRevenue(): void
    {
        $s = new ProfitabilityService(new FeatureAccessGuard($this->entitlement(['professional'])));
        $input = ['netRevenue' => '100','purchaseCost' => '90','shippingCost' => '5','paymentCost' => '5','bestpriceCost' => '10','advertisingCost' => '0','returnCost' => '0','otherCost' => '0'];
        $r = $s->calculate($input);
        self::assertTrue($r['complete']);
        self::assertSame('-10.00', $r['contribution']);
        self::assertSame('-10.00', $r['marginPercent']);
        $input['netRevenue'] = '0';
        self::assertNull($s->calculate($input)['marginPercent']);
    }
    public function testMinimumPriceRoundsUpToProtectTargetMargin(): void
    {
        $service = new PricingService(new FeatureAccessGuard($this->entitlement(['professional'])));
        $result = $service->preview(['netCost' => '1','netOverhead' => '0','marginPercent' => '10','vatPercent' => '0']);
        self::assertSame('1.12', $result['netMinimum']);
        self::assertSame('1.12', $result['grossMinimum']);
    }
}
