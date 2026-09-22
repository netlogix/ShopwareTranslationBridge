<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\System\Snippet;

use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\Exception\MissingDefaultProviderException;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\Exception\MissingSalesChannelProviderException;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\Exception\UnknownProviderException;
use Netlogix\ShopwareTranslationBridge\Resolver\ConfigurationResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;

readonly class TranslationProviderResolver implements TranslationProviderResolverInterface
{
    public function __construct(
        #[Autowire(service: 'translation.provider_collection')]
        private TranslationProviderCollection $providerCollection,
        private ConfigurationResolver $configurationResolver
    ) {
    }

    public function hasProvider(?string $salesChannelId = null): bool
    {
        $providerName = $this->configurationResolver->getProviderName($salesChannelId);

        return $providerName !== null && $this->providerCollection->has($providerName);
    }

    public function getProvider(?string $salesChannelId = null): ProviderInterface
    {
        $providerName = $this->configurationResolver->getProviderName($salesChannelId);

        if ($providerName === null) {
            throw $salesChannelId === null
                ? new MissingDefaultProviderException(code: 1785932654)
                : new MissingSalesChannelProviderException(salesChannelId: $salesChannelId, code: 1785932765);
        }

        if (!$this->providerCollection->has($providerName)) {
            throw new UnknownProviderException(
                providerName: $providerName,
                salesChannelId: $salesChannelId,
                code: 1785932876
            );
        }

        return $this->providerCollection->get($providerName);
    }
}
