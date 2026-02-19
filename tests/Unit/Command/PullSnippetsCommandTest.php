<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Tests\Unit\Command;

use Netlogix\ShopwareTranslationBridge\Command\PullSnippetsCommand;
use Netlogix\ShopwareTranslationBridge\Tests\Support\CreatesLanguageEntitiesTrait;
use Netlogix\ShopwareTranslationBridge\Tests\Support\CreatesTranslationBagTrait;
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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;

#[CoversClass(PullSnippetsCommand::class)]
final class PullSnippetsCommandTest extends TestCase
{
    use CreatesLanguageEntitiesTrait;
    use CreatesTranslationBagTrait;

    private const string SALES_CHANNEL_ID = '2b919afec10730f413cb5682bbed09fd';

    public function testExecuteWarnsWhenNoProviderConfigurationExists(): void
    {
        $languageRepository = $this->createStub(EntityRepository::class);
        $salesChannelRepository = $this->createStub(EntityRepository::class);
        $writer = $this->createMock(TranslationWriterInterface::class);
        $writer->expects(static::never())->method('write');

        $command = new PullSnippetsCommand(
            new TranslationProviderCollection([]),
            $languageRepository,
            $salesChannelRepository,
            $writer,
            $this->createProjectDir(),
            null,
            [],
        );

        $commandTester = new CommandTester($command);
        $status = $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('No translations fetched', $commandTester->getDisplay());
    }

    public function testExecuteFetchesFromDefaultProviderAndWritesTranslations(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider
            ->expects(static::once())
            ->method('read')
            ->with(['messages'], ['de-DE'])
            ->willReturn($this->createBag('de-DE', ['welcome' => 'Willkommen']));

        $writer = $this->createMock(TranslationWriterInterface::class);
        $writer
            ->expects(static::once())
            ->method('write')
            ->with(
                static::callback(
                    static fn(MessageCatalogue $catalogue): bool => $catalogue->has('welcome', 'messages')
                ),
                'json',
                static::callback(
                    static fn(array $options): bool => array_key_exists('path', $options) && is_string($options['path'])
                )
            );

        $languageRepository = $this->createMock(EntityRepository::class);
        $languageRepository
            ->expects(static::once())
            ->method('search')
            ->willReturn($this->createLanguageSearchResult(['de-DE']));

        $salesChannelRepository = $this->createMock(EntityRepository::class);
        $salesChannelRepository->expects(static::never())->method('search');

        $command = new PullSnippetsCommand(
            new TranslationProviderCollection(['default-provider' => $provider]),
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
    }

    public function testExecuteSkipsSalesChannelProviderWhenNoLocalesWereFound(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects(static::never())->method('read');

        $languageRepository = $this->createMock(EntityRepository::class);
        $languageRepository->expects(static::never())->method('search');

        $salesChannelRepository = $this->createMock(EntityRepository::class);
        $salesChannelRepository
            ->expects(static::once())
            ->method('search')
            ->willReturn($this->createSalesChannelSearchResult());

        $writer = $this->createMock(TranslationWriterInterface::class);
        $writer->expects(static::never())->method('write');

        $command = new PullSnippetsCommand(
            new TranslationProviderCollection(['sales-provider' => $provider]),
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
            $this->createLanguageCollection($localeCodes),
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
}
