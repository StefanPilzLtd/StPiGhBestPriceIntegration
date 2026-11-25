<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Pricing;

final class Decimal
{
    public static function amount(mixed $value, bool $negative = false): string
    {
        if (!is_string($value) || !preg_match($negative ? '/^-?\d{1,10}(?:\.\d{1,6})?$/' : '/^\d{1,10}(?:\.\d{1,6})?$/', $value)) {
            throw new \InvalidArgumentException('STPI_BESTPRICE_INVALID_DECIMAL');
        }
        return bcadd($value, '0', 6);
    }
    public static function round(string $value, int $scale = 2): string
    {
        if ($scale < 0 || $scale > 6) {
            throw new \InvalidArgumentException('STPI_BESTPRICE_INVALID_SCALE');
        }
        $offset = $scale === 0 ? '0.5' : '0.' . str_repeat('0', $scale) . '5';
        return bcadd($value, bccomp($value, '0', 6) < 0 ? '-' . $offset : $offset, $scale);
    }
    public static function ceil(string $value, int $scale = 2): string
    {
        $truncated = bcadd($value, '0', $scale);
        return bccomp($value, $truncated, 8) > 0 ? bcadd($truncated, $scale === 0 ? '1' : '0.' . str_repeat('0', $scale - 1) . '1', $scale) : $truncated;
    }
}
