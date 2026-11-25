<?php

declare(strict_types=1);

namespace StPiGhBestPriceIntegration\Controller;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use StPiGhBestPriceIntegration\Configuration\FeedRepository;
use StPiGhBestPriceIntegration\Feed\FeedStorage;
use StPiGhBestPriceIntegration\Entitlements\EntitlementService;
use StPiGhBestPriceIntegration\Pricing\PricingService;
use StPiGhBestPriceIntegration\Analytics\ProfitabilityService;
use StPiGhBestPriceIntegration\Message\BuildFeed;
use Symfony\Component\HttpFoundation\{Request, JsonResponse};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
final readonly class AdminController
{
    public function __construct(private FeedRepository $feeds, private FeedStorage $storage, private EntitlementService $entitlements, private PricingService $pricing, private ProfitabilityService $profitability, private Connection $connection, private MessageBusInterface $bus)
    {
    }
    #[Route(path: '/api/_action/stpi-gh-bestprice/state', name: 'api.stpi_gh_bestprice.state', defaults: ['_acl' => ['stpi_gh_bestprice.viewer']], methods: ['GET'])]
    public function state(Context $context): JsonResponse
    {
        $channels = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(c.id)) id, COALESCE(t.name, f.name, LOWER(HEX(c.id))) name FROM sales_channel c LEFT JOIN sales_channel_translation t ON t.sales_channel_id = c.id AND t.language_id = :language LEFT JOIN sales_channel_translation f ON f.sales_channel_id = c.id AND f.language_id = :fallback WHERE c.active = 1', ['language' => \Shopware\Core\Framework\Uuid\Uuid::fromHexToBytes($context->getLanguageId()), 'fallback' => \Shopware\Core\Framework\Uuid\Uuid::fromHexToBytes(\Shopware\Core\Defaults::LANGUAGE_SYSTEM)]);
        $domains = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) id, LOWER(HEX(sales_channel_id)) salesChannelId, url, LOWER(HEX(language_id)) languageId, LOWER(HEX(currency_id)) currencyId FROM sales_channel_domain ORDER BY url');
        $countries = $this->connection->fetchAllAssociative('SELECT LOWER(HEX(id)) id, iso name FROM country WHERE active = 1 ORDER BY iso');
        return new JsonResponse(['salesChannels' => $channels, 'domains' => $domains, 'countries' => $countries, 'feeds' => $this->feeds->list(), 'tracking' => ['available' => false, 'reason' => 'OFFICIAL_TEST_AND_CONSENT_REVIEW_REQUIRED']]);
    }
    #[Route(path: '/api/_action/stpi-gh-bestprice/feeds', name: 'api.stpi_gh_bestprice.feeds', defaults: ['_acl' => ['stpi_gh_bestprice.viewer']], methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->feeds->list($request->query->getInt('page', 1), $request->query->getInt('limit', 25)));
    }
    #[Route(path: '/api/_action/stpi-gh-bestprice/feeds', name: 'api.stpi_gh_bestprice.feed.create', defaults: ['_acl' => ['stpi_gh_bestprice.editor']], methods: ['POST'])]
    public function create(Request $request, Context $context): JsonResponse
    {
        return $this->save(null, $request, $context);
    }
    #[Route(path: '/api/_action/stpi-gh-bestprice/feeds/{id}', name: 'api.stpi_gh_bestprice.feed.update', defaults: ['_acl' => ['stpi_gh_bestprice.editor']], methods: ['PUT'])]
    public function update(string $id, Request $request, Context $context): JsonResponse
    {
        return $this->save($id, $request, $context);
    }
    private function save(?string $id, Request $request, Context $context): JsonResponse
    {
        try {
            return new JsonResponse($this->feeds->save($id, $this->input($request), $context));
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
    #[Route(path: '/api/_action/stpi-gh-bestprice/feeds/{id}', name: 'api.stpi_gh_bestprice.feed.detail', defaults: ['_acl' => ['stpi_gh_bestprice.editor']], methods: ['GET'])]
    public function detail(string $id): JsonResponse
    {
        return new JsonResponse($this->feeds->get($id) + ['published' => $this->storage->current($id), 'lastBuild' => $this->feeds->lastBuild($id)]);
    }
    #[Route(path: '/api/_action/stpi-gh-bestprice/feeds/{id}', name: 'api.stpi_gh_bestprice.feed.delete', defaults: ['_acl' => ['stpi_gh_bestprice.editor']], methods: ['DELETE'])]
    public function delete(string $id, Context $context): JsonResponse
    {
        $this->feeds->delete($id, $context);
        $this->storage->remove($id);
        return new JsonResponse(['deleted' => true]);
    }
    #[Route(path: '/api/_action/stpi-gh-bestprice/feeds/{id}/build', name: 'api.stpi_gh_bestprice.feed.build', defaults: ['_acl' => ['stpi_gh_bestprice.generator']], methods: ['POST'])]
    public function build(string $id): JsonResponse
    {
        $this->feeds->assertBuildable($id);
        $this->bus->dispatch(new BuildFeed($id));
        return new JsonResponse(['queued' => true], 202);
    }
    #[Route(path: '/api/_action/stpi-gh-bestprice/feeds/{id}/errors', name: 'api.stpi_gh_bestprice.feed.errors', defaults: ['_acl' => ['stpi_gh_bestprice.viewer']], methods: ['GET'])]
    public function errors(string $id, Request $request): JsonResponse
    {
        $this->feeds->get($id);
        return new JsonResponse($this->storage->errors($id, $request->query->getInt('page', 1)));
    }
    #[Route(path: '/api/_action/stpi-gh-bestprice/entitlements', name: 'api.stpi_gh_bestprice.entitlements', defaults: ['_acl' => ['stpi_gh_bestprice.licenses']], methods: ['GET'])]
    public function entitlements(): JsonResponse
    {
        return new JsonResponse($this->entitlements->status());
    }
    #[Route(path: '/api/_action/stpi-gh-bestprice/pricing/preview', name: 'api.stpi_gh_bestprice.pricing', defaults: ['_acl' => ['stpi_gh_bestprice.pricing']], methods: ['POST'])]
    public function pricing(Request $request): JsonResponse
    {
        try {
            return new JsonResponse($this->pricing->preview($this->input($request)));
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
    #[Route(path: '/api/_action/stpi-gh-bestprice/analytics/profitability', name: 'api.stpi_gh_bestprice.profitability', defaults: ['_acl' => ['stpi_gh_bestprice.analytics']], methods: ['POST'])]
    public function profitability(Request $request): JsonResponse
    {
        try {
            return new JsonResponse($this->profitability->calculate($this->input($request)));
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
    /**
     * @return array<string,mixed>
     */
    private function input(Request $request): array
    {
        if (strlen($request->getContent()) > 262144) {
            throw new BadRequestHttpException('STPI_BESTPRICE_REQUEST_TOO_LARGE');
        }
        try {
            $data = json_decode($request->getContent(), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('STPI_BESTPRICE_INVALID_JSON');
        }
        if (!is_array($data) || !str_starts_with(ltrim($request->getContent()), '{')) {
            throw new BadRequestHttpException('STPI_BESTPRICE_INVALID_JSON');
        }
        return $data;
    }
}
