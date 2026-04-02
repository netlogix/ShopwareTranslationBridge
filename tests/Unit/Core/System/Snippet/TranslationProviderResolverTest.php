<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Tests\Unit\Core\System\Snippet;

use InvalidArgumentException;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\TranslationProviderResolver;
use Netlogix\ShopwareTranslationBridge\Tests\Support\Provider\InMemoryTestProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;

#[CoversClass(TranslationProviderResolver::class)]
final class TranslationProviderResolverTest extends TestCase
{
    private const string SALES_CHANNEL_ID = '2b919afec10730f413cb5682bbed09fd';

    public function testHasDefaultProviderReturnsTrueWhenProviderExists(): void
    {
        $providerCollection = new TranslationProviderCollection([
            'default-provider' => new InMemoryTestProvider('default-provider')
        ]);

        $resolver = new TranslationProviderResolver($providerCollection, 'default-provider', []);

        static::assertTrue($resolver->hasDefaultProvider());
    }

    public function testHasSalesChannelProviderThrowsOnInvalidUuid(): void
    {
        $resolver = new TranslationProviderResolver(new TranslationProviderCollection([]), null, []);

        $this->expectException(InvalidArgumentException::class);

        $resolver->hasSalesChannelProvider('not-a-uuid');
    }

    public function testGetProviderPrefersSalesChannelProviderOverDefaultProvider(): void
    {
        $salesChannelProvider = new InMemoryTestProvider('sales-channel-provider');
        $defaultProvider = new InMemoryTestProvider('default-provider');

        $resolver = new TranslationProviderResolver(
            new TranslationProviderCollection([
                'default-provider' => $defaultProvider,
                'sales-channel-provider' => $salesChannelProvider
            ]),
            'default-provider',
            [self::SALES_CHANNEL_ID => 'sales-channel-provider']
        );

        static::assertSame($salesChannelProvider, $resolver->getProvider(self::SALES_CHANNEL_ID));
    }

    public function testGetProviderFallsBackToDefaultProvider(): void
    {
        $defaultProvider = new InMemoryTestProvider('default-provider');
        $resolver = new TranslationProviderResolver(
            new TranslationProviderCollection(['default-provider' => $defaultProvider]),
            'default-provider',
            []
        );

        static::assertSame($defaultProvider, $resolver->getProvider(self::SALES_CHANNEL_ID));
    }

    public function testGetProviderThrowsIfNoProviderExists(): void
    {
        $resolver = new TranslationProviderResolver(new TranslationProviderCollection([]), null, []);

        $this->expectException(RuntimeException::class);

        $resolver->getProvider(self::SALES_CHANNEL_ID);
    }

    public function testResetClearsSalesChannelProviderCache(): void
    {
        $provider = new InMemoryTestProvider('sales-channel-provider');
        $resolver = new TranslationProviderResolver(
            new TranslationProviderCollection(['sales-channel-provider' => $provider]),
            null,
            [self::SALES_CHANNEL_ID => 'sales-channel-provider']
        );

        static::assertSame($provider, $resolver->getSalesChannelProvider(self::SALES_CHANNEL_ID));
        static::assertNotSame([], $this->getProviderCache($resolver));

        $resolver->reset();

        static::assertSame([], $this->getProviderCache($resolver));
    }

    private function getProviderCache(TranslationProviderResolver $resolver): array
    {
        $reflectionProperty = new ReflectionProperty($resolver, 'providers');

        return $reflectionProperty->isInitialized($resolver) ? $reflectionProperty->getValue($resolver) : [];
    }
}
