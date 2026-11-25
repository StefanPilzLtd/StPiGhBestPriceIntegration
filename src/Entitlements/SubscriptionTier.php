<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Entitlements;

enum SubscriptionTier: string
{
    case Basic = 'basic';
    case Professional = 'professional';
    case Advanced = 'advanced';
}
