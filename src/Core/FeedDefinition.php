<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Core;

use Shopware\Core\Framework\DataAbstractionLayer\{EntityDefinition, FieldCollection};
use Shopware\Core\Framework\DataAbstractionLayer\Field\{IdField, StringField, BoolField, JsonField, FkField};
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\{PrimaryKey, Required};
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

final class FeedDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'stpi_gh_bp_feed';
    }
    public function getEntityClass(): string
    {
        return FeedEntity::class;
    }
    public function getCollectionClass(): string
    {
        return FeedCollection::class;
    }
    protected function defineProtections(): \Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection
    {
        return new \Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection([new \Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\WriteProtection(\Shopware\Core\Framework\Context::SYSTEM_SCOPE)]);
    }
    protected function defineFields(): FieldCollection
    {
        // Intentionally no ApiAware flags: generic DAL API cannot bypass business/entitlement checks.
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('name', 'name'))->addFlags(new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required()),
            (new BoolField('enabled', 'enabled'))->addFlags(new Required()),
            (new JsonField('settings', 'settings'))->addFlags(new Required()),
        ]);
        foreach ($fields as $field) {
            $field->removeFlag(\Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware::class);
        }
        return $fields;
    }
}
