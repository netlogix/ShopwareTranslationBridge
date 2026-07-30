<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\System\Snippet;

use InvalidArgumentException;
use Netlogix\ShopwareTranslationBridge\Resolver\ConfigurationResolver;
use RuntimeException;
use Shopware\Core\Framework\Uuid\Uuid;
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

    public function hasDefaultProvider(): bool
    {
        $providerName = $this->resolveDefaultProviderName();

        return $providerName !== null && $this->providerCollection->has($providerName);
    }

    public function getDefaultProvider(): ProviderInterface
    {
        $providerName = $this->resolveDefaultProviderName();

        if ($providerName === null || !$this->providerCollection->has($providerName)) {
            throw new RuntimeException(\sprintf('Provider "%s" not found.', $providerName ?? ''));
        }

        return $this->providerCollection->get($providerName);
    }

    public function hasSalesChannelProvider(string $salesChannelId): bool
    {
        if (!Uuid::isValid($salesChannelId)) {
            throw new InvalidArgumentException(\sprintf('SalesChannelId "%s" is not a valid UUID.', $salesChannelId));
        }

        $providerName = $this->resolveSalesChannelProviderName($salesChannelId);

        return $providerName !== null && $this->providerCollection->has($providerName);
    }

    public function getSalesChannelProvider(string $salesChannelId): ProviderInterface
    {
        $providerName = $this->resolveSalesChannelProviderName($salesChannelId);

        if ($providerName === null || !$this->providerCollection->has($providerName)) {
            throw new RuntimeException(\sprintf('Provider for salesChannel "%s" not found.', $salesChannelId));
        }

        return $this->providerCollection->get($providerName);
    }

    public function hasProvider(string $salesChannelId): bool
    {
        return $this->hasDefaultProvider() || $this->hasSalesChannelProvider($salesChannelId);
    }

    public function getProvider(string $salesChannelId): ProviderInterface
    {
        if ($this->hasSalesChannelProvider($salesChannelId)) {
            return $this->getSalesChannelProvider($salesChannelId);
        }
        if ($this->hasDefaultProvider()) {
            return $this->getDefaultProvider();
        }

        throw new RuntimeException(\sprintf('Provider for salesChannel "%s" not found.', $salesChannelId));
    }

    private function resolveDefaultProviderName(): ?string
    {
        $providerName = $this->configurationResolver->getDefaultProviderName();

        return $providerName !== '' ? $providerName : null;
    }

    private function resolveSalesChannelProviderName(string $salesChannelId): ?string
    {
        return $this->configurationResolver->getSalesChannelProviderOverride($salesChannelId);
    }
}
