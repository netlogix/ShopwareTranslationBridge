<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Tests\Unit\Command;

use Netlogix\ShopwareTranslationBridge\Command\PullSnippetsCommand;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\TranslationProviderResolverInterface;
use Netlogix\ShopwareTranslationBridge\Resolver\ConfigurationResolver;
use Netlogix\ShopwareTranslationBridge\Tests\Support\HelperService;
use Netlogix\ShopwareTranslationBridge\Tests\Support\Provider\InMemoryTestProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;

#[CoversClass(PullSnippetsCommand::class)]
final class PullSnippetsCommandTest extends TestCase
{
    private const string SALES_CHANNEL_ID = '2b919afec10730f413cb5682bbed09fd';
    private const string TRANSLATOR_DEFAULT_PATH = '/tmp/nlx-translation-bridge';

    private HelperService $helperService;

    protected function setUp(): void
    {
        $this->helperService = new HelperService();
    }

    public function testExecuteWarnsWhenNoProviderIsConfigured(): void
    {
        $resolver = $this->createStub(TranslationProviderResolverInterface::class);
        $resolver->method('hasProvider')->willReturn(false);

        $writer = $this->createRecordingWriter();

        $command = new PullSnippetsCommand(
            $resolver,
            $this->createStub(ConfigurationResolver::class),
            $this->createStub(EntityRepository::class),
            $this->createStub(EntityRepository::class),
            $writer,
            self::TRANSLATOR_DEFAULT_PATH
        );

        $commandTester = new CommandTester($command);
        $status = $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('No translation provider configured', $commandTester->getDisplay());
        static::assertCount(0, $writer->writes);
    }

    public function testExecuteFetchesFromDefaultProviderAndWritesTranslations(): void
    {
        $provider = new InMemoryTestProvider(
            'default-provider',
            $this->helperService->createBag('de-DE', ['welcome' => 'Willkommen'])
        );

        $resolver = $this->createStub(TranslationProviderResolverInterface::class);
        $resolver->method('hasProvider')->willReturn(true);
        $resolver->method('getProvider')->willReturn($provider);

        $configurationResolver = $this->createStub(ConfigurationResolver::class);
        $configurationResolver->method('getProviderName')->willReturn('default-provider');

        $languageRepository = $this->createStub(EntityRepository::class);
        $languageRepository->method('search')->willReturn($this->createLanguageSearchResult(['de-DE']));

        $salesChannelRepository = $this->createStub(EntityRepository::class);
        $salesChannelRepository->method('searchIds')->willReturn($this->createIdSearchResult([]));

        $writer = $this->createRecordingWriter();

        $command = new PullSnippetsCommand(
            $resolver,
            $configurationResolver,
            $languageRepository,
            $salesChannelRepository,
            $writer,
            self::TRANSLATOR_DEFAULT_PATH
        );

        $commandTester = new CommandTester($command);
        $status = $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('Fetched translations for 1 domain', $commandTester->getDisplay());
        static::assertCount(1, $writer->writes);
        static::assertSame('json', $writer->writes[0]['format']);
        static::assertArrayHasKey('path', $writer->writes[0]['options']);

        $writtenBag = new TranslatorBag();
        $writtenBag->addCatalogue($writer->writes[0]['catalogue']);
        static::assertSame(
            $this->helperService->translatorBagToArray(
                $this->helperService->createBag('de-DE', ['welcome' => 'Willkommen'])
            ),
            $this->helperService->translatorBagToArray($writtenBag)
        );
    }

    public function testExecuteWarnsWhenNoTranslationsWereFetched(): void
    {
        $resolver = $this->createStub(TranslationProviderResolverInterface::class);
        $resolver->method('hasProvider')->willReturn(true);
        $resolver->method('getProvider')->willReturn(new InMemoryTestProvider('default-provider'));

        $configurationResolver = $this->createStub(ConfigurationResolver::class);
        $configurationResolver->method('getProviderName')->willReturn('default-provider');

        // No enabled locales -> nothing can be fetched for the default provider.
        $languageRepository = $this->createStub(EntityRepository::class);
        $languageRepository->method('search')->willReturn($this->createLanguageSearchResult([]));

        $salesChannelRepository = $this->createStub(EntityRepository::class);
        $salesChannelRepository->method('searchIds')->willReturn($this->createIdSearchResult([]));

        $writer = $this->createRecordingWriter();

        $command = new PullSnippetsCommand(
            $resolver,
            $configurationResolver,
            $languageRepository,
            $salesChannelRepository,
            $writer,
            self::TRANSLATOR_DEFAULT_PATH
        );

        $commandTester = new CommandTester($command);
        $status = $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('No translations fetched', $commandTester->getDisplay());
        static::assertCount(0, $writer->writes);
    }

