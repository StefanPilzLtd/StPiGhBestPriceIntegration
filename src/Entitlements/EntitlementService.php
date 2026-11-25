<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Entitlements;

final readonly class EntitlementService
{
    public function __construct(private PurchaseAdapter $purchases)
    {
    }
    public function tier(): SubscriptionTier
    {
        if ($this->purchases->active('advanced')) {
            return SubscriptionTier::Advanced;
        }
        return $this->purchases->active('professional') ? SubscriptionTier::Professional : SubscriptionTier::Basic;
    }
    public function allows(Feature $feature): bool
    {
        if ($feature === Feature::Feed) {
            return true;
        }
        // No commercial data rights: deliberately impossible to enable through configuration or IAP.
        if ($feature === Feature::MarketIntelligence || $feature === Feature::Tracking360) {
            return false;
        }
        $tier = $this->tier();
        if ($feature === Feature::MultiAccount) {
            return $tier === SubscriptionTier::Advanced;
        }
        return $tier !== SubscriptionTier::Basic;
    }
    /**
     * @return array<string,mixed>
     */
    public function status(): array
    {
        $features = [];
        foreach (Feature::cases() as $feature) {
            $features[$feature->value] = $this->allows($feature);
        }
        return ['tier' => $this->tier()->value, 'features' => $features,
            'catalogRegistered' => false, 'purchasable' => [], 'prices' => [],
            'addons' => [], 'model' => 'exclusive-tiers', 'marketDataRights' => false];
    }
}
