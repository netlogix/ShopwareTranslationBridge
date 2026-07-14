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
use Symfony\Contracts\Service\ResetInterface;

class TranslationProviderResolver implements TranslationProviderResolverInterface, ResetInterface
{
    private array $providers = [];

    public function __construct(
        #[Autowire(service: 'translation.provider_collection')]
        private readonly TranslationProviderCollection $providerCollection,
        private readonly ConfigurationResolver $configurationResolver
    ) {
    }

    public function hasDefaultProvider(): bool
    {
        $providerName = $this->resolveDefaultProviderName();

        return $providerName !== null && $this->providerCollection->has($providerName);
    }

    public function getDefaultProvider(): ProviderInterface
    {
        if (!$this->hasDefaultProvider()) {
            throw new RuntimeException(\sprintf('Provider "%s" not found.', $this->resolveDefaultProviderName() ?? ''));
        }

        $providerName = $this->resolveDefaultProviderName();
        assert(is_string($providerName));

        return $this->providers['default'] = $this->providerCollection->get($providerName);
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
        if (array_key_exists($salesChannelId, $this->providers)) {
            return $this->providers[$salesChannelId];
        }

        if (!$this->hasSalesChannelProvider($salesChannelId)) {
            throw new RuntimeException(\sprintf('Provider for salesChannel "%s" not found.', $salesChannelId));
        }

        $providerName = $this->resolveSalesChannelProviderName($salesChannelId);
        assert(is_string($providerName), 'Provider map value must be string');

        return $this->providers[$salesChannelId] = $this->providerCollection->get($providerName);
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

    public function reset(): void
    {
        $this->providers = [];
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
