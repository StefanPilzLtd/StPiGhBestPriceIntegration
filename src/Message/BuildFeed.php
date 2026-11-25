<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

final readonly class BuildFeed implements AsyncMessageInterface
{
    public function __construct(public string $feedId)
    {
    }
}
