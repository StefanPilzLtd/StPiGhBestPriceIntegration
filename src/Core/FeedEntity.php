<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Core;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class FeedEntity extends Entity
{
    use EntityIdTrait;
    protected string $name;
    protected string $salesChannelId;
    protected bool $enabled;
    /** @var array<string, mixed> */
    protected array $settings = [];
    public function getName(): string
    {
        return $this->name;
    }
    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }
    public function getEnabled(): bool
    {
        return $this->enabled;
    }
    /** @return array<string, mixed> */
    public function getSettings(): array
    {
        return $this->settings;
    }
    public function setName(string $name): void
    {
        $this->name = $name;
    }
    public function setSalesChannelId(string $id): void
    {
        $this->salesChannelId = $id;
    }
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
    /** @param array<string,mixed> $settings */
    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
    }
}
