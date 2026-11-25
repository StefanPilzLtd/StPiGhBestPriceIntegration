<?php

declare(strict_types=1);
if (getenv('BP_ISOLATED_TESTS') !== '1' || !str_contains((string) getenv('DATABASE_URL'), 'bestprice')) {
    throw new RuntimeException('Disposable BestPrice test database required.');
}
$root = getenv('SHOPWARE_ROOT') ?: '/var/www/html';
$loader = require $root . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv($root . '/.env');
$_SERVER['PROJECT_ROOT'] = $_ENV['PROJECT_ROOT'] = $root;
$kernel = Shopware\Core\Framework\Adapter\Kernel\KernelFactory::create('prod', false, $loader);
$kernel->boot();
$GLOBALS['bpKernel'] = $kernel;
