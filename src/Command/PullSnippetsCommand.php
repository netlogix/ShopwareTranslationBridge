<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Command;

use RuntimeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;

#[AsCommand('sw:snippets:pull')]
class PullSnippetsCommand extends Command
{
    private const string REMOTE_DOMAIN = 'messages';
    private const string STORAGE_DIRECTORY = 'nlx-storefront-translation';

    function __construct(
        #[Autowire(service: 'translation.provider_collection')]
        private readonly TranslationProviderCollection $providers,
        private readonly EntityRepository $languageRepository,
        private readonly EntityRepository $salesChannelRepository,
        #[Autowire(service: 'translation.writer')]
        private readonly TranslationWriterInterface $translationWriter,
        private readonly ParameterBagInterface $parameterBag,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        #[Autowire(param: 'nlx_storefront_translation.default_provider')]
        private readonly ?string $defaultProvider,
        #[Autowire(param: 'nlx_storefront_translation.sales_channel_provider')]
        private readonly array $salesChannelProviders
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $translationPath = $this->resolveTranslationPath();
        $this->ensureDirectoryExists($translationPath);

        $domainsFetched = 0;

        if (is_string($this->defaultProvider) && $this->defaultProvider !== '') {
            $domainsFetched += $this->fetchTranslations(
                $this->defaultProvider,
                self::REMOTE_DOMAIN,
                $this->getAllLocales(),
                $translationPath,
                $io
            );
        }

        foreach ($this->salesChannelProviders as $salesChannelId => $providerName) {
            if (!is_string($providerName)) {
                continue;
            }

            $domainsFetched += $this->fetchTranslations(
                $providerName,
                $salesChannelId,
                $this->getLocalesForSalesChannel($salesChannelId),
                $translationPath,
                $io
            );
        }

        if ($domainsFetched === 0) {
            $io->warning(
                'No translations fetched. Configure a default provider or at least one sales channel provider.'
            );

            return Command::SUCCESS;
        }

        $io->success(sprintf('Fetched translations for %d domain(s).', $domainsFetched));

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $locales
     */
    private function fetchTranslations(
        string $providerName,
        string $targetDomain,
        array $locales,
        string $translationPath,
        SymfonyStyle $io
    ): int {
        if ($locales === []) {
            $io->note(sprintf('Skipping "%s" because no locales were found.', $targetDomain));

            return 0;
        }

        if (!$this->providers->has($providerName)) {
            throw new RuntimeException(sprintf('Provider "%s" not found.', $providerName));
        }

        $provider = $this->providers->get($providerName);
        $translationBag = $provider->read([self::REMOTE_DOMAIN], $locales);

        $written = 0;

        foreach ($translationBag->getCatalogues() as $catalogue) {
            $messages = $catalogue->all(self::REMOTE_DOMAIN);
            if ($messages === []) {
                continue;
            }

            $newCatalogue = new MessageCatalogue($catalogue->getLocale());
            $newCatalogue->add($messages, $targetDomain);

            $this->translationWriter->write($newCatalogue, 'json', ['path' => $translationPath]);
            ++$written;
        }

        if ($written === 0) {
            $io->note(sprintf('Provider "%s" did not return messages for domain "%s".', $providerName, $targetDomain));

            return 0;
        }

        $io->writeln(sprintf(
            'Stored translations for domain "%s" from provider "%s" (%s).',
            $targetDomain,
            $providerName,
            implode(', ', $locales)
        ));

        return 1;
    }

    /**
     * @return list<string>
     */
    private function getAllLocales(): array
    {
        $criteria = (new Criteria())
            ->addAssociation('locale');
        $result = $this->languageRepository->search($criteria, Context::createCLIContext());
        $languages = $result->getEntities();
        assert($languages instanceof LanguageCollection);

        return $this->extractLocaleCodes($languages);
    }

    /**
     * @return list<string>
     */
    private function getLocalesForSalesChannel(string $salesChannelId): array
    {
        $criteria = (new Criteria([$salesChannelId]))->addAssociation('languages.locale');
        $salesChannel = $this->salesChannelRepository->search($criteria, Context::createCLIContext())->get(
            $salesChannelId
        );

        if (!$salesChannel instanceof SalesChannelEntity) {
            return [];
        }

        $languages = $salesChannel->getLanguages();
        if (!$languages instanceof LanguageCollection) {
            return [];
        }

        return $this->extractLocaleCodes($languages);
    }

    /**
     * @return list<string>
     */
    private function extractLocaleCodes(LanguageCollection $languages): array
    {
        $codes = [];
        foreach ($languages as $language) {
            $code = $language->getLocale()?->getCode();
            if ($code !== null) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    private function resolveTranslationPath(): string
    {
        $path = $this->parameterBag->has('framework.translator.default_path')
            ? (string) $this->parameterBag->get('framework.translator.default_path')
            : $this->projectDir . '/translations';

        return rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::STORAGE_DIRECTORY;
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create translation directory "%s".', $path));
        }
    }
}
