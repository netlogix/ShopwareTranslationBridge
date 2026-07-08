<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\Response\JsonApiResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;

#[Route(
    path: '/api/_action/nlx-translation/providers',
    name: 'api.action.nlx.translation_providers',
    defaults: [
        '_routeScope' => ['api'],
        '_acl' => ['system_config:read'],
    ],
    methods: ['GET']
)]
class TranslationProviderOptionsController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'translation.provider_collection')]
        private readonly TranslationProviderCollection $providers,
    ) {
    }

    public function __invoke(): JsonApiResponse
    {
        $options = array_map(
            static fn (string $name): array => [
                'value' => $name,
                'label' => $name,
            ],
            $this->providers->keys()
        );

        return new JsonApiResponse(['options' => $options]);
    }
}
