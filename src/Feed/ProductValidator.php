<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Feed;

final class ProductValidator
{
    public const REQUIRED = ['productId', 'title', 'productURL', 'image', 'price', 'category_name', 'availability', 'brand'];
    public const AVAILABILITY = ['Σε απόθεμα', 'Παράδοση σε 1-3 ημέρες', 'Παράδοση σε 4-7 ημέρες', 'Παράδοση σε 4-10 ημέρες', 'Παράδοση σε 8-14 ημέρες', 'Παράδοση σε 15-30 ημέρες', 'Κατόπιν παραγγελίας', 'Προπαραγγελία', 'Εξαντλήθηκε'];
    /** @return list<array{field: string, code: string, severity: string}> */
    public function validate(Product $product): array
    {
        $errors = [];
        $add = static function (string $field, string $code, string $severity = 'error') use (&$errors): void {
            $errors[] = ['field' => $field, 'code' => $code, 'severity' => $severity];
        };
        foreach (self::REQUIRED as $field) {
            if (trim((string) ($product->fields[$field] ?? '')) === '') {
                $add($field, 'required');
            }
        }
        foreach ($product->fields as $key => $value) {
            if (!mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
                $add($key, 'encoding');
            }
        }
        foreach (['productURL', 'image'] as $field) {
            $url = (string) ($product->fields[$field] ?? '');
            if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http'], true) || parse_url($url, PHP_URL_USER) !== null) {
                $add($field, 'url');
            }
        }
        foreach (['productURL', 'image'] as $field) {
            $query = [];
            parse_str((string) parse_url($product->fields[$field] ?? '', PHP_URL_QUERY), $query);
            if (array_diff(array_keys($query), ['ts', 'v', 'version', 'width', 'height', 'format', 'quality']) !== []) {
                $add($field, 'url');
            }
        }
        $price = $product->fields['price'] ?? '';
        if (!preg_match('/^\d{1,10}\.\d{2}$/D', $price) || bccomp($price, '0', 2) <= 0) {
            $add('price', 'price');
        }
        if (!in_array($product->fields['availability'] ?? '', self::AVAILABILITY, true)) {
            $add('availability', 'availability');
        }
        if (mb_strlen($product->fields['title'] ?? '') > 255) {
            $add('title', 'length');
        }
        $barcode = $product->fields['barcode'] ?? '';
        if ($barcode !== '' && !$this->gtin($barcode)) {
            $add('barcode', 'gtin');
        }
        if (($product->fields['MPN'] ?? '') === '') {
            $add('MPN', 'recommended', 'warning');
        }
        if (isset($product->fields['Shipping']) && !preg_match('/^\d{1,10}\.\d{2}$/D', $product->fields['Shipping'])) {
            $add('Shipping', 'shipping');
        }
        return $errors;
    }
    public function gtin(string $value): bool
    {
        if (!preg_match('/^(?:\d{8}|\d{12}|\d{13}|\d{14})$/D', $value)) {
            return false;
        }
        $digits = str_split(substr($value, 0, -1));
        $sum = 0;
        foreach (array_reverse($digits) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 3 : 1);
        }
        return (10 - $sum % 10) % 10 === (int) substr($value, -1);
    }
}
