<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

final class RefreshFeedsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'stpi_gh_bestprice.refresh_feeds';
    }
    public static function getDefaultInterval(): int
    {
        return 900;
    }
}
