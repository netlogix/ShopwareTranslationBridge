<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Command;

use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\TranslationProviderResolverInterface;
use Netlogix\ShopwareTranslationBridge\Resolver\ConfigurationResolver;
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
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;

#[AsCommand('sw:snippets:pull')]
class PullSnippetsCommand extends Command
{
    private const string REMOTE_DOMAIN = 'messages';

    private const string STORAGE_DIRECTORY = 'nlx-storefront-translation';

    public function __construct(
        private readonly TranslationProviderResolverInterface $translationProviderResolver,
        private readonly ConfigurationResolver $configurationResolver,
        private readonly EntityRepository $languageRepository,
        private readonly EntityRepository $salesChannelRepository,
        #[Autowire(service: 'translation.writer')]
        private readonly TranslationWriterInterface $translationWriter,
        #[Autowire(param: 'translator.default_path')]
        private readonly string $translatorDefaultPath
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $translationPath = $this->resolveTranslationPath();

        $domainsFetched = 0;

        if (!$this->translationProviderResolver->hasProvider()) {
            $io->warning(
                'No translation provider configured. Configure a default provider or at least one sales channel provider.'
            );

            return Command::SUCCESS;
        }

        $defaultProviderName = $this->configurationResolver->getProviderName();

        $io->note('Fetching translations for keyProvider');
        $domainsFetched += $this->fetchTranslations(
            $this->translationProviderResolver->getProvider(),
            self::REMOTE_DOMAIN,
            $this->getAllLocales(),
            $translationPath,
            $io
        );

        $salesChannelIdsWithProvider = $this->getSalesChannelIdsWithProviderOverride($defaultProviderName);
        if (empty($salesChannelIdsWithProvider)) {
            $io->note('No own sales-channels providers found.');
        }

        foreach ($salesChannelIdsWithProvider as $salesChannelId) {
            $io->note(sprintf('Fetching translations for Sales-Channel-ID: %s.', $salesChannelId));
            $domainsFetched += $this->fetchTranslations(
                $this->translationProviderResolver->getProvider($salesChannelId),
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
        ProviderInterface $provider,
        string $targetDomain,
        array $locales,
        string $translationPath,
        SymfonyStyle $io
    ): int {
        $providerName = $this->getProviderName($provider);

        if ($locales === []) {
            $io->note(sprintf('Skipping "%s" because no locales were found.', $targetDomain));

            return 0;
        }

        $translationBag = $provider->read([self::REMOTE_DOMAIN], $locales);

        $written = 0;

        foreach ($translationBag->getCatalogues() as $catalogue) {
            $messages = $catalogue->all(self::REMOTE_DOMAIN);
            if ($messages === []) {
                continue;
            }

            $newCatalogue = new MessageCatalogue($catalogue->getLocale());
            $newCatalogue->add($messages, $targetDomain);

            $this->translationWriter->write($newCatalogue, 'json', [
                'path' => $translationPath,
            ]);
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

    private function getProviderName(ProviderInterface $provider): string
    {
        return parse_url((string) $provider, \PHP_URL_SCHEME) ?: 'unknown';
    }

    /**
     * Returns sales channels whose configured provider differs from the global default,
     * i.e. a real per-channel override. Channels that merely inherit the default are
     * excluded so their translations are not pulled redundantly.
     *
     * @return list<string>
     */
    private function getSalesChannelIdsWithProviderOverride(string $defaultProviderName): array
    {
        $result = $this->salesChannelRepository->searchIds(new Criteria(), Context::createCLIContext());

        return array_values(array_filter(
            $result->getIds(),
            function (string $salesChannelId) use ($defaultProviderName): bool {
                $providerName = $this->configurationResolver->getProviderName($salesChannelId);

                return $providerName !== null && $providerName !== $defaultProviderName;
            }
        ));
    }

    /**
     * @return list<string>
     */
    private function getAllLocales(): array
    {
        $criteria = new Criteria()
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
        $criteria = new Criteria([$salesChannelId])->addAssociation('languages.locale');
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
        return rtrim($this->translatorDefaultPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::STORAGE_DIRECTORY;
    }
}
