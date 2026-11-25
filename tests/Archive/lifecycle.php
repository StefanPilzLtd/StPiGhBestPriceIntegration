<?php

declare(strict_types=1);

$testDatabase = getenv('BP_ARCHIVE_TEST_DATABASE') ?: 'bestprice_archive';
if (getenv('BP_ISOLATED_TESTS') !== '1' || !str_starts_with($testDatabase, 'bestprice_') || !str_ends_with((string) getenv('DATABASE_URL'), '/' . $testDatabase)) {
    throw new RuntimeException('Dedicated BestPrice archive test database required');
}
$root = '/var/www/html';
$loader = require $root . '/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv($root . '/.env');
$_SERVER['PROJECT_ROOT'] = $_ENV['PROJECT_ROOT'] = $root;
$kernel = Shopware\Core\Framework\Adapter\Kernel\KernelFactory::create('prod', false, $loader);
$kernel->boot();
$c = $kernel->getContainer();
$db = $c->get(Doctrine\DBAL\Connection::class);
$fs = $c->get('shopware.filesystem.private');
$config = $c->get(Shopware\Core\System\SystemConfig\SystemConfigService::class);
$phase = $argv[1] ?? '';
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
if ($phase === 'seed') {
    $db->executeStatement('CREATE TABLE bp_archive_control (id INT PRIMARY KEY, value VARCHAR(32))');
    $db->insert('bp_archive_control', ['id' => 1, 'value' => 'preserve-core-and-others']);
    $ctx = Shopware\Core\Framework\Context::createCLIContext();
    $domain = $db->fetchAssociative('SELECT LOWER(HEX(id)) id, LOWER(HEX(sales_channel_id)) channelId FROM sales_channel_domain LIMIT 1');
    $country = $db->fetchOne('SELECT LOWER(HEX(id)) FROM country WHERE active=1 LIMIT 1');
    $c->get('console.command_loader')->get('stpi:bestprice:feed:build');
    // Use the native DAL in the test; no production entitlement override is installed.
    $ctx->scope(Shopware\Core\Framework\Context::SYSTEM_SCOPE, static function () use ($c, $ctx, $domain, $country): void {
        $id = str_repeat('7a', 16);
        $c->get('stpi_gh_bp_feed.repository')->upsert([['id' => $id, 'name' => 'Archive lifecycle fixture', 'salesChannelId' => $domain['channelId'], 'enabled' => false, 'settings' => ['domainId' => $domain['id'], 'countryId' => $country, 'revision' => str_repeat('7b', 16), 'token' => str_repeat('7c', 32)]]], $ctx);
    });
    $fs->write('stpi-gh-bestprice/lifecycle-marker.txt', 'synthetic lifecycle fixture');
    $fs->write('archive-control/marker.txt', 'preserve unrelated private file');
    $config->set('StPiGhBestPriceIntegration.config.lifecycleTest', 'synthetic');
    $config->set('BestPriceArchiveControl.keep', 'preserve unrelated config');
    file_put_contents($root . '/lifecycle-baseline.json', json_encode(['salesChannels' => (int) $db->fetchOne('SELECT COUNT(*) FROM sales_channel'), 'countries' => (int) $db->fetchOne('SELECT COUNT(*) FROM country')], JSON_THROW_ON_ERROR));
} elseif ($phase === 'kept' || $phase === 'reinstalled' || $phase === 'deactivated') {
    $assert($db->createSchemaManager()->tablesExist(['stpi_gh_bp_feed', 'stpi_gh_bp_build', 'stpi_gh_bp_conversion']), 'Keep-data tables lost');
    $assert((int) $db->fetchOne('SELECT COUNT(*) FROM stpi_gh_bp_feed') === 1, 'Keep-data feed lost');
    $assert($fs->fileExists('stpi-gh-bestprice/lifecycle-marker.txt'), 'Keep-data file lost');
    $assert(json_decode((string) $db->fetchOne("SELECT configuration_value FROM system_config WHERE configuration_key='StPiGhBestPriceIntegration.config.lifecycleTest'"), true)['_value'] === 'synthetic', 'Keep-data config lost');
    if ($phase === 'deactivated') {
        $assert(!(bool) $db->fetchOne("SELECT active FROM plugin WHERE name='StPiGhBestPriceIntegration'"), 'Deactivation not applied');
    }
} elseif ($phase === 'removed') {
    foreach (['stpi_gh_bp_feed', 'stpi_gh_bp_build', 'stpi_gh_bp_conversion'] as $table) {
        $assert(!$db->createSchemaManager()->tablesExist([$table]), 'Plugin table retained: ' . $table);
    }
    $assert(!$fs->fileExists('stpi-gh-bestprice/lifecycle-marker.txt'), 'Remove-data file retained');
    $assert(!$db->fetchOne("SELECT 1 FROM system_config WHERE configuration_key LIKE 'StPiGhBestPriceIntegration.%'"), 'Remove-data configuration retained');
} else {
    throw new RuntimeException('Unknown lifecycle phase');
}
$baseline = json_decode(file_get_contents($root . '/lifecycle-baseline.json'), true, 512, JSON_THROW_ON_ERROR);
$assert($db->fetchOne('SELECT value FROM bp_archive_control WHERE id=1') === 'preserve-core-and-others', 'Unrelated table changed');
$assert($fs->fileExists('archive-control/marker.txt'), 'Unrelated private file changed');
$assert(json_decode((string) $db->fetchOne("SELECT configuration_value FROM system_config WHERE configuration_key='BestPriceArchiveControl.keep'"), true)['_value'] === 'preserve unrelated config', 'Unrelated configuration changed');
$assert((int) $db->fetchOne('SELECT COUNT(*) FROM sales_channel') === $baseline['salesChannels'], 'Core channels changed');
$assert((int) $db->fetchOne('SELECT COUNT(*) FROM country') === $baseline['countries'], 'Core countries changed');
echo json_encode(['phase' => $phase, 'passed' => true, 'database' => $testDatabase, 'php' => PHP_VERSION], JSON_THROW_ON_ERROR) . "\n";
