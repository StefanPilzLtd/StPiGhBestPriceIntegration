<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Configuration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use StPiGhBestPriceIntegration\Entitlements\{Feature, FeatureAccessGuard};
use StPiGhBestPriceIntegration\Security\Validation;
use Symfony\Component\HttpKernel\Exception\{NotFoundHttpException, ConflictHttpException};

final readonly class FeedRepository
{
    /** @param EntityRepository<\StPiGhBestPriceIntegration\Core\FeedCollection> $repository */
    public function __construct(private Connection $connection, private EntityRepository $repository, private FeatureAccessGuard $guard)
    {
    }
    /**
     * @return array<string,mixed>
     */
    public function list(int $page = 1, int $limit = 25): array
    {
        $limit = min(100, max(1, $limit));
        $page = max(1, $page);
        $rows = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) id, name, LOWER(HEX(sales_channel_id)) salesChannelId, enabled, created_at createdAt FROM stpi_gh_bp_feed ORDER BY created_at,id LIMIT :lim OFFSET :off', ['lim' => $limit, 'off' => ($page - 1) * $limit], ['lim' => \Doctrine\DBAL\ParameterType::INTEGER, 'off' => \Doctrine\DBAL\ParameterType::INTEGER]);
        foreach ($rows as &$row) {
            $row['enabled'] = (bool) $row['enabled'];
            $row['lastBuild'] = $this->lastBuild($row['id']);
        }
        return ['items' => $rows, 'total' => (int) $this->connection->fetchOne('SELECT COUNT(*) FROM stpi_gh_bp_feed'), 'page' => $page, 'limit' => $limit];
    }
    /**
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        $id = Validation::id($id);
        $row = $this->connection->fetchAssociative('SELECT name, LOWER(HEX(sales_channel_id)) salesChannelId, enabled, settings, updated_at updatedAt FROM stpi_gh_bp_feed WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        if (!$row) {
            throw new NotFoundHttpException('STPI_BESTPRICE_FEED_NOT_FOUND');
        }
        $settings = json_decode($row['settings'], true, 512, JSON_THROW_ON_ERROR);
        return array_merge($settings, ['id' => $id, 'name' => $row['name'], 'salesChannelId' => $row['salesChannelId'], 'enabled' => (bool) $row['enabled'], 'updatedAt' => $row['updatedAt']]);
    }
    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function save(?string $id, array $input, Context $context): array
    {
        $data = FeedSettings::validate($input);
        $lockName = 'stpi_gh_bp_' . substr(hash('sha256', (string) $this->connection->getDatabase()), 0, 32);
        if ((int) $this->connection->fetchOne('SELECT GET_LOCK(:name, 10)', ['name' => $lockName]) !== 1) {
            throw new ConflictHttpException('STPI_BESTPRICE_CONFIGURATION_BUSY');
        }
        $this->connection->beginTransaction();
        try {
            // Serialize all configuration writes, including two concurrent first-feed creations.
            $this->connection->executeQuery("SELECT id FROM sales_channel WHERE id = :id FOR UPDATE", ['id' => Uuid::fromHexToBytes($data['salesChannelId'])])->fetchOne();
            $existing = $id ? $this->get($id) : null;
            if (!$existing && (int) $this->connection->fetchOne('SELECT COUNT(*) FROM stpi_gh_bp_feed') > 0) {
                $this->guard->assert(Feature::MultiAccount);
            }
            if ($existing && !$this->isPrimary($id)) {
                $this->guard->assert(Feature::MultiAccount);
            }
            if ($data['trackingEnabled']) {
                $this->guard->assert(Feature::Tracking360);
            }
            $domain = $this->connection->fetchAssociative('SELECT LOWER(HEX(sales_channel_id)) channelId, url FROM sales_channel_domain WHERE id = :id', ['id' => Uuid::fromHexToBytes($data['domainId'])]);
            if (!$domain || $domain['channelId'] !== $data['salesChannelId']) {
                throw new \InvalidArgumentException('STPI_BESTPRICE_DOMAIN_CHANNEL_MISMATCH');
            }
            Validation::url($domain['url']);
            if (!$this->connection->fetchOne('SELECT 1 FROM country WHERE id = :id AND active = 1', ['id' => Uuid::fromHexToBytes($data['countryId'])])) {
                throw new \InvalidArgumentException('STPI_BESTPRICE_INVALID_COUNTRY');
            }
            $id ??= Uuid::randomHex();
            $data['token'] = $existing['token'] ?? bin2hex(random_bytes(32));
            $data['revision'] = Uuid::randomHex();
            $context->scope(Context::SYSTEM_SCOPE, fn () => $this->repository->upsert([['id' => $id, 'name' => $data['name'], 'salesChannelId' => $data['salesChannelId'], 'enabled' => $data['enabled'], 'settings' => $data]], $context));
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        } finally {
            $this->connection->fetchOne('SELECT RELEASE_LOCK(:name)', ['name' => $lockName]);
        }
        return $this->get($id);
    }
    public function isPrimary(string $id): bool
    {
        return $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM stpi_gh_bp_feed ORDER BY created_at,id LIMIT 1') === $id;
    }
    /**
     * @return array<string,mixed>
     */
    public function assertBuildable(string $id): array
    {
        $feed = $this->get($id);
        if (!(bool) $this->connection->fetchOne('SELECT active FROM plugin WHERE name = :name', ['name' => 'StPiGhBestPriceIntegration'])) {
            throw new ConflictHttpException('STPI_BESTPRICE_PLUGIN_INACTIVE');
        }
        if (!$this->isPrimary($id)) {
            $this->guard->assert(Feature::MultiAccount);
        }
        if (!$feed['enabled']) {
            throw new ConflictHttpException('STPI_BESTPRICE_FEED_DISABLED');
        }
        return $feed;
    }
    public function delete(string $id, Context $context): void
    {
        $this->get($id);
        $context->scope(Context::SYSTEM_SCOPE, fn () => $this->repository->delete([['id' => $id]], $context));
    }
    /**
     * @return array<string,mixed>|null
     */
    public function lastBuild(string $id): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT LOWER(HEX(id)) id, status, products, rejected, duration_ms durationMs, error_code errorCode, created_at createdAt FROM stpi_gh_bp_build WHERE feed_id = :id ORDER BY created_at DESC,id DESC LIMIT 1', ['id' => Uuid::fromHexToBytes($id)]);
        return $row ?: null;
    }
    /**
     * @return list<string>
     */
    public function due(): array
    {
        return $this->connection->fetchFirstColumn('SELECT LOWER(HEX(f.id)) FROM stpi_gh_bp_feed f WHERE f.enabled = 1 AND NOT EXISTS (SELECT 1 FROM stpi_gh_bp_build b WHERE b.feed_id = f.id AND b.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL CAST(JSON_UNQUOTE(JSON_EXTRACT(f.settings, "$.intervalMinutes")) AS UNSIGNED) MINUTE)) ORDER BY f.created_at,f.id LIMIT 100');
    }
}
