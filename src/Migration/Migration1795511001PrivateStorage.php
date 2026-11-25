<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

final class Migration1795511001PrivateStorage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795511001;
    }
    public function update(Connection $connection): void
    {
        $schema = $connection->createSchemaManager();
        if (!isset($schema->listTableColumns('stpi_gh_bp_feed')['published_build_id'])) {
            $connection->executeStatement('ALTER TABLE stpi_gh_bp_feed ADD published_build_id BINARY(16) NULL');
        }
        if (!isset($schema->listTableColumns('stpi_gh_bp_build')['manifest'])) {
            $connection->executeStatement('ALTER TABLE stpi_gh_bp_build ADD manifest JSON NULL');
        }
    }
    public function updateDestructive(Connection $connection): void
    {
    }
}
