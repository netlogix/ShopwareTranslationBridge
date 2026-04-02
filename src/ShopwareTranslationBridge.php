<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge;

use Netlogix\ShopwareTranslationBridge\Core\Framework\Adapter\Translator\TranslationCacheInvalidation;
use Netlogix\ShopwareTranslationBridge\Core\Framework\Adapter\Translator\TranslationCacheInvalidationInterface;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\SalesChannelTranslationRefresher;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\SalesChannelTranslationRefresherInterface;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\TranslationProviderResolver;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\TranslationProviderResolverInterface;
use Override;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Extension\ConfigurableExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleExtension;

class ShopwareTranslationBridge extends Plugin implements ConfigurableExtensionInterface
{
    private string $extensionAlias;

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition
            ->rootNode()
            ->children()
            ->scalarNode('default_provider')
            ->defaultNull()
            ->end()
            ->booleanNode('respect_translation_files')
            ->defaultTrue()
            ->end()
            ->arrayNode('sales_channel_providers')
            ->useAttributeAsKey('salesChannelId')
            ->arrayPrototype()
            ->beforeNormalization()
            ->ifString()
            ->then(static fn(string $v): array => ['provider' => $v])
            ->end()
            ->children()
            ->scalarNode('provider')
            ->isRequired()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services()->defaults()->autowire()->autoconfigure();

        $services->load(__NAMESPACE__ . '\\', '*');

        $services->alias(TranslationProviderResolverInterface::class, TranslationProviderResolver::class);
        $services->alias(SalesChannelTranslationRefresherInterface::class, SalesChannelTranslationRefresher::class);
        $services->alias(TranslationCacheInvalidationInterface::class, TranslationCacheInvalidation::class);

        $defaultProvider = $config['default_provider'] ?? null;
        assert($defaultProvider === null || is_string($defaultProvider));
        $container->parameters()->set('nlx_storefront_translation.default_provider', $defaultProvider);

        $respectTranslationFiles = $config['respect_translation_files'] ?? null;
        assert(is_bool($respectTranslationFiles));
        $container->parameters()->set('nlx_storefront_translation.respect_translation_files', $respectTranslationFiles);

        $salesChannelProviders = $config['sales_channel_providers'] ?? null;
        assert(is_array($salesChannelProviders));
        $container->parameters()->set(
            'nlx_storefront_translation.sales_channel_provider',
            $this->processSalesChannelProviders($salesChannelProviders)
        );
    }

    #[Override]
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (!isset($this->extensionAlias)) {
            $this->extensionAlias = Container::underscore(preg_replace('/Bundle$/', '', $this->getName()));
        }

        $this->extension ??= new BundleExtension($this, $this->extensionAlias);

        return $this->extension === false ? null : $this->extension;
    }

    private function processSalesChannelProviders(array $salesChannelProviders): array
    {
        $providerMap = [];
        foreach ($salesChannelProviders as $salesChannelId => $providerConfig) {
            assert(is_string($salesChannelId));
            if (!Uuid::isValid($salesChannelId)) {
                throw new RuntimeException('Invalid salesChannel UUID: ' . $salesChannelId);
            }

            $providerName = $providerConfig['provider'] ?? null;
            assert(is_string($providerName));
            $providerMap[$salesChannelId] = $providerName;
        }

        return $providerMap;
    }
}
