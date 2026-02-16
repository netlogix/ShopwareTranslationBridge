<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\System\Snippet;

use RuntimeException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;
use Symfony\Contracts\Service\ResetInterface;

class TranslationProviderResolver implements TranslationProviderResolverInterface, ResetInterface
{
    private array $providers;

    function __construct(
        #[Autowire(service: 'translation.provider_collection')]
        private readonly TranslationProviderCollection $providerCollection,
        #[Autowire(param: 'nlx_storefront_translation.default_provider')]
        private readonly ?string $defaultProvider,
        #[Autowire(param: 'nlx_storefront_translation.sales_channel_provider')]
        private readonly array $providerMap
    ) {
    }

    public function hasDefaultProvider(): bool
    {
        return $this->defaultProvider !== null && $this->providerCollection->has($this->defaultProvider);
    }

    public function getDefaultProvider(): ProviderInterface
    {
        if (!$this->hasDefaultProvider()) {
            throw new RuntimeException(\sprintf('Provider "%s" not found.', $this->defaultProvider));
        }

        return $this->providers['default'] = $this->providerCollection->get($this->defaultProvider);
    }

    public function hasProvider(string $salesChannelId): bool
    {
        if (!Uuid::isValid($salesChannelId)) {
            throw new \InvalidArgumentException(\sprintf('Provider "%s" is not a valid UUID.', $salesChannelId));
        }

        $providerName = $this->providerMap[$salesChannelId] ?? null;

        return $providerName !== null && $this->providerCollection->has($providerName);
    }

    public function getProvider(string $salesChannelId): ProviderInterface
    {
        if (isset($this->providers[$salesChannelId])) {
            return $this->providers[$salesChannelId];
        }

        if (!$this->hasProvider($salesChannelId)) {
            throw new RuntimeException(\sprintf('No provider for salesChannel "%s" not found.', $salesChannelId));
        }

        $providerName = $this->providerMap[$salesChannelId];
        assert(is_string($providerName));

        return $this->providers[$salesChannelId] = $this->providerCollection->get($providerName);
    }

    public function reset(): void
    {
        unset($this->providerss);
    }
}
