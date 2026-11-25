<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Command;

use StPiGhBestPriceIntegration\Feed\FeedBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputArgument};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'stpi:bestprice:feed:build', description: 'Build an explicitly selected, enabled BestPrice feed')]
final class BuildFeedCommand extends Command
{
    public function __construct(private readonly FeedBuilder $builder)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('feed-id', InputArgument::REQUIRED);
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $output->writeln(json_encode($this->builder->build((string) $input->getArgument('feed-id')), JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (\Throwable) {
            $output->writeln('<error>STPI_BESTPRICE_BUILD_FAILED: see the administration diagnostics.</error>');
            return self::FAILURE;
        }
    }
}
