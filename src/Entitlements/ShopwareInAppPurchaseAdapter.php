<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Entitlements;

use Shopware\Core\Framework\Store\InAppPurchase;

final readonly class ShopwareInAppPurchaseAdapter implements PurchaseAdapter
{
    public function __construct(private InAppPurchase $purchases)
    {
    }
    public function active(string $identifier): bool
    {
        // Reset the request/worker cache: long-running consumers must observe expiry.
        // Validation, domain binding, expiry and offline policy remain Shopware's responsibility.
        $this->purchases->reset();
        return $this->purchases->isActive('StPiGhBestPriceIntegration', $identifier);
    }
}
