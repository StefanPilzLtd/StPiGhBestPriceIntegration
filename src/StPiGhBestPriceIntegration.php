<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

final class StPiGhBestPriceIntegration extends Plugin
{
    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);
        if ($context->keepUserData()) {
            return;
        }
        $connection = $this->container->get(Connection::class);
        foreach (['stpi_gh_bp_conversion', 'stpi_gh_bp_build', 'stpi_gh_bp_feed'] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS `' . $table . '`');
        }
        $connection->executeStatement('DELETE FROM system_config WHERE configuration_key LIKE :prefix', ['prefix' => 'StPiGhBestPriceIntegration.%']);
        $this->container->get('shopware.filesystem.private')->deleteDirectory('stpi-gh-bestprice');
    }
}
