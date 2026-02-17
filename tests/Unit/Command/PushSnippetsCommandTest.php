<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Tests\Unit\Command;

use Netlogix\ShopwareTranslationBridge\Command\PushSnippetsCommand;
use Netlogix\ShopwareTranslationBridge\Core\System\RelevantLocaleResolverInterface;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\TranslationProviderResolverInterface;
use Netlogix\ShopwareTranslationBridge\Tests\Support\CreatesTranslationBagTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\System\Snippet\SnippetService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;
use Symfony\Component\Translation\TranslatorBag;

#[CoversClass(PushSnippetsCommand::class)]
final class PushSnippetsCommandTest extends TestCase
{
    use CreatesTranslationBagTrait;

    private const string SALES_CHANNEL_ID = '2b919afec10730f413cb5682bbed09fd';

    public function testExecuteFailsWhenInvalidLocalesAreProvided(): void
    {
        $command = new PushSnippetsCommand(
            new TranslationProviderCollection([]),
            $this->createStub(SnippetService::class),
            $this->createStub(AbstractTranslator::class),
            $this->createStub(TranslationProviderResolverInterface::class),
            $this->createLocaleResolver(['de-DE'])
        );

        $commandTester = new CommandTester($command);
        $status = $commandTester->execute(['--locales' => ['fr-FR']]);

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString('not enabled', $commandTester->getDisplay());
    }

    public function testExecutePushesAllTranslationsToDefaultProviderWithForce(): void
    {
        $provider = $this->createMock(ProviderInterface::class);
        $provider
            ->expects(static::once())
            ->method('write')
            ->with(static::callback(
                static fn(TranslatorBag $bag): bool => $bag->getCatalogue('de-DE')->has('greeting', 'messages')
            ));
        $provider->method('__toString')->willReturn('mock://default');

        $providerResolver = $this->createMock(TranslationProviderResolverInterface::class);
        $providerResolver->expects(static::once())->method('getDefaultProvider')->willReturn($provider);
        $providerResolver->expects(static::never())->method('getSalesChannelProvider');

        $translator = $this->createMock(AbstractTranslator::class);
        $translator->expects(static::once())->method('getSnippetSetId')->with('de-DE')->willReturn('snippet-set-id');

        $snippetService = $this->createMock(SnippetService::class);
        $snippetService
            ->expects(static::once())
            ->method('getStorefrontSnippets')
            ->with(static::isInstanceOf(MessageCatalogue::class), 'snippet-set-id')
            ->willReturn(['greeting' => 'Hallo']);

        $command = new PushSnippetsCommand(
            new TranslationProviderCollection([]),
            $snippetService,
            $translator,
            $providerResolver,
            $this->createLocaleResolver(['de-DE'])
        );

        $commandTester = new CommandTester($command);
        $status = $commandTester->execute(['--force' => true]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('All local translations have been sent', $commandTester->getDisplay());
    }

    public function testExecuteDeletesMissingAndWritesDiffForSalesChannelProvider(): void
    {
        $firstProviderTranslations = $this->createBag('de-DE', ['obsolete' => 'to-delete']);
        $secondProviderTranslations = $this->createBag('de-DE', []);

        $provider = $this->createMock(ProviderInterface::class);
        $provider
            ->expects(static::exactly(2))
            ->method('read')
            ->with(['messages'], ['de-DE'])
            ->willReturnOnConsecutiveCalls($firstProviderTranslations, $secondProviderTranslations);
        $provider
            ->expects(static::once())
            ->method('delete')
            ->with(static::callback(
                static fn(TranslatorBag $bag): bool => $bag->getCatalogue('de-DE')->has('obsolete', 'messages')
            ));
        $provider
            ->expects(static::once())
            ->method('write')
            ->with(static::callback(
                static fn(TranslatorBag $bag): bool => $bag->getCatalogue('de-DE')->has('greeting', 'messages')
            ));
        $provider->method('__toString')->willReturn('mock://sales-channel');

        $providerResolver = $this->createMock(TranslationProviderResolverInterface::class);
        $providerResolver
            ->expects(static::once())
            ->method('getSalesChannelProvider')
            ->with(self::SALES_CHANNEL_ID)
            ->willReturn($provider);
        $providerResolver->expects(static::never())->method('getDefaultProvider');

        $translator = $this->createMock(AbstractTranslator::class);
        $translator->expects(static::once())->method('getSnippetSetId')->with('de-DE')->willReturn('snippet-set-id');

        $snippetService = $this->createMock(SnippetService::class);
        $snippetService
            ->expects(static::once())
            ->method('getStorefrontSnippets')
            ->with(static::isInstanceOf(MessageCatalogue::class), 'snippet-set-id')
            ->willReturn(['greeting' => 'Hallo']);

        $command = new PushSnippetsCommand(
            new TranslationProviderCollection([]),
            $snippetService,
            $translator,
            $providerResolver,
            $this->createLocaleResolver(['de-DE'])
        );

        $commandTester = new CommandTester($command);
        $status = $commandTester->execute([
            'salesChannelId' => [self::SALES_CHANNEL_ID],
            '--locales' => ['de-DE'],
            '--delete-missing' => true
        ]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('Missing translations', $commandTester->getDisplay());
        static::assertStringContainsString('New local translations have been sent', $commandTester->getDisplay());
    }

    private function createLocaleResolver(array $allLocales): RelevantLocaleResolverInterface
    {
        $localeResolver = $this->createStub(RelevantLocaleResolverInterface::class);
        $localeResolver->method('getAll')->willReturn($allLocales);

        return $localeResolver;
    }
}
