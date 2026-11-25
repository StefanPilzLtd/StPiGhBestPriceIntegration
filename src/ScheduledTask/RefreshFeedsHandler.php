<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\ScheduledTask;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use StPiGhBestPriceIntegration\Configuration\FeedRepository;
use StPiGhBestPriceIntegration\Message\BuildFeed;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(handles: RefreshFeedsTask::class)]
final class RefreshFeedsHandler extends ScheduledTaskHandler
{
    public function __construct(EntityRepository $scheduledTaskRepository, LoggerInterface $logger, private readonly FeedRepository $feeds, private readonly MessageBusInterface $bus, private readonly Connection $connection)
    {
        parent::__construct($scheduledTaskRepository, $logger);
    }
    public function run(): void
    {
        foreach ($this->feeds->due() as $id) {
            $this->bus->dispatch(new BuildFeed($id));
        }
        $this->connection->executeStatement('DELETE FROM stpi_gh_bp_conversion WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 180 DAY)');
        $this->connection->executeStatement('DELETE b FROM stpi_gh_bp_build b LEFT JOIN stpi_gh_bp_feed f ON f.published_build_id = b.id WHERE b.created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY) AND f.id IS NULL');
    }
}
