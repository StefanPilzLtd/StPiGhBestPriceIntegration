<?php

declare(strict_types=1);
require dirname(__DIR__).'/integration-bootstrap.php';
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

$c = $GLOBALS['bpKernel']->getContainer();
$db = $c->get(Doctrine\DBAL\Connection::class);
$ctx = Context::createCLIContext();
$domain = $db->fetchAssociative('SELECT LOWER(HEX(id)) id, LOWER(HEX(sales_channel_id)) channelId FROM sales_channel_domain LIMIT 1');
$tax = $db->fetchOne('SELECT LOWER(HEX(id)) FROM tax ORDER BY tax_rate DESC LIMIT 1');
$country = $db->fetchOne("SELECT LOWER(HEX(id)) FROM country WHERE iso='DE' AND active=1 LIMIT 1");
$ids = ['product' => str_repeat('1a', 16),'manufacturer' => str_repeat('1b', 16),'category' => str_repeat('1c', 16),'media' => str_repeat('1d', 16),'productMedia' => str_repeat('1e', 16)];
$c->get('product_manufacturer.repository')->upsert([['id' => $ids['manufacturer'],'name' => 'Synthetic BestPrice Fixture']], $ctx);
$c->get('category.repository')->upsert([['id' => $ids['category'],'name' => 'Synthetic Electronics','type' => 'page']], $ctx);
$c->get('media.repository')->upsert([['id' => $ids['media'],'fileName' => 'bestprice-synthetic','fileExtension' => 'png','mimeType' => 'image/png','path' => 'media/bestprice-synthetic.png']], $ctx);
$shopRoot = getenv('SHOPWARE_ROOT') ?: '/var/www/html';
@mkdir($shopRoot . '/public/media', 0775, true);
file_put_contents($shopRoot . '/public/media/bestprice-synthetic.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO/a9S0AAAAASUVORK5CYII='));
$c->get('product.repository')->upsert([['id' => $ids['product'],'name' => 'Synthetic BestPrice Camera','productNumber' => 'BP-SYNTHETIC-CAMERA','stock' => 12,'active' => true,'taxId' => $tax,'manufacturerId' => $ids['manufacturer'],'manufacturerNumber' => 'BP-SYN-1','ean' => '4006381333931','price' => [['currencyId' => Defaults::CURRENCY,'net' => 100,'gross' => 119,'linked' => false]],'categories' => [['id' => $ids['category']]],'visibilities' => [['salesChannelId' => $domain['channelId'],'visibility' => 30]],'media' => [['id' => $ids['productMedia'],'mediaId' => $ids['media'],'position' => 0]],'coverId' => $ids['productMedia']]], $ctx);
$feed = ['name' => 'Synthetic BestPrice Feed','salesChannelId' => $domain['channelId'],'domainId' => $domain['id'],'countryId' => $country,'productIds' => [$ids['product']],'enabled' => true,'shippingFallbackConfirmed' => true];
file_put_contents(__DIR__.'/../../artifacts/fixture.json', json_encode(['productId' => $ids['product'],'config' => $feed], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo "Synthetic fixture created in disposable bestprice database.\n";
