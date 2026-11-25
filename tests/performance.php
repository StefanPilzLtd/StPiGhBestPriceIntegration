<?php

declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use StPiGhBestPriceIntegration\Feed\{Product,FeedWriter};

$rows = [];
foreach ([100,1000,10000,50000,100000] as $count) {
    $dir = sys_get_temp_dir().'/bp-perf-'.bin2hex(random_bytes(6));
    mkdir($dir);
    $generator = (static function () use ($count) {
        for ($i = 0;$i < $count;++$i) {
            $id = str_pad((string)$i, 32, '0', STR_PAD_LEFT);
            yield new Product($id, ['productId' => $id,'title' => 'Synthetic Καλημέρα product '.$i,'productURL' => 'https://synthetic.invalid/product/'.$i,'image' => 'https://synthetic.invalid/image.png','price' => '123.45','category_name' => 'Synthetic > Catalog','availability' => 'Σε απόθεμα','brand' => 'Synthetic','MPN' => 'M-'.$i]);
        }
    })();
    try {
        $start = hrtime(true);
        $r = (new FeedWriter())->write($generator, $dir);
        $rows[] = ['products' => $count,'exported' => $r['products'],'durationSeconds' => round((hrtime(true) - $start) / 1e9, 4),'peakPhpMemoryBytes' => memory_get_peak_usage(true),'xmlBytes' => filesize($dir.'/feed.xml'),'csvBytes' => filesize($dir.'/feed.csv'),'scope' => 'serializer-only; no DAL, queue or network'];
    } finally {
        foreach (glob($dir.'/*') ?: [] as $f) {
            unlink($f);
        }rmdir($dir);
    }
}
echo json_encode(['php' => PHP_VERSION,'measurements' => $rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
