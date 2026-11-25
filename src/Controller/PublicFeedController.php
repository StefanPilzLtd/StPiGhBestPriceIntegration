<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Controller;

use StPiGhBestPriceIntegration\Configuration\FeedRepository;
use StPiGhBestPriceIntegration\Feed\FeedStorage;
use Symfony\Component\HttpFoundation\{Request, StreamedResponse, Response};
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, ConflictHttpException};
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
final readonly class PublicFeedController
{
    public function __construct(private FeedRepository $feeds, private FeedStorage $storage)
    {
    }
    #[Route(path: '/bestprice-feed/{id}/{token}.{format}', name: 'store-api.stpi_gh_bestprice.feed', requirements: ['id' => '[a-f0-9]{32}', 'token' => '[a-f0-9]{64}', 'format' => 'xml|csv'], methods: ['GET', 'HEAD'])]
    public function download(string $id, string $token, string $format, Request $request): Response
    {
        try {
            $feed = $this->feeds->assertBuildable($id);
        } catch (AccessDeniedHttpException|ConflictHttpException) {
            throw new NotFoundHttpException();
        }
        if (!$feed['enabled'] || !hash_equals($feed['token'], $token)) {
            throw new NotFoundHttpException();
        }
        $manifest = $this->storage->current($id);
        $stream = $this->storage->stream($id, $format, $manifest);
        $response = new StreamedResponse(static function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        });
        $response->headers->set('Content-Type', ($format === 'xml' ? 'application/xml' : 'text/csv') . '; charset=UTF-8');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->setEtag($manifest[$format . 'Sha256']);
        $response->isNotModified($request);
        return $response;
    }
}
