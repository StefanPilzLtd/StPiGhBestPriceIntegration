<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Tests\Integration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use StPiGhBestPriceIntegration\Configuration\FeedRepository;
use StPiGhBestPriceIntegration\Entitlements\{EntitlementService, FeatureAccessGuard, PurchaseAdapter};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class BackendTest extends TestCase
{
    private Connection $db;
    private $container;
    private Context $context;
    private FeedRepository $feeds;
    protected function setUp(): void
    {
        $this->container = $GLOBALS['bpKernel']->getContainer();
        $this->db = $this->container->get(Connection::class);
        $this->db->beginTransaction();
        $this->context = Context::createCLIContext();
        $this->db->executeStatement('DELETE FROM stpi_gh_bp_feed');
        $adapter = new class () implements PurchaseAdapter {
            public function active(string $id): bool
            {
                return false;
            }
        };
        $this->feeds = new FeedRepository($this->db, $this->container->get('stpi_gh_bp_feed.repository'), new FeatureAccessGuard(new EntitlementService($adapter)));
    }
    protected function tearDown(): void
    {
        $this->db->rollBack();
    }
    private function config(): array
    {
        $domain = $this->db->fetchAssociative('SELECT LOWER(HEX(id)) id, LOWER(HEX(sales_channel_id)) channelId FROM sales_channel_domain LIMIT 1');
        $country = $this->db->fetchOne('SELECT LOWER(HEX(id)) FROM country WHERE active=1 LIMIT 1');
        return ['name' => 'Synthetic isolated feed','salesChannelId' => $domain['channelId'],'domainId' => $domain['id'],'countryId' => $country,'productIds' => [Uuid::randomHex()],'enabled' => true,'shippingFallbackConfirmed' => true];
    }
    public function testMigrationAndDalPersistAndRetainToken(): void
    {
        $saved = $this->feeds->save(null, $this->config(), $this->context);
        self::assertSame(64, strlen($saved['token']));
        self::assertTrue($this->feeds->isPrimary($saved['id']));
        $updated = $this->feeds->save($saved['id'], array_replace($saved, ['name' => 'Renamed']), $this->context);
        self::assertSame($saved['token'], $updated['token']);
        self::assertNotSame($saved['revision'], $updated['revision']);
        $entity = $this->container->get('stpi_gh_bp_feed.repository')->search(new Criteria([$saved['id']]), $this->context)->first();
        self::assertSame('Renamed', $entity->getName());
        self::assertNotNull($entity->getCreatedAt());
        self::assertArrayNotHasKey('token', $this->feeds->list()['items'][0]);
    }
    public function testMultipleConfigsCannotBypassBasic(): void
    {
        $this->feeds->save(null, $this->config(), $this->context);
        $this->expectException(AccessDeniedHttpException::class);
        $this->feeds->save(null, $this->config(), $this->context);
    }
    public function testTrackingConfigRequiresEntitlement(): void
    {
        $input = $this->config();
        $input['trackingEnabled'] = true;
        $this->expectException(AccessDeniedHttpException::class);
        $this->feeds->save(null, $input, $this->context);
    }
    public function testDisabledFeedCannotBuildAndDeleteCascades(): void
    {
        $input = $this->config();
        $input['enabled'] = false;
        $f = $this->feeds->save(null, $input, $this->context);
        $this->db->insert('stpi_gh_bp_build', ['id' => Uuid::randomBytes(),'feed_id' => Uuid::fromHexToBytes($f['id']),'status' => 'success','created_at' => gmdate('Y-m-d H:i:s')]);
        try {
            $this->feeds->assertBuildable($f['id']);
            self::fail('Disabled build allowed');
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
        }
        $this->feeds->delete($f['id'], $this->context);
        self::assertSame(0, (int)$this->db->fetchOne('SELECT COUNT(*) FROM stpi_gh_bp_build'));
    }
    public function testDueFeedsAndBoundedPagination(): void
    {
        $f = $this->feeds->save(null, $this->config(), $this->context);
        self::assertContains($f['id'], $this->feeds->due());
        self::assertSame(100, $this->feeds->list(1, 10000)['limit']);
        $this->db->insert('stpi_gh_bp_build', ['id' => Uuid::randomBytes(),'feed_id' => Uuid::fromHexToBytes($f['id']),'status' => 'failed','created_at' => gmdate('Y-m-d H:i:s')]);
        self::assertNotContains($f['id'], $this->feeds->due());
    }
    public function testGenericApiCannotWriteProtectedSettings(): void
    {
        $definition = $this->container->get(\Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry::class)->getByEntityName('stpi_gh_bp_feed');
        self::assertFalse($definition->getField('settings')->is(\Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware::class));
    }
    public function testInactivePluginCannotBuildInStaleWorker(): void
    {
        $f = $this->feeds->save(null, $this->config(), $this->context);
        $this->db->update('plugin', ['active' => 0], ['name' => 'StPiGhBestPriceIntegration']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\ConflictHttpException::class);
        $this->feeds->assertBuildable($f['id']);
    }
    public function testPublicExtraFeedStopsImmediatelyAfterEntitlementRevocation(): void
    {
        $purchase = new class () implements PurchaseAdapter {
            public bool $enabled = true;
            public function active(string $id): bool
            {
                return $this->enabled && $id === 'advanced';
            }
        };
        $feeds = new FeedRepository($this->db, $this->container->get('stpi_gh_bp_feed.repository'), new FeatureAccessGuard(new EntitlementService($purchase)));
        $feeds->save(null, $this->config(), $this->context);
        $extra = $feeds->save(null, $this->config(), $this->context);
        self::assertFalse($feeds->isPrimary($extra['id']));
        $purchase->enabled = false;
        $filesystem = $this->createMock(\League\Flysystem\FilesystemOperator::class);
        $filesystem->expects(self::never())->method('readStream');
        $controller = new \StPiGhBestPriceIntegration\Controller\PublicFeedController($feeds, new \StPiGhBestPriceIntegration\Feed\FeedStorage($filesystem, $this->db));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->download($extra['id'], $extra['token'], 'xml', new \Symfony\Component\HttpFoundation\Request());
    }
    public function testBothFilesPublishThroughOneDatabasePointer(): void
    {
        $this->publicationScenario(false, false);
    }
    public function testFailedSecondUploadRetainsPreviousPublishedBuild(): void
    {
        $this->publicationScenario(true, false);
    }
    public function testConfigurationChangeRollsBackPublicationAndBuildStatus(): void
    {
        $this->publicationScenario(false, true);
    }
    private function publicationScenario(bool $uploadFailure, bool $revisionChanged): void
    {
        $feed = $this->feeds->save(null, $this->config(), $this->context);
        $oldId = Uuid::randomHex();
        $newId = Uuid::randomHex();
        foreach ([$oldId => 'success', $newId => 'running'] as $id => $status) {
            $this->db->insert('stpi_gh_bp_build', ['id' => Uuid::fromHexToBytes($id), 'feed_id' => Uuid::fromHexToBytes($feed['id']), 'status' => $status, 'manifest' => json_encode(['buildId' => $id], JSON_THROW_ON_ERROR), 'created_at' => gmdate('Y-m-d H:i:s')]);
        }
        $this->db->update('stpi_gh_bp_feed', ['published_build_id' => Uuid::fromHexToBytes($oldId)], ['id' => Uuid::fromHexToBytes($feed['id'])]);
        $filesystem = $this->createMock(\League\Flysystem\FilesystemOperator::class);
        $uploads = [];
        $filesystem->expects(self::exactly(2))->method('writeStream')->willReturnCallback(static function (string $path, $stream) use (&$uploads, $uploadFailure): void {
            $uploads[] = [$path, stream_get_contents($stream)];
            if ($uploadFailure && count($uploads) === 2) {
                throw new \RuntimeException('Synthetic storage failure');
            }
        });
        $filesystem->expects(($uploadFailure || $revisionChanged) ? self::once() : self::never())->method('deleteDirectory');
        $storage = new \StPiGhBestPriceIntegration\Feed\FeedStorage($filesystem, $this->db);
        $directory = sys_get_temp_dir() . '/stpi-bp-publish-' . Uuid::randomHex();
        mkdir($directory, 0700);
        file_put_contents($directory . '/feed.xml', '<store/>');
        file_put_contents($directory . '/feed.csv', 'productId;title');
        $stats = ['buildId' => $newId, 'revision' => $revisionChanged ? Uuid::randomHex() : $feed['revision'], 'products' => 1, 'rejected' => 0, 'durationMs' => 1];
        try {
            try {
                $storage->publish($feed['id'], $newId, $directory, $stats);
                self::assertFalse($uploadFailure || $revisionChanged, 'Failed publication unexpectedly succeeded');
            } catch (\RuntimeException $e) {
                self::assertTrue($uploadFailure || $revisionChanged, $e->getMessage());
            }
            self::assertSame($uploadFailure || $revisionChanged ? $oldId : $newId, $storage->current($feed['id'])['buildId']);
            self::assertSame($uploadFailure || $revisionChanged ? 'running' : 'success', $this->db->fetchOne('SELECT status FROM stpi_gh_bp_build WHERE id = :id', ['id' => Uuid::fromHexToBytes($newId)]));
            self::assertSame('<store/>', $uploads[0][1]);
            self::assertSame('productId;title', $uploads[1][1]);
        } finally {
            unlink($directory . '/feed.xml');
            unlink($directory . '/feed.csv');
            rmdir($directory);
        }
    }
}
