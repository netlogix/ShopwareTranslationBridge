<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Command;

use Netlogix\ShopwareTranslationBridge\Core\System\RelevantLocaleResolverInterface;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\LoadTranslationsListener;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\TranslationProviderResolverInterface;
use Override;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\System\Snippet\SnippetService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;
use Symfony\Component\Translation\TranslatorBag;

#[AsCommand('sw:snippets:push')]
class PushSnippetsCommand extends Command
{
    function __construct(
        #[Autowire(service: 'translation.provider_collection')]
        private readonly TranslationProviderCollection $providers,
        private readonly SnippetService $snippetService,
        #[Autowire(service: Translator::class)]
        private readonly AbstractTranslator $translator,
        private readonly TranslationProviderResolverInterface $translationProviderResolver,
        private readonly RelevantLocaleResolverInterface $relevantLocaleResolver,
    ) {
        parent::__construct();
    }

    #[Override]
    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('provider')) {
            $suggestions->suggestValues($this->getProviderKeys());
        }
    }

    /**
     * @return list<string>
     */
    private function getProviderKeys(): array
    {
        return $this->providers->keys();
    }

    protected function configure(): void
    {
        $this->setDefinition([
            new InputArgument(
                'salesChannelId',
                InputArgument::IS_ARRAY,
                'The salesChannelId which should be updated. If "default" or empty it updates the default provider.',
                []
            ),
            new InputOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Override existing translations with local ones (it will delete not synchronized messages).'
            ),
            new InputOption(
                'delete-missing',
                null,
                InputOption::VALUE_NONE,
                'Delete translations available on provider but not locally.'
            ),
            new InputOption(
                'locales',
                'l',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Specify the locales to push.'
            )
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $salesChannelIds = $input->getArgument('salesChannelId');

        assert(is_array($salesChannelIds));
        $updateDefaultProvider = $salesChannelIds === [] || key_exists('default', $salesChannelIds);

        // @todo: ...

        /** @var string[] $locales */
        $locales = $input->getOption('locales');
        assert(is_array($locales));
        if ($locales === []) {
            $locales = $this->relevantLocaleResolver->getAll();
            $io->info(sprintf('The following locales are going to be pushed: %s', implode(', ', $locales)));
        } else {
            $missingLocales = array_diff($locales, $this->relevantLocaleResolver->getAll());
            if ($missingLocales !== []) {
                $io->error(sprintf('The following locales are not enabled: %s', implode(', ', $missingLocales)));

                return Command::FAILURE;
            }
        }

        $force = $input->getOption('force');
        assert(is_bool($force));
        $deleteMissing = $input->getOption('delete-missing');
        $localTranslations = $this->getTranslations($locales);

        if (!$deleteMissing && $force) {
            $provider->write($localTranslations);
            $io->success(
                \sprintf(
                    'All local translations has been sent to "%s" (for "%s" locale(s)).',
                    parse_url((string) $provider, \PHP_URL_SCHEME),
                    implode(', ', $locales)
                )
            );

            return Command::SUCCESS;
        }

        $providerTranslations = $provider->read(['messages'], $locales);

        if ($deleteMissing) {
            $provider->delete($providerTranslations->diff($localTranslations));

            $io->success(
                \sprintf(
                    'Missing translations on "%s" has been deleted (for "%s" locale(s)).',
                    parse_url((string) $provider, \PHP_URL_SCHEME),
                    implode(', ', $locales)
                )
            );

            $providerTranslations = $provider->read(['messages'], $locales);
        }

        $translationsToWrite = $localTranslations->diff($providerTranslations);

        if ($force) {
            $translationsToWrite->addBag($localTranslations->intersect($providerTranslations));
        }

        $provider->write($translationsToWrite);

        $io->success(
            \sprintf(
                '%s local translations has been sent to "%s" (for "%s" locale(s)).',
                $force ? 'All' : 'New',
                parse_url((string) $provider, \PHP_URL_SCHEME),
                implode(', ', $locales)
            )
        );

        return Command::SUCCESS;
    }

    /**
     * @param string[] $locales
     */
    private function getTranslations(array $locales): TranslatorBag
    {
        $translationBag = new TranslatorBag();
        LoadTranslationsListener::skip(function () use ($locales, $translationBag): void {
            foreach ($locales as $locale) {
                $catalogue = new MessageCatalogue($locale);
                $snippetSetId = $this->translator->getSnippetSetId($catalogue->getLocale());
                assert($snippetSetId !== null);
                $translations = $this->snippetService->getStorefrontSnippets($catalogue, $snippetSetId);
                $catalogue->add($translations, 'messages');
                $translationBag->addCatalogue($catalogue);
            }
        });

        return $translationBag;
    }
}
