<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Tests\Unit\Core\System\Snippet\Listener;

use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\Listener\LoadTranslationsListener;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\TranslationProviderResolverInterface;
use Netlogix\ShopwareTranslationBridge\Resolver\ConfigurationResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\Snippet\Extension\StorefrontSnippetsExtension;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;

#[CoversClass(LoadTranslationsListener::class)]
final class LoadTranslationsListenerTest extends TestCase
{
    private const string SALES_CHANNEL_ID = '2b919afec10730f413cb5682bbed09fd';

    public function testInvokeRespectsTranslationFilesWhenNoProviderExists(): void
    {
        $providerResolver = $this->createMock(TranslationProviderResolverInterface::class);
        $providerResolver
            ->expects(static::once())
            ->method('hasProvider')
            ->with(self::SALES_CHANNEL_ID)
            ->willReturn(false);
        $providerResolver->expects(static::never())->method('getProvider');

        $catalog = new MessageCatalogue('de-DE');
        $catalog->add(['headline' => 'sales-channel-value'], self::SALES_CHANNEL_ID);
        $catalog->add(['fileOnly' => 'from-file'], LoadTranslationsListener::TRANSLATION_DOMAIN);

        $extension = new StorefrontSnippetsExtension(
            ['headline' => 'snippet-value', 'missing' => 'fallback-value', 'fileOnly' => 'snippet-file'],
            'de-DE',
            $catalog,
            'snippet-set',
            null,
            self::SALES_CHANNEL_ID,
            []
        );

        $listener = new LoadTranslationsListener($providerResolver, $this->createConfigurationResolver(true));
        $listener($extension);

        static::assertFalse($extension->isPropagationStopped());
        static::assertSame('sales-channel-value', $extension->result['headline']);
        static::assertSame('fallback-value', $extension->result['missing']);
        static::assertArrayNotHasKey('fileOnly', $extension->result);
    }

    public function testInvokeAppliesProviderTranslationsAndStopsPropagation(): void
    {
        $providerResolver = $this->createMock(TranslationProviderResolverInterface::class);
        $provider = $this->createMock(ProviderInterface::class);

        $providerResolver
            ->expects(static::once())
            ->method('hasProvider')
            ->with(self::SALES_CHANNEL_ID)
            ->willReturn(true);
        $providerResolver
            ->expects(static::once())
            ->method('getProvider')
            ->with(self::SALES_CHANNEL_ID)
            ->willReturn($provider);

        $localeCatalogue = new MessageCatalogue('de-DE');
        $localeCatalogue->add([
            'locOnly' => 'from-locale',
            'both' => 'locale-priority'
        ], LoadTranslationsListener::TRANSLATION_DOMAIN);

        $fallbackCatalogue = new MessageCatalogue('en-GB');
        $fallbackCatalogue->add([
            'fbOnly' => 'from-fallback',
            'both' => 'fallback-wins'
        ], LoadTranslationsListener::TRANSLATION_DOMAIN);

        $translationBag = new TranslatorBag();
        $translationBag->addCatalogue($localeCatalogue);
        $translationBag->addCatalogue($fallbackCatalogue);

        $provider
            ->expects(static::once())
            ->method('read')
            ->with([LoadTranslationsListener::TRANSLATION_DOMAIN], ['de-DE', 'en-GB'])
            ->willReturn($translationBag);

        $extension = new StorefrontSnippetsExtension(
            ['locOnly' => 'snippet', 'fbOnly' => 'snippet', 'both' => 'snippet'],
            'de-DE',
            new MessageCatalogue('de-DE'),
            'snippet-set',
            'en-GB',
            self::SALES_CHANNEL_ID,
            []
        );
        $extension->result = $extension->snippets;

        $listener = new LoadTranslationsListener($providerResolver, $this->createConfigurationResolver(false));
        $listener($extension);

        static::assertTrue($extension->isPropagationStopped());
        static::assertSame('from-locale', $extension->result['locOnly']);
        static::assertSame('from-fallback', $extension->result['fbOnly']);
        static::assertSame('fallback-wins', $extension->result['both']);
    }

    public function testSkipSuppressesInvocationOnlyInsideCallback(): void
    {
        $providerResolver = $this->createMock(TranslationProviderResolverInterface::class);
        $providerResolver->expects(static::once())->method('hasProvider')->willReturn(false);

        $listener = new LoadTranslationsListener($providerResolver, $this->createConfigurationResolver(false));
        $extension = new StorefrontSnippetsExtension(
            ['headline' => 'snippet-value'],
            'de-DE',
            new MessageCatalogue('de-DE'),
            'snippet-set',
            null,
            self::SALES_CHANNEL_ID,
            []
        );
        $extension->result = $extension->snippets;

        LoadTranslationsListener::skip(static fn() => $listener($extension));
        $listener($extension);
    }

    private function createConfigurationResolver(bool $respectTranslationFiles): ConfigurationResolver
    {
        $configurationResolver = $this->createStub(ConfigurationResolver::class);
        $configurationResolver->method('respectTranslationFiles')->willReturn($respectTranslationFiles);

        return $configurationResolver;
    }
}
