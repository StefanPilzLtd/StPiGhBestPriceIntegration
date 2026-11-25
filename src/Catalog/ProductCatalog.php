<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Catalog;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ArrayParameterType;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Content\Product\SalesChannel\{ProductAvailableFilter, SalesChannelProductEntity};
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\{EqualsFilter, EqualsAnyFilter, NotFilter, MultiFilter, RangeFilter};
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\{AbstractSalesChannelContextFactory, SalesChannelContextService};
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use StPiGhBestPriceIntegration\Feed\Product;
use StPiGhBestPriceIntegration\Pricing\Decimal;
use StPiGhBestPriceIntegration\Security\Validation;

final readonly class ProductCatalog
{
    /** @param SalesChannelRepository<\Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection> $products */
    public function __construct(private SalesChannelRepository $products, private AbstractSalesChannelContextFactory $factory, private CartRuleLoader $ruleLoader, private ProductStreamBuilder $streams, private Connection $connection)
    {
    }
    /**
     * @param array<string, mixed> $feed
     * @return \Generator<Product>
     */
    public function stream(array $feed): \Generator
    {
        $domain = $this->connection->fetchAssociative('SELECT url, LOWER(HEX(language_id)) languageId, LOWER(HEX(currency_id)) currencyId FROM sales_channel_domain WHERE id = :id AND sales_channel_id = :channel', ['id' => Uuid::fromHexToBytes($feed['domainId']), 'channel' => Uuid::fromHexToBytes($feed['salesChannelId'])]);
        if (!$domain) {
            throw new \RuntimeException('STPI_BESTPRICE_DOMAIN_MISSING');
        }
        $token = Uuid::randomHex();
        $context = $this->factory->create($token, $feed['salesChannelId'], [SalesChannelContextService::LANGUAGE_ID => $domain['languageId'], SalesChannelContextService::CURRENCY_ID => $domain['currencyId'], SalesChannelContextService::COUNTRY_ID => $feed['countryId'], SalesChannelContextService::DOMAIN_ID => $feed['domainId']]);
        $context->getContext()->setConsiderInheritance(true);
        $this->ruleLoader->loadByToken($context, $token);
        if ($context->getCurrency()->getIsoCode() !== 'EUR' || $context->getTaxState() !== 'gross' || !$context->getSalesChannel()->getActive()) {
            throw new \RuntimeException('STPI_BESTPRICE_EUR_GROSS_ACTIVE_REQUIRED');
        }
        $base = new Criteria();
        $base->setLimit(100);
        $base->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);
        $base->addSorting(new FieldSorting('id', FieldSorting::ASCENDING));
        $base->addFilter(new ProductAvailableFilter($feed['salesChannelId']), new EqualsFilter('childCount', 0));
        foreach (['cover.media', 'manufacturer', 'categories', 'options.group', 'properties.group', 'tax'] as $association) {
            $base->addAssociation($association);
        }
        if ($feed['productStreamId']) {
            $this->streams->enrichCriteria($base, $feed['productStreamId'], $context->getContext());
        }
        foreach (['categoryIds' => 'categoriesRo.id', 'manufacturerIds' => 'manufacturerId', 'tagIds' => 'tags.id'] as $key => $field) {
            if ($feed[$key]) {
                $base->addFilter(new EqualsAnyFilter($field, $feed[$key]));
            }
        }
        if ($feed['productIds']) {
            $base->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [new EqualsAnyFilter('id', $feed['productIds']), new EqualsAnyFilter('parentId', $feed['productIds'])]));
        }
        if ($feed['excludedProductIds']) {
            $base->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsAnyFilter('id', $feed['excludedProductIds'])]));
            $base->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('parentId', null), new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsAnyFilter('parentId', $feed['excludedProductIds'])])]));
        }
        $last = null;
        do {
            $criteria = clone $base;
            if ($last) {
                $criteria->addFilter(new RangeFilter('id', [RangeFilter::GT => $last]));
            }
            $entities = $this->products->search($criteria, $context)->getEntities();
            $seoPaths = [];
            if ($entities->count() > 0) {
                $rows = $this->connection->fetchAllAssociative('SELECT foreign_key, seo_path_info FROM seo_url WHERE foreign_key IN (:ids) AND sales_channel_id = :channel AND language_id = :language AND route_name = :route AND is_canonical = 1 AND is_deleted = 0', ['ids' => array_values($entities->getIds()), 'channel' => Uuid::fromHexToBytes($feed['salesChannelId']), 'language' => Uuid::fromHexToBytes($domain['languageId']), 'route' => 'frontend.detail.page'], ['ids' => ArrayParameterType::STRING]);
                foreach ($rows as $row) {
                    $seoPaths[$row['foreign_key']] = $row['seo_path_info'];
                }
            }
            foreach ($entities as $product) {
                $last = $product->getId();
                yield $this->map($product, $feed, Validation::url($domain['url']), $seoPaths[$product->getId()] ?? null);
            }
            $count = $entities->count();
            unset($entities);
        } while ($count === 100);
    }
    /** @param array<string, mixed> $feed */
    private function map(SalesChannelProductEntity $p, array $feed, string $domain, ?string $seo): Product
    {
        $price = $p->getCalculatedPrices()->count() > 0 ? $p->getCalculatedPrices()->first() : $p->getCalculatedPrice();
        $category = $p->getCategories()?->first();
        $translated = $category?->getTranslated() ?? [];
        $breadcrumb = $translated['breadcrumb'] ?? [];
        $fields = [
            'productId' => $p->getId(), 'title' => (string) ($p->getTranslation('name') ?? ''),
            'productURL' => $domain . '/detail/' . $p->getId(), 'image' => (string) ($p->getCover()?->getMedia()?->getUrl() ?? ''),
            'price' => $price && is_finite($price->getUnitPrice()) ? number_format($price->getUnitPrice(), 2, '.', '') : '',
            'category_name' => implode(' > ', array_values($breadcrumb)) ?: (string) ($translated['name'] ?? ''),
            'availability' => $p->getAvailableStock() > 0 ? $feed['inStock'] : $feed['outOfStock'],
            'brand' => (string) ($p->getManufacturer()?->getTranslation('name') ?? ''),
            'MPN' => $p->getManufacturerNumber() ?? '', 'barcode' => $p->getEan() ?? '',
            'description' => trim(strip_tags((string) ($p->getTranslation('description') ?? ''))),
            'weight' => (string) (int) round(($p->getWeight() ?? 0) * 1000),
        ];
        if (is_string($seo) && $seo !== '') {
            $fields['productURL'] = $domain . '/' . ltrim($seo, '/');
        }
        foreach ($feed['mapping'] as $field => $source) {
            $fields[$field] = $this->source($p, $source);
        }
        // Arbitrary Rule Builder shipping is not approximated: omission uses confirmed official merchant rule.
        $override = $p->getTranslation('customFields') ?? [];
        foreach (['title', 'MPN', 'barcode', 'brand', 'color', 'size'] as $field) {
            $key = 'stpi_gh_bestprice_' . $field;
            if (isset($override[$key]) && is_scalar($override[$key])) {
                $fields[$field] = (string) $override[$key];
            }
        }
        if (isset($override['stpi_gh_bestprice_shipping'])) {
            $fields['Shipping'] = Decimal::round(Decimal::amount((string) $override['stpi_gh_bestprice_shipping']));
        }
        if ($p->getMinPurchase() !== 1 || $p->getPurchaseSteps() !== 1) {
            $fields['price'] = '';
        }
        return new Product($p->getId(), $fields);
    }
    private function source(SalesChannelProductEntity $p, string $source): string
    {
        if (str_starts_with($source, 'customFields.')) {
            $value = ($p->getTranslation('customFields') ?? [])[substr($source, 13)] ?? '';
            return is_scalar($value) ? (string) $value : '';
        }
        if (str_starts_with($source, 'property.')) {
            $values = [];
            foreach ([$p->getOptions(), $p->getProperties()] as $properties) {
                foreach ($properties ?? [] as $property) {
                    if ($property->getGroupId() === substr($source, 9)) {
                        $values[] = (string) $property->getTranslation('name');
                    }
                }
            }
            return implode(', ', array_unique($values));
        }
        return match ($source) {
            'name' => (string) $p->getTranslation('name'), 'description' => trim(strip_tags((string) $p->getTranslation('description'))),
            'ean' => $p->getEan() ?? '', 'manufacturerNumber' => $p->getManufacturerNumber() ?? '',
            'manufacturer' => (string) ($p->getManufacturer()?->getTranslation('name') ?? ''), default => '',
        };
    }
}
