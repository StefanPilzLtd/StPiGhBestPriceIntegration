<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Feed;

use StPiGhBestPriceIntegration\Security\Validation;

final class FeedWriter
{
    public const FIELDS = ['productId','title','productURL','image','price','category_name','availability','brand','MPN','barcode','description','size','color','weight','Shipping'];
    /**
     * @param iterable<Product> $products
     * @param null|callable(string, array{field:string,code:string,severity:string}):void $finding
     * @return array<string, int|string|false>
     */
    public function write(iterable $products, string $directory, ?callable $finding = null): array
    {
        if (realpath(dirname($directory)) !== realpath(sys_get_temp_dir())) {
            throw new \InvalidArgumentException('STPI_BESTPRICE_TEMP_DIRECTORY_REQUIRED');
        }
        $xml = new \XMLWriter();
        if (!$xml->openMemory()) {
            throw new \RuntimeException('STPI_BESTPRICE_WRITE_FAILED');
        }
        $xmlFile = fopen(sys_get_temp_dir() . '/' . basename($directory) . '/feed.xml', 'xb');
        $csv = fopen(sys_get_temp_dir() . '/' . basename($directory) . '/feed.csv', 'xb');
        $ids = new \SQLite3($directory . '/ids.sqlite');
        $ids->enableExceptions(true);
        $ids->exec('PRAGMA journal_mode=OFF');
        $ids->exec('PRAGMA synchronous=OFF');
        $ids->exec('BEGIN TRANSACTION');
        $ids->exec('CREATE TABLE ids (id TEXT PRIMARY KEY) WITHOUT ROWID');
        $insert = $ids->prepare('INSERT OR IGNORE INTO ids VALUES (:id)');
        if ($xmlFile === false || $csv === false || $insert === false) {
            throw new \RuntimeException('STPI_BESTPRICE_WRITE_FAILED');
        }
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('store');
        $xml->writeElement('date', (new \DateTimeImmutable())->format('Y-m-d H:i'));
        $xml->startElement('products');
        if (fputcsv($csv, self::FIELDS, ';', '"', '') === false) {
            throw new \RuntimeException('STPI_BESTPRICE_WRITE_FAILED');
        }
        $count = $rejected = $warnings = 0;
        $validator = new ProductValidator();
        try {
            foreach ($products as $product) {
                $issues = $validator->validate($product);
                $insert->bindValue(':id', $product->id, SQLITE3_TEXT);
                $insert->execute();
                if ($ids->changes() === 0) {
                    $issues[] = ['field' => 'productId', 'code' => 'duplicate', 'severity' => 'error'];
                }
                $invalid = false;
                foreach ($issues as $issue) {
                    if ($finding !== null) {
                        $finding($product->id, $issue);
                    }
                    $invalid = $invalid || $issue['severity'] === 'error';
                    if ($issue['severity'] === 'warning') {
                        ++$warnings;
                    }
                }
                if ($invalid) {
                    ++$rejected;
                    continue;
                }
                $xml->startElement('product');
                foreach (self::FIELDS as $field) {
                    if (isset($product->fields[$field]) && $product->fields[$field] !== '') {
                        $xml->writeElement($field, $product->fields[$field]);
                    }
                }
                $xml->endElement();
                if (fputcsv($csv, array_map(static fn (string $field) => Validation::csv($product->fields[$field] ?? ''), self::FIELDS), ';', '"', '') === false) {
                    throw new \RuntimeException('STPI_BESTPRICE_WRITE_FAILED');
                }
                if (++$count % 100 === 0) {
                    $this->flushXml($xml, $xmlFile);
                }
            }
            $xml->endElement();
            $xml->endElement();
            $xml->endDocument();
            $this->flushXml($xml, $xmlFile);
            if (!fflush($csv) || !fflush($xmlFile)) {
                throw new \RuntimeException('STPI_BESTPRICE_WRITE_FAILED');
            }
        } finally {
            fclose($csv);
            fclose($xmlFile);
            $ids->close();
            unset($insert);
            @unlink(sys_get_temp_dir() . '/' . basename($directory) . '/ids.sqlite');
        }
        if ($count === 0) {
            throw new \RuntimeException('STPI_BESTPRICE_NO_VALID_PRODUCTS');
        }
        return ['products' => $count, 'rejected' => $rejected, 'warnings' => $warnings,
            'qualityScore' => (int) floor(100 * $count / ($count + $rejected)),
            'xmlSha256' => hash_file('sha256', $directory . '/feed.xml'), 'csvSha256' => hash_file('sha256', $directory . '/feed.csv')];
    }
    /** @param resource $stream */
    private function flushXml(\XMLWriter $xml, $stream): void
    {
        $chunk = $xml->outputMemory(true);
        $length = strlen($chunk);
        for ($offset = 0; $offset < $length; $offset += $written) {
            $written = fwrite($stream, substr($chunk, $offset));
            if ($written === false || $written === 0) {
                throw new \RuntimeException('STPI_BESTPRICE_WRITE_FAILED');
            }
        }
    }
}
