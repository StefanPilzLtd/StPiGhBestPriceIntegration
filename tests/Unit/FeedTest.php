<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use StPiGhBestPriceIntegration\Feed\{Product, ProductValidator, FeedWriter};
use StPiGhBestPriceIntegration\Security\Validation;
use StPiGhBestPriceIntegration\Configuration\FeedSettings;

final class FeedTest extends TestCase
{
    public static function fields(): array
    {
        return ['productId' => str_repeat('a', 32),'title' => 'ACME Καλημέρα & <product> "quoted"','productURL' => 'https://shop.example/product','image' => 'https://shop.example/image.jpg','price' => '123.45','category_name' => 'Root > Shoes','brand' => 'ACME','availability' => 'Σε απόθεμα','barcode' => '4006381333931','MPN' => 'A-1'];
    }
    public static function gtins(): array
    {
        return [['4006381333931',true],['4006381333932',false],['96385074',true],['123',false],['0000000000000',true],['012345678905',true],['1234<script>',false]];
    }
    #[DataProvider('gtins')]
    public function testGtinChecksum(string $value, bool $valid): void
    {
        self::assertSame($valid, (new ProductValidator())->gtin($value));
    }
    public static function badFields(): array
    {
        return [['price','0.00'],['price','-1.00'],['price','1,00'],['price','1e3'],['productURL','file:///etc/passwd'],['productURL','http://user:pass@shop.example/'],['image','javascript:alert(1)'],['availability','invented'],['title',"Bad\x01title"],['brand',''],['barcode','4006381333932']];
    }
    #[DataProvider('badFields')]
    public function testInvalidProductsHaveErrors(string $field, string $value): void
    {
        $f = self::fields();
        $f[$field] = $value;
        $errors = (new ProductValidator())->validate(new Product($f['productId'], $f));
        self::assertNotEmpty(array_filter($errors, static fn ($e) => $e['field'] === $field && $e['severity'] === 'error'));
    }
    public static function formulae(): array
    {
        return [['=WEBSERVICE("https://bad")',"'=WEBSERVICE(\"https://bad\")"],['\t@SUM(1)',"'	@SUM(1)"],['+1',"'+1"],['-1',"'-1"],['Safe','Safe']];
    }
    #[DataProvider('formulae')]
    public function testCsvFormulaInjection(string $input, string $expected): void
    {
        self::assertSame($expected, Validation::csv(str_replace('\\t', "\t", $input)));
    }
    public function testStreamingEscapingDuplicateAndCsv(): void
    {
        $dir = sys_get_temp_dir().'/stpi-bp-'.bin2hex(random_bytes(6));
        mkdir($dir);
        $errors = [];
        try {
            $f = self::fields();
            $f['title'] = '=1+1';
            $r = (new FeedWriter())->write([new Product($f['productId'], $f),new Product($f['productId'], $f)], $dir, static function ($id, $error) use (&$errors) {
                $errors[] = $error;
            });
            self::assertSame(1, $r['products']);
            self::assertSame(1, $r['rejected']);
            self::assertSame(50, $r['qualityScore']);
            $dom = new \DOMDocument();
            self::assertTrue($dom->load($dir.'/feed.xml', LIBXML_NONET));
            self::assertSame('=1+1', $dom->getElementsByTagName('title')->item(0)->textContent);
            $handle = fopen($dir.'/feed.csv', 'r');
            fgetcsv($handle, 0, ';', '"', '');
            $row = fgetcsv($handle, 0, ';', '"', '');
            fclose($handle);
            self::assertSame("'=1+1", $row[1]);
            self::assertFalse(file_exists($dir.'/ids.sqlite'));
            self::assertSame(hash_file('sha256', $dir.'/feed.xml'), $r['xmlSha256']);
        } finally {
            foreach (glob($dir.'/*') ?: [] as $file) {
                unlink($file);
            }rmdir($dir);
        }
    }
    public function testXmlRoundTripsGreekAndReservedCharacters(): void
    {
        $dir = sys_get_temp_dir().'/stpi-bp-'.bin2hex(random_bytes(6));
        mkdir($dir);
        $f = self::fields();
        try {
            (new FeedWriter())->write([new Product($f['productId'], $f)], $dir);
            $xml = new \DOMDocument();
            $xml->load($dir.'/feed.xml', LIBXML_NONET);
            self::assertSame($f['title'], $xml->getElementsByTagName('title')->item(0)->textContent);
        } finally {
            foreach (glob($dir.'/*') ?: [] as $file) {
                unlink($file);
            }rmdir($dir);
        }
    }
    public function testEmptyOrAllInvalidFeedFails(): void
    {
        $dir = sys_get_temp_dir().'/stpi-bp-'.bin2hex(random_bytes(6));
        mkdir($dir);
        try {
            $this->expectException(\RuntimeException::class);
            (new FeedWriter())->write([], $dir);
        } finally {
            foreach (glob($dir.'/*') ?: [] as $file) {
                unlink($file);
            }rmdir($dir);
        }
    }
    public function testNoImplicitAllProductsAndNoUnconfirmedShipping(): void
    {
        $base = ['name' => 'Test','salesChannelId' => str_repeat('a', 32),'domainId' => str_repeat('b', 32),'countryId' => str_repeat('c', 32),'shippingFallbackConfirmed' => true];
        try {
            FeedSettings::validate($base);
            self::fail('Implicit all-products selection allowed');
        } catch (\InvalidArgumentException $e) {
            self::assertSame('STPI_BESTPRICE_EXPLICIT_SELECTION_REQUIRED', $e->getMessage());
        }
        $base['productIds'] = [str_repeat('d', 32)];
        $base['shippingFallbackConfirmed'] = false;
        $this->expectException(\InvalidArgumentException::class);
        FeedSettings::validate($base);
    }
    public function testMappingCannotAlterPriceIdOrExecuteCode(): void
    {
        $base = ['name' => 'Test','salesChannelId' => str_repeat('a', 32),'domainId' => str_repeat('b', 32),'countryId' => str_repeat('c', 32),'productIds' => [str_repeat('d', 32)],'shippingFallbackConfirmed' => true,'mapping' => ['price' => 'customFields.malicious']];
        $this->expectException(\InvalidArgumentException::class);
        FeedSettings::validate($base);
    }
    public function testCacheBustingQueryAllowedButSessionQueryRejected(): void
    {
        $fields = self::fields();
        $fields['image'] .= '?ts=12345';
        self::assertSame([], (new ProductValidator())->validate(new Product($fields['productId'], $fields)));
        $fields['image'] = 'https://shop.example/image.png?access_token=private';
        self::assertNotEmpty((new ProductValidator())->validate(new Product($fields['productId'], $fields)));
    }
}
