<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Entitlements;

enum Feature: string
{
    case Feed = 'feed';
    case Tracking360 = 'tracking-360';
    case AdvancedPricing = 'advanced-pricing';
    case Analytics = 'analytics';
    case MultiAccount = 'multi-account';
    case MarketIntelligence = 'market-intelligence';
}
