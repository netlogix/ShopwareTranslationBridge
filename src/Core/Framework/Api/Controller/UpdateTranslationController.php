<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\Framework\Api\Controller;

use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\TranslationProviderResolverInterface;
use Netlogix\ShopwareTranslationBridge\Message\TranslationUpdateMessage;
use Shopware\Core\Framework\Api\Response\JsonApiResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    path: '/api/_action/nlx-translation/update',
    name: 'api.action.nlx.translation_update',
    defaults: [
        '_routeScope' => ['api'],
        '_acl' => ['system:cache:info'],
    ],
    methods: ['POST']
)]
class UpdateTranslationController extends AbstractController
{
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly TranslationProviderResolverInterface $translationProviderResolver,
        private readonly int $batchSize = 5
    ) {
    }

    public function __invoke(): JsonApiResponse
    {
        $salesChannelIds = $this->salesChannelRepository
            ->searchIds(new Criteria(), Context::createCLIContext())
            ->getIds();

        // Keep only sales channels that resolve to a provider. Thanks to Shopware's config
        // inheritance this covers both a global default and channel-specific overrides.
        $salesChannelIds = array_values(array_filter(
            $salesChannelIds,
            $this->translationProviderResolver->hasProvider(...)
        ));

        if ($salesChannelIds === []) {
            return new JsonApiResponse([
                'success' => false,
                'error' => 'errorMissingTranslationProvider',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        foreach (array_chunk($salesChannelIds, $this->batchSize) as $chunk) {
            $this->messageBus->dispatch(new TranslationUpdateMessage(...$chunk));
        }

        return new JsonApiResponse([
            'success' => true,
        ]);
    }
}
