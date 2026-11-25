<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Configuration;

use StPiGhBestPriceIntegration\Security\Validation;
use StPiGhBestPriceIntegration\Feed\ProductValidator;

final class FeedSettings
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function validate(array $input): array
    {
        $out = [];
        $out['name'] = Validation::text($input['name'] ?? '');
        if ($out['name'] === '') {
            throw new \InvalidArgumentException('STPI_BESTPRICE_NAME_REQUIRED');
        }
        foreach (['salesChannelId', 'domainId', 'countryId'] as $key) {
            $out[$key] = Validation::id($input[$key] ?? null);
        }
        foreach (['productIds', 'categoryIds', 'manufacturerIds', 'tagIds', 'excludedProductIds'] as $key) {
            $out[$key] = Validation::ids($input[$key] ?? []);
        }
        $out['productStreamId'] = empty($input['productStreamId']) ? null : Validation::id($input['productStreamId']);
        if (!$out['productStreamId'] && $out['productIds'] === [] && $out['categoryIds'] === [] && $out['manufacturerIds'] === [] && $out['tagIds'] === []) {
            throw new \InvalidArgumentException('STPI_BESTPRICE_EXPLICIT_SELECTION_REQUIRED');
        }
        foreach (['enabled', 'trackingEnabled', 'shippingFallbackConfirmed'] as $key) {
            if (isset($input[$key]) && !is_bool($input[$key])) {
                throw new \InvalidArgumentException('STPI_BESTPRICE_INVALID_BOOLEAN');
            }
            $out[$key] = $input[$key] ?? false;
        }
        if (!$out['shippingFallbackConfirmed']) {
            throw new \InvalidArgumentException('STPI_BESTPRICE_SHIPPING_CONFIRMATION_REQUIRED');
        }
        $out['merchantId'] = Validation::text($input['merchantId'] ?? '', 100);
        $out['trackingKey'] = Validation::text($input['trackingKey'] ?? '', 100);
        if ($out['trackingKey'] !== '' && !preg_match('/^BP-[A-Za-z0-9-]{4,90}$/D', $out['trackingKey'])) {
            throw new \InvalidArgumentException('STPI_BESTPRICE_INVALID_TRACKING_KEY');
        }
        $interval = $input['intervalMinutes'] ?? 60;
        if (!is_int($interval) || $interval < 15 || $interval > 10080) {
            throw new \InvalidArgumentException('STPI_BESTPRICE_INVALID_INTERVAL');
        }
        $out['intervalMinutes'] = $interval;
        $out['inStock'] = $input['inStock'] ?? 'Σε απόθεμα';
        $out['outOfStock'] = $input['outOfStock'] ?? 'Κατόπιν παραγγελίας';
        foreach (['inStock', 'outOfStock'] as $key) {
            if (!in_array($out[$key], ProductValidator::AVAILABILITY, true)) {
                throw new \InvalidArgumentException('STPI_BESTPRICE_INVALID_AVAILABILITY');
            }
        }
        $out['mapping'] = [];
        $mapping = $input['mapping'] ?? [];
        if (!is_array($mapping)) {
            throw new \InvalidArgumentException('STPI_BESTPRICE_INVALID_MAPPING');
        }
        foreach ($mapping as $field => $source) {
            if ($source === '') {
                continue;
            }
            if (!in_array($field, ['title', 'brand', 'barcode', 'MPN', 'description', 'color', 'size'], true)
                || !is_string($source) || !preg_match('/^(?:name|description|ean|manufacturerNumber|manufacturer|customFields\.[A-Za-z0-9_]{1,100}|property\.[a-f0-9]{32})$/D', $source)) {
                throw new \InvalidArgumentException('STPI_BESTPRICE_INVALID_MAPPING');
            }
            $out['mapping'][$field] = $source;
        }
        // The service guard rejects tracking until its implementation and transmission gate are approved.
        return $out;
    }
}
