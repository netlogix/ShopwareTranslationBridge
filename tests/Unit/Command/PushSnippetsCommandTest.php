<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Tests\Unit\Command;

use Netlogix\ShopwareTranslationBridge\Command\PushSnippetsCommand;
use Netlogix\ShopwareTranslationBridge\Core\System\RelevantLocaleResolverInterface;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\TranslationProviderResolverInterface;
use Netlogix\ShopwareTranslationBridge\Tests\Support\HelperService;
use Netlogix\ShopwareTranslationBridge\Tests\Support\Provider\InMemoryTestProvider;
use Netlogix\ShopwareTranslationBridge\Tests\Support\Provider\InMemoryTestProviderFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\System\Snippet\SnippetService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;

#[CoversClass(PushSnippetsCommand::class)]
final class PushSnippetsCommandTest extends TestCase
{
    private const string SALES_CHANNEL_ID = '2b919afec10730f413cb5682bbed09fd';
    private const string PUSH_PROVIDER_FIXTURE = __DIR__ . '/../../Fixture/providers/push-provider.json';

    private HelperService $helperService;
    private InMemoryTestProviderFactory $providerFactory;

    protected function setUp(): void
    {
        $this->helperService = new HelperService();
        $this->providerFactory = new InMemoryTestProviderFactory();
    }

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
        $providers = $this->providerFactory->createCollectionFromJsonFile(self::PUSH_PROVIDER_FIXTURE);
        $provider = $providers->get('default-provider');
        static::assertInstanceOf(InMemoryTestProvider::class, $provider);

        $providerResolver = $this->createStub(TranslationProviderResolverInterface::class);
        $providerResolver->method('getDefaultProvider')->willReturn($provider);

        $translator = $this->createStub(AbstractTranslator::class);
        $translator->method('getSnippetSetId')->willReturn('snippet-set-id');

        $snippetService = $this->createStub(SnippetService::class);
        $snippetService->method('getStorefrontSnippets')->willReturn(['greeting' => 'Hallo']);

        $command = new PushSnippetsCommand(
            $providers,
            $snippetService,
            $translator,
            $providerResolver,
            $this->createLocaleResolver(['de-DE'])
        );

        $commandTester = new CommandTester($command);
        $status = $commandTester->execute(['--force' => true]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('All local translations have been sent', $commandTester->getDisplay());
        static::assertSame(
            $this->helperService->translatorBagToArray($this->helperService->createBag('de-DE', [
                'greeting' => 'Hallo'
            ])),
            $this->helperService->translatorBagToArray($provider->read(['messages'], ['de-DE']))
        );
    }

    public function testExecuteDeletesMissingAndWritesDiffForSalesChannelProvider(): void
    {
        $providers = $this->providerFactory->createCollectionFromJsonFile(self::PUSH_PROVIDER_FIXTURE);
        $provider = $providers->get('sales-provider');
        static::assertInstanceOf(InMemoryTestProvider::class, $provider);

        $providerResolver = $this->createStub(TranslationProviderResolverInterface::class);
        $providerResolver->method('getSalesChannelProvider')->willReturn($provider);

        $translator = $this->createStub(AbstractTranslator::class);
        $translator->method('getSnippetSetId')->willReturn('snippet-set-id');

        $snippetService = $this->createStub(SnippetService::class);
        $snippetService->method('getStorefrontSnippets')->willReturn(['greeting' => 'Hallo']);

        $command = new PushSnippetsCommand(
            $providers,
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
        static::assertSame(
            $this->helperService->translatorBagToArray($this->helperService->createBag('de-DE', [
                'greeting' => 'Hallo'
            ])),
            $this->helperService->translatorBagToArray($provider->read(['messages'], ['de-DE']))
        );
    }

    public function testExecutePushesOnlyNewTranslationsWithoutForce(): void
    {
        $providers = $this->providerFactory->createCollectionFromJsonFile(self::PUSH_PROVIDER_FIXTURE);
        $provider = $providers->get('default-provider');
        static::assertInstanceOf(InMemoryTestProvider::class, $provider);

        // Provider hat bereits eine existierende Übersetzung
        $provider->write($this->helperService->createBag('de-DE', [
            'existing' => 'Existing'
        ]));

        $providerResolver = $this->createStub(TranslationProviderResolverInterface::class);
        $providerResolver->method('getDefaultProvider')->willReturn($provider);

        $translator = $this->createStub(AbstractTranslator::class);
        $translator->method('getSnippetSetId')->willReturn('snippet-set-id');

        $snippetService = $this->createStub(SnippetService::class);
        $snippetService->method('getStorefrontSnippets')->willReturn([
            'existing' => 'Existing',
            'new' => 'Neu'
        ]);

        $command = new PushSnippetsCommand(
            $providers,
            $snippetService,
            $translator,
            $providerResolver,
            $this->createLocaleResolver(['de-DE'])
        );

        $commandTester = new CommandTester($command);
        $status = $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('New local translations have been sent', $commandTester->getDisplay());

        $result = $provider->read(['messages'], ['de-DE']);
        $resultArray = $this->helperService->translatorBagToArray($result);

        static::assertSame([
            'de-DE' => [
                'messages' => [
                    'existing' => 'Existing',
                    'new' => 'Neu'
                ]
            ]
        ], $resultArray);
    }

    public function testExecuteOverridesExistingTranslationsWithForce(): void
    {
        $providers = $this->providerFactory->createCollectionFromJsonFile(self::PUSH_PROVIDER_FIXTURE);
        $provider = $providers->get('default-provider');
        static::assertInstanceOf(InMemoryTestProvider::class, $provider);

        // Provider hat alte Werte
        $provider->write($this->helperService->createBag('de-DE', [
            'greeting' => 'Alter Wert'
        ]));

        $providerResolver = $this->createStub(TranslationProviderResolverInterface::class);
        $providerResolver->method('getDefaultProvider')->willReturn($provider);

        $translator = $this->createStub(AbstractTranslator::class);
        $translator->method('getSnippetSetId')->willReturn('snippet-set-id');

        $snippetService = $this->createStub(SnippetService::class);
        $snippetService->method('getStorefrontSnippets')->willReturn([
            'greeting' => 'Neuer Wert'
        ]);

        $command = new PushSnippetsCommand(
            $providers,
            $snippetService,
            $translator,
            $providerResolver,
            $this->createLocaleResolver(['de-DE'])
        );

        $commandTester = new CommandTester($command);
        $status = $commandTester->execute(['--force' => true]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('All local translations have been sent', $commandTester->getDisplay());

        $result = $provider->read(['messages'], ['de-DE']);
        $messages = $result->getCatalogue('de-DE')->all('messages');

        static::assertSame('Neuer Wert', $messages['greeting']);
    }

    private function createLocaleResolver(array $allLocales): RelevantLocaleResolverInterface
    {
        $localeResolver = $this->createStub(RelevantLocaleResolverInterface::class);
        $localeResolver->method('getAll')->willReturn($allLocales);

        return $localeResolver;
    }
}
