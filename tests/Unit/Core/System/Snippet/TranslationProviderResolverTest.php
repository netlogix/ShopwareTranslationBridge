<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Tests\Unit\Core\System\Snippet;

use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\Exception\MissingDefaultProviderException;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\Exception\MissingSalesChannelProviderException;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\Exception\UnknownProviderException;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\TranslationProviderResolver;
use Netlogix\ShopwareTranslationBridge\Resolver\ConfigurationResolver;
use Netlogix\ShopwareTranslationBridge\Tests\Support\Provider\InMemoryTestProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;

#[CoversClass(TranslationProviderResolver::class)]
final class TranslationProviderResolverTest extends TestCase
{
    private const string SALES_CHANNEL_ID = '2b919afec10730f413cb5682bbed09fd';

    public function testHasProviderReturnsTrueWhenConfiguredProviderExists(): void
    {
        $resolver = new TranslationProviderResolver(
            new TranslationProviderCollection(['default-provider' => new InMemoryTestProvider('default-provider')]),
            $this->createConfigurationResolver('default-provider')
        );

        static::assertTrue($resolver->hasProvider());
    }

    public function testHasProviderReturnsFalseWhenNothingConfigured(): void
    {
        $resolver = new TranslationProviderResolver(
            new TranslationProviderCollection([]),
            $this->createConfigurationResolver(null)
        );

        static::assertFalse($resolver->hasProvider());
    }

    public function testHasProviderReturnsFalseWhenConfiguredProviderIsNotRegistered(): void
    {
        $resolver = new TranslationProviderResolver(
            new TranslationProviderCollection([]),
            $this->createConfigurationResolver('missing-provider')
        );

        static::assertFalse($resolver->hasProvider());
    }

    public function testGetProviderReturnsConfiguredDefaultProvider(): void
    {
        $provider = new InMemoryTestProvider('default-provider');
        $resolver = new TranslationProviderResolver(
            new TranslationProviderCollection(['default-provider' => $provider]),
            $this->createConfigurationResolver('default-provider')
        );

        static::assertSame($provider, $resolver->getProvider());
    }

    public function testGetProviderResolvesSalesChannelScopedProvider(): void
    {
        $provider = new InMemoryTestProvider('sales-provider');
        $resolver = new TranslationProviderResolver(
            new TranslationProviderCollection(['sales-provider' => $provider]),
            $this->createConfigurationResolver('sales-provider')
        );

        static::assertSame($provider, $resolver->getProvider(self::SALES_CHANNEL_ID));
    }

    public function testGetProviderThrowsWhenNoDefaultProviderConfigured(): void
    {
        $resolver = new TranslationProviderResolver(
            new TranslationProviderCollection([]),
            $this->createConfigurationResolver(null)
        );

        $this->expectException(MissingDefaultProviderException::class);

        $resolver->getProvider();
    }

    public function testGetProviderThrowsWhenNoSalesChannelProviderConfigured(): void
    {
        $resolver = new TranslationProviderResolver(
            new TranslationProviderCollection([]),
            $this->createConfigurationResolver(null)
        );

        $this->expectException(MissingSalesChannelProviderException::class);

        $resolver->getProvider(self::SALES_CHANNEL_ID);
    }

    public function testGetProviderThrowsWhenConfiguredProviderIsNotRegistered(): void
    {
        $resolver = new TranslationProviderResolver(
            new TranslationProviderCollection([]),
            $this->createConfigurationResolver('ghost-provider')
        );

        $this->expectException(UnknownProviderException::class);

        $resolver->getProvider();
    }

    private function createConfigurationResolver(?string $providerName): ConfigurationResolver
    {
        $configurationResolver = $this->createStub(ConfigurationResolver::class);
        $configurationResolver->method('getProviderName')->willReturn($providerName);

        return $configurationResolver;
    }
}
