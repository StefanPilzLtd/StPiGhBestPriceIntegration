<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Security;

use Shopware\Core\Framework\Uuid\Uuid;

final class Validation
{
    public static function id(mixed $value): string
    {
        if (!is_string($value) || !Uuid::isValid($value)) {
            throw new \InvalidArgumentException('STPI_BESTPRICE_INVALID_ID');
        }
        return strtolower($value);
    }
    /**
     * @return list<string>
     */
    public static function ids(mixed $values): array
    {
        if (!is_array($values) || !array_is_list($values) || count($values) > 1000) {
            throw new \InvalidArgumentException('STPI_BESTPRICE_INVALID_SELECTION');
        }
        return array_values(array_unique(array_map(self::id(...), $values)));
    }
    public static function url(mixed $value): string
    {
        if (!is_string($value) || strlen($value) > 2000 || !filter_var($value, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('STPI_BESTPRICE_INVALID_URL');
        }
        $parts = parse_url($value);
        if (!in_array($parts['scheme'] ?? '', ['https', 'http'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('STPI_BESTPRICE_INVALID_URL');
        }
        return rtrim($value, '/');
    }
    public static function text(mixed $value, int $max = 255): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            throw new \InvalidArgumentException('STPI_BESTPRICE_INVALID_TEXT');
        }
        return trim($value);
    }
    public static function csv(mixed $value): string
    {
        $text = (string) $value;
        return preg_match('/^[\s\x00-\x1f]*[=+@-]/u', $text) ? "'" . $text : $text;
    }
}