    public function testExecuteFetchesFromSalesChannelProviderOverride(): void
    {
        $defaultProvider = new InMemoryTestProvider('default-provider');
        $salesChannelProvider = new InMemoryTestProvider(
            'sales-provider',
            $this->helperService->createBag('de-DE', ['checkout' => 'Kasse'])
        );

        $resolver = $this->createStub(TranslationProviderResolverInterface::class);
        $resolver->method('hasProvider')->willReturn(true);
        $resolver->method('getProvider')->willReturnCallback(
            static fn (?string $salesChannelId = null): InMemoryTestProvider =>
                $salesChannelId === null ? $defaultProvider : $salesChannelProvider
        );

        // Default provider name differs from the sales-channel one -> channel is treated as an override.
        $configurationResolver = $this->createStub(ConfigurationResolver::class);
        $configurationResolver->method('getProviderName')->willReturnCallback(
            static fn (?string $salesChannelId = null): string =>
                $salesChannelId === null ? 'default-provider' : 'sales-provider'
        );

        // No global locales -> only the sales-channel override produces a write.
        $languageRepository = $this->createStub(EntityRepository::class);
        $languageRepository->method('search')->willReturn($this->createLanguageSearchResult([]));

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setUniqueIdentifier(self::SALES_CHANNEL_ID);
        $salesChannel->setLanguages($this->helperService->createLanguageCollection(['de-DE']));

        $salesChannelRepository = $this->createStub(EntityRepository::class);
        $salesChannelRepository->method('searchIds')->willReturn(
            $this->createIdSearchResult([self::SALES_CHANNEL_ID])
        );
        $salesChannelRepository->method('search')->willReturn(
            $this->createSalesChannelSearchResult($salesChannel)
        );

        $writer = $this->createRecordingWriter();

        $command = new PullSnippetsCommand(
            $resolver,
            $configurationResolver,
            $languageRepository,
            $salesChannelRepository,
            $writer,
            self::TRANSLATOR_DEFAULT_PATH
        );

        $commandTester = new CommandTester($command);
        $status = $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('Fetched translations for 1 domain', $commandTester->getDisplay());
        static::assertCount(1, $writer->writes);

        $catalogue = $writer->writes[0]['catalogue'];
        static::assertTrue($catalogue->defines('checkout', self::SALES_CHANNEL_ID));
        static::assertSame('Kasse', $catalogue->get('checkout', self::SALES_CHANNEL_ID));
    }

    /**
     * @param list<string> $localeCodes
     */
    private function createLanguageSearchResult(array $localeCodes): EntitySearchResult
    {
        return new EntitySearchResult(
            'language',
            count($localeCodes),
            $this->helperService->createLanguageCollection($localeCodes),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    private function createSalesChannelSearchResult(?SalesChannelEntity $salesChannel = null): EntitySearchResult
    {
        $collection = new SalesChannelCollection($salesChannel === null ? [] : [$salesChannel]);

        return new EntitySearchResult(
            'sales_channel',
            $salesChannel === null ? 0 : 1,
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }

    /**
     * @param list<string> $ids
     */
    private function createIdSearchResult(array $ids): IdSearchResult
    {
        $idSearchResult = $this->createStub(IdSearchResult::class);
        $idSearchResult->method('getIds')->willReturn($ids);

        return $idSearchResult;
    }

    private function createRecordingWriter(): object
    {
        return new class() implements TranslationWriterInterface {
            public array $writes = [];

            public function write(MessageCatalogue $catalogue, string $format, array $options = []): void
            {
                $this->writes[] = [
                    'catalogue' => $catalogue,
                    'format' => $format,
                    'options' => $options
                ];
            }
        };
    }
}
