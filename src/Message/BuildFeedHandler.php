<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Message;

use StPiGhBestPriceIntegration\Feed\FeedBuilder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException, ConflictHttpException};

#[AsMessageHandler]
final readonly class BuildFeedHandler
{
    public function __construct(private FeedBuilder $builder)
    {
    }
    public function __invoke(BuildFeed $message): void
    {
        try {
            $this->builder->build($message->feedId);
        } catch (AccessDeniedHttpException|NotFoundHttpException|ConflictHttpException) {
            // Revoked entitlement, deleted/disabled feed or duplicate job: safe cancellation, no retry.
        }
    }
}
