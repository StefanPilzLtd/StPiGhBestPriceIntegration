<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Entitlements;

interface PurchaseAdapter
{
    public function active(string $identifier): bool;
}
