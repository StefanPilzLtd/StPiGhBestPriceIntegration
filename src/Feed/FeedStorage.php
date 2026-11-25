<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Feed;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Uuid\Uuid;
use StPiGhBestPriceIntegration\Security\Validation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class FeedStorage
{
    public function __construct(private FilesystemOperator $filesystem, private Connection $connection)
    {
    }
    private function prefix(string $id): string
    {
        return 'stpi-gh-bestprice/' . Validation::id($id);
    }
    /** @return array<string,mixed>|null */
    public function current(string $id): ?array
    {
        $json = $this->connection->fetchOne('SELECT b.manifest FROM stpi_gh_bp_feed f JOIN stpi_gh_bp_build b ON b.id = f.published_build_id AND b.feed_id = f.id WHERE f.id = :id AND b.status = :status', ['id' => Uuid::fromHexToBytes(Validation::id($id)), 'status' => 'success']);
        return is_string($json) ? json_decode($json, true, 512, JSON_THROW_ON_ERROR) : null;
    }
    /**
     * @param array<string,mixed>|null $manifest
     * @return resource
     */
    public function stream(string $id, string $format, ?array $manifest = null)
    {
        if (!in_array($format, ['xml', 'csv'], true)) {
            throw new NotFoundHttpException();
        }
        $manifest ??= $this->current($id);
        if (!$manifest) {
            throw new NotFoundHttpException('STPI_BESTPRICE_NO_PUBLISHED_FEED');
        }
        return $this->filesystem->readStream($this->prefix($id) . '/' . Validation::id($manifest['buildId']) . '/feed.' . $format);
    }
    public function saveErrors(string $id, string $file): void
    {
        $stream = fopen($file, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('STPI_BESTPRICE_READ_FAILED');
        }
        try {
            $this->filesystem->writeStream($this->prefix($id) . '/latest-errors.ndjson', $stream);
        } finally {
            fclose($stream);
        }
    }
    /** @param array<string,mixed> $stats */
    public function publish(string $id, string $buildId, string $directory, array $stats): void
    {
        $prefix = $this->prefix($id) . '/' . Validation::id($buildId);
        try {
            foreach (['xml', 'csv'] as $format) {
                $stream = fopen($directory . '/feed.' . $format, 'rb');
                if ($stream === false) {
                    throw new \RuntimeException('STPI_BESTPRICE_READ_FAILED');
                }
                try {
                    $this->filesystem->writeStream($prefix . '/feed.' . $format, $stream);
                } finally {
                    fclose($stream);
                }
            }
            $this->connection->transactional(function () use ($id, $buildId, $stats): void {
                $updated = $this->connection->update('stpi_gh_bp_build', ['manifest' => json_encode($stats, JSON_THROW_ON_ERROR), 'status' => 'success', 'products' => $stats['products'], 'rejected' => $stats['rejected'], 'duration_ms' => $stats['durationMs']], ['id' => Uuid::fromHexToBytes($buildId), 'feed_id' => Uuid::fromHexToBytes($id), 'status' => 'running']);
                if ($updated !== 1) {
                    throw new \RuntimeException('STPI_BESTPRICE_BUILD_FAILED');
                }
                $changed = $this->connection->executeStatement('UPDATE stpi_gh_bp_feed SET published_build_id = :build WHERE id = :id AND enabled = 1 AND JSON_UNQUOTE(JSON_EXTRACT(settings, "$.revision")) = :revision', ['build' => Uuid::fromHexToBytes($buildId), 'id' => Uuid::fromHexToBytes($id), 'revision' => $stats['revision']]);
                if ($changed !== 1) {
                    throw new \RuntimeException('STPI_BESTPRICE_CONFIGURATION_CHANGED');
                }
            });
        } catch (\Throwable $e) {
            $this->filesystem->deleteDirectory($prefix);
            throw $e;
        }
    }
    /** @return array<string,mixed> */
    public function errors(string $id, int $page = 1, int $limit = 50): array
    {
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));
        $items = [];
        $total = 0;
        $path = $this->prefix($id) . '/latest-errors.ndjson';
        if ($this->filesystem->fileExists($path)) {
            $stream = $this->filesystem->readStream($path);
            try {
                while (($line = fgets($stream)) !== false) {
                    if (trim($line) === '') {
                        continue;
                    }
                    if ($total >= ($page - 1) * $limit && count($items) < $limit) {
                        $items[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    }
                    ++$total;
                }
            } finally {
                fclose($stream);
            }
        }
        return ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }
    public function prune(string $id, string $currentBuild): void
    {
        $prefix = $this->prefix($id) . '/';
        foreach ($this->filesystem->listContents($prefix, true) as $item) {
            if (!$item instanceof \League\Flysystem\FileAttributes || str_contains($item->path(), '/' . $currentBuild . '/') || $item->path() === $prefix . 'latest-errors.ndjson') {
                continue;
            }
            if (($item->lastModified() ?? time()) < time() - 86400) {
                $this->filesystem->delete($item->path());
            }
        }
    }
    public function remove(string $id): void
    {
        $this->filesystem->deleteDirectory($this->prefix($id));
    }
}
