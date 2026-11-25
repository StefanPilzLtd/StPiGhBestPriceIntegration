<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Core;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/** @extends EntityCollection<FeedEntity> */
final class FeedCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return FeedEntity::class;
    }
}
