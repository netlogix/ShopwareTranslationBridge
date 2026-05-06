<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Tests\Support\Provider;

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\TranslatorBagInterface;

final class InMemoryTestProvider implements ProviderInterface
{
    /**
     * @var array<string, MessageCatalogue>
     */
    private array $catalogues = [];

    public function __construct(
        private readonly string $name,
        ?TranslatorBagInterface $seed = null
    ) {
        if ($seed === null) {
            return;
        }

        $this->write($seed);
    }

    public function write(TranslatorBagInterface $translatorBag): void
    {
        foreach ($translatorBag->getCatalogues() as $catalogue) {
            $locale = $catalogue->getLocale();
            $targetCatalogue = $this->catalogues[$locale] ?? new MessageCatalogue($locale);

            foreach ($catalogue->getDomains() as $domain) {
                $targetCatalogue->add($catalogue->all($domain), $domain);
            }

            $this->catalogues[$locale] = $targetCatalogue;
        }
    }

    public function read(array $domains, array $locales): TranslatorBag
    {
        $bag = new TranslatorBag();
        $availableLocales = array_values(array_intersect($locales, array_keys($this->catalogues)));

        foreach ($availableLocales as $locale) {
            $catalogue = $this->filterCatalogue($this->catalogues[$locale], $domains);
            $bag->addCatalogue($catalogue);
        }

        return $bag;
    }

    public function delete(TranslatorBagInterface $translatorBag): void
    {
        foreach ($translatorBag->getCatalogues() as $catalogueToDelete) {
            $locale = $catalogueToDelete->getLocale();
            $catalogue = $this->catalogues[$locale] ?? new MessageCatalogue($locale);
            $this->deleteFromCatalogue($catalogue, $catalogueToDelete);
            $this->catalogues[$locale] = $catalogue;
        }
    }

    public function __toString(): string
    {
        return 'memory://' . $this->name;
    }

    private function filterCatalogue(MessageCatalogue $sourceCatalogue, array $domains): MessageCatalogue
    {
        $catalogue = new MessageCatalogue($sourceCatalogue->getLocale());
        $readDomains = $domains === [] ? $sourceCatalogue->getDomains() : $domains;

        foreach ($readDomains as $domain) {
            $catalogue->add($sourceCatalogue->all($domain), $domain);
        }

        return $catalogue;
    }

    private function deleteFromCatalogue(MessageCatalogue $catalogue, MessageCatalogue $catalogueToDelete): void
    {
        foreach ($catalogueToDelete->getDomains() as $domain) {
            $catalogue->replace(array_diff_key($catalogue->all($domain), $catalogueToDelete->all($domain)), $domain);
        }
    }
}
