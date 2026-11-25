<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Feed;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use StPiGhBestPriceIntegration\Catalog\ProductCatalog;
use StPiGhBestPriceIntegration\Configuration\FeedRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final readonly class FeedBuilder
{
    public function __construct(private FeedRepository $feeds, private ProductCatalog $catalog, private FeedWriter $writer, private FeedStorage $storage, private Connection $connection, private LoggerInterface $logger)
    {
    }
    /** @return array<string,mixed> */
    public function build(string $id): array
    {
        $feed = $this->feeds->assertBuildable($id);
        $lockName = 'stpi_gh_bp_build_' . $id;
        if ((int) $this->connection->fetchOne('SELECT GET_LOCK(:name,0)', ['name' => $lockName]) !== 1) {
            throw new ConflictHttpException('STPI_BESTPRICE_BUILD_RUNNING');
        }
        $buildId = Uuid::randomHex();
        $target = sys_get_temp_dir() . '/stpi-gh-bestprice-' . $buildId;
        $fs = new Filesystem();
        $errors = null;
        $inserted = false;
        $start = hrtime(true);
        try {
            $fs->mkdir(sys_get_temp_dir() . '/stpi-gh-bestprice-' . $buildId, 0700);
            $errors = fopen(sys_get_temp_dir() . '/stpi-gh-bestprice-' . $buildId . '/errors.ndjson', 'xb');
            if ($errors === false) {
                throw new \RuntimeException('STPI_BESTPRICE_WRITE_FAILED');
            }
            $this->connection->insert('stpi_gh_bp_build', ['id' => Uuid::fromHexToBytes($buildId), 'feed_id' => Uuid::fromHexToBytes($id), 'status' => 'running', 'created_at' => gmdate('Y-m-d H:i:s')]);
            $inserted = true;
            $stats = $this->writer->write($this->catalog->stream($feed), $target, static function (string $productId, array $finding) use ($errors): void {
                if (fwrite($errors, json_encode(['productId' => $productId] + $finding, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n") === false) {
                    throw new \RuntimeException('STPI_BESTPRICE_WRITE_FAILED');
                }
            });
            fclose($errors);
            $errors = null;
            $fresh = $this->feeds->assertBuildable($id);
            if ($fresh['revision'] !== $feed['revision']) {
                throw new ConflictHttpException('STPI_BESTPRICE_CONFIGURATION_CHANGED');
            }
            $stats['buildId'] = $buildId;
            $stats['generatedAt'] = gmdate(DATE_ATOM);
            $stats['revision'] = $feed['revision'];
            $stats['durationMs'] = (int) ((hrtime(true) - $start) / 1000000);
            $this->storage->saveErrors($id, $target . '/errors.ndjson');
            $this->storage->publish($id, $buildId, $target, $stats);
            try {
                $this->storage->prune($id, $buildId);
            } catch (\League\Flysystem\FilesystemException) { /* Published data stays valid if optional cleanup is unavailable. */
            }
            return $stats;
        } catch (\Throwable $e) {
            if (is_resource($errors)) {
                fclose($errors);
            }
            if (is_file($target . '/errors.ndjson')) {
                try {
                    $this->storage->saveErrors($id, $target . '/errors.ndjson');
                } catch (\Throwable) {
                    $this->logger->warning('STPI_BESTPRICE_DIAGNOSTIC_STORAGE_FAILED', ['feedId' => $id, 'buildId' => $buildId]);
                }
            }
            $code = preg_match('/^STPI_BESTPRICE_[A-Z_]+$/D', $e->getMessage()) ? $e->getMessage() : 'STPI_BESTPRICE_BUILD_FAILED';
            if ($inserted) {
                $this->connection->update('stpi_gh_bp_build', ['status' => 'failed', 'error_code' => $code, 'duration_ms' => (int) ((hrtime(true) - $start) / 1000000)], ['id' => Uuid::fromHexToBytes($buildId)]);
            }
            throw new \RuntimeException($code, 0, $e);
        } finally {
            try {
                $fs->remove(sys_get_temp_dir() . '/stpi-gh-bestprice-' . $buildId);
            } catch (\Symfony\Component\Filesystem\Exception\IOExceptionInterface) {
                $this->logger->warning('STPI_BESTPRICE_TEMP_CLEANUP_FAILED', ['feedId' => $id, 'buildId' => $buildId]);
            } finally {
                $this->connection->fetchOne('SELECT RELEASE_LOCK(:name)', ['name' => $lockName]);
            }
        }
    }
}
