<?php

declare(strict_types=1);

require __DIR__ . '/integration-bootstrap.php';
$fixture = json_decode(file_get_contents(__DIR__ . '/../artifacts/e2e-feed.json'), true, 512, JSON_THROW_ON_ERROR);
$id = \StPiGhBestPriceIntegration\Security\Validation::id($fixture['id']);
$command = $GLOBALS['bpKernel']->getContainer()->get('console.command_loader')->get('stpi:bestprice:feed:build');
exit($command->run(new \Symfony\Component\Console\Input\ArrayInput(['feed-id' => $id]), new \Symfony\Component\Console\Output\ConsoleOutput()));
