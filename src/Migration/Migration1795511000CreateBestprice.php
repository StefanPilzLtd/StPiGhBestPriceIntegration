<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1795511000CreateBestprice extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795511000;
    }
    public function update(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS stpi_gh_bp_feed (
            id BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, sales_channel_id BINARY(16) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 0, settings JSON NOT NULL,
            created_at DATETIME(3) NOT NULL, updated_at DATETIME(3) NULL,
            PRIMARY KEY(id), INDEX idx_stpi_gh_bp_channel(sales_channel_id),
            CONSTRAINT fk_stpi_gh_bp_channel FOREIGN KEY(sales_channel_id) REFERENCES sales_channel(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS stpi_gh_bp_build (
            id BINARY(16) NOT NULL, feed_id BINARY(16) NOT NULL, status VARCHAR(32) NOT NULL,
            products INT UNSIGNED NOT NULL DEFAULT 0, rejected INT UNSIGNED NOT NULL DEFAULT 0,
            duration_ms INT UNSIGNED NOT NULL DEFAULT 0, error_code VARCHAR(100) NULL,
            created_at DATETIME(3) NOT NULL, PRIMARY KEY(id), INDEX idx_stpi_gh_bp_build(feed_id, created_at),
            CONSTRAINT fk_stpi_gh_bp_build_feed FOREIGN KEY(feed_id) REFERENCES stpi_gh_bp_feed(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS stpi_gh_bp_conversion (
            id BINARY(16) NOT NULL, feed_id BINARY(16) NOT NULL, order_hash CHAR(64) NOT NULL,
            created_at DATETIME(3) NOT NULL, PRIMARY KEY(id), UNIQUE KEY uniq_stpi_gh_bp_conversion(feed_id, order_hash),
            INDEX idx_stpi_gh_bp_retention(created_at),
            CONSTRAINT fk_stpi_gh_bp_conversion_feed FOREIGN KEY(feed_id) REFERENCES stpi_gh_bp_feed(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    public function updateDestructive(Connection $connection): void
    {
    }
}
