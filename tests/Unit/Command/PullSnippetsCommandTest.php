<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Tests\Unit\Command;

use Symfony\Component\Translation\MessageCatalogue;
use Netlogix\ShopwareTranslationBridge\Command\PullSnippetsCommand;
use Netlogix\ShopwareTranslationBridge\Tests\Support\HelperService;
use Netlogix\ShopwareTranslationBridge\Tests\Support\Provider\InMemoryTestProviderFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;

#[CoversClass(PullSnippetsCommand::class)]
final class PullSnippetsCommandTest extends TestCase
{
    private const string SALES_CHANNEL_ID = '2b919afec10730f413cb5682bbed09fd';

    private HelperService $helperService;
    private InMemoryTestProviderFactory $providerFactory;

    protected function setUp(): void
    {
        $this->helperService = new HelperService();
        $this->providerFactory = new InMemoryTestProviderFactory();
    }

    public function testExecuteWarnsWhenNoProviderConfigurationExists(): void
    {
        $languageRepository = $this->createStub(EntityRepository::class);
        $salesChannelRepository = $this->createStub(EntityRepository::class);
        $writer = $this->createRecordingWriter();

        $command = new PullSnippetsCommand(
            new TranslationProviderCollection([]),
            $languageRepository,
            $salesChannelRepository,
            $writer,
            $this->createProjectDir(),
            null,
            []
        );

        $commandTester = new CommandTester($command);
        $status = $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('No translations fetched', $commandTester->getDisplay());
        static::assertCount(0, $writer->writes);
    }

    public function testExecuteFetchesFromDefaultProviderAndWritesTranslations(): void
    {
        $providers = $this->providerFactory->createPullProviderCollection();
        $writer = $this->createRecordingWriter();

        $languageRepository = $this->createStub(EntityRepository::class);
        $languageRepository->method('search')->willReturn($this->createLanguageSearchResult(['de-DE']));
        $salesChannelRepository = $this->createStub(EntityRepository::class);

        $command = new PullSnippetsCommand(
            $providers,
            $languageRepository,
            $salesChannelRepository,
            $writer,
            $this->createProjectDir(),
            'default-provider',
            []
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
            $this->helperService->translatorBagToArray($this->helperService->createBag('de-DE', [
                'welcome' => 'Willkommen'
            ])),
            $this->helperService->translatorBagToArray($writtenBag)
        );
    }

    public function testExecuteSkipsSalesChannelProviderWhenNoLocalesWereFound(): void
    {
        $providers = $this->providerFactory->createPullProviderCollection();

        $languageRepository = $this->createStub(EntityRepository::class);
        $salesChannelRepository = $this->createStub(EntityRepository::class);
        $salesChannelRepository->method('search')->willReturn($this->createSalesChannelSearchResult());
        $writer = $this->createRecordingWriter();

        $command = new PullSnippetsCommand(
            $providers,
            $languageRepository,
            $salesChannelRepository,
            $writer,
            $this->createProjectDir(),
            null,
            [self::SALES_CHANNEL_ID => 'sales-provider']
        );

        $commandTester = new CommandTester($command);
        $status = $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('No translations fetched', $commandTester->getDisplay());
        static::assertCount(0, $writer->writes);
    }

    public function testExecuteThrowsExceptionWhenProviderNotFound(): void
    {
        $languageRepository = $this->createStub(EntityRepository::class);
        $languageRepository->method('search')->willReturn($this->createLanguageSearchResult(['de-DE']));
        $salesChannelRepository = $this->createStub(EntityRepository::class);

        $command = new PullSnippetsCommand(
            new TranslationProviderCollection([]),
            $languageRepository,
            $salesChannelRepository,
            $this->createRecordingWriter(),
            $this->createProjectDir(),
            'non-existent-provider',
            []
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provider "non-existent-provider" not found.');

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);
    }

    public function testExecuteFetchesFromSalesChannelProviderAndWritesTranslations(): void
    {
        $providers = $this->providerFactory->createPullProviderCollection();
        $writer = $this->createRecordingWriter();

        $languageRepository = $this->createStub(EntityRepository::class);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setUniqueIdentifier(self::SALES_CHANNEL_ID);
        $salesChannel->setLanguages($this->helperService->createLanguageCollection(['de-DE']));

        $salesChannelRepository = $this->createStub(EntityRepository::class);
        $salesChannelRepository->method('search')->willReturn(
            $this->createSalesChannelSearchResult($salesChannel)
        );

        $command = new PullSnippetsCommand(
            $providers,
            $languageRepository,
            $salesChannelRepository,
            $writer,
            $this->createProjectDir(),
            null,
            [self::SALES_CHANNEL_ID => 'sales-provider']
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

    private function createProjectDir(): string
    {
        $projectDir = sys_get_temp_dir() . '/shopware-translation-bridge-tests-' . uniqid('', true);
        mkdir($projectDir, recursive: true);

        return $projectDir;
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
