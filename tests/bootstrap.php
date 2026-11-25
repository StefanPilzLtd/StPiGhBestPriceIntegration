<?php

declare(strict_types=1);
$autoload = getenv('SHOPWARE_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';
require $autoload;
spl_autoload_register(static function (string $class): void {
    $prefix = 'StPiGhBestPriceIntegration\\';
    if (str_starts_with($class, $prefix)) {
        $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});
