<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Feed;

final readonly class Product
{
    /** @param array<string, string> $fields */
    public function __construct(public string $id, public array $fields)
    {
    }
}
