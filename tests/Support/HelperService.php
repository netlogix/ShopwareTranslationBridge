<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Tests\Support;

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\TranslatorBag;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;

final class HelperService
{
    /**
     * @param array<string, string> $messages
     */
    public function createBag(string $locale, array $messages, string $domain = 'messages'): TranslatorBag
    {
        $bag = new TranslatorBag();
        $bag->addCatalogue(new MessageCatalogue($locale, [$domain => $messages]));

        return $bag;
    }

    /**
     * @param list<string> $localeCodes
     */
    public function createLanguageCollection(array $localeCodes): LanguageCollection
    {
        return new LanguageCollection(array_map($this->createLanguageEntity(...), $localeCodes));
    }

    public function createLanguageEntity(string $localeCode, ?string $languageId = null): LanguageEntity
    {
        $language = new LanguageEntity();
        $language->setUniqueIdentifier($languageId ?? md5('language-' . $localeCode));
        $language->setLocale($this->createLocaleEntity($localeCode));

        return $language;
    }

    public function createLocaleEntity(string $localeCode): LocaleEntity
    {
        $locale = new LocaleEntity();
        $locale->setUniqueIdentifier(md5('locale-' . $localeCode));
        $locale->setCode($localeCode);

        return $locale;
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    public function translatorBagToArray(TranslatorBagInterface $bag): array
    {
        $result = [];

        foreach ($bag->getCatalogues() as $catalogue) {
            $result[$catalogue->getLocale()] = $this->catalogueToArray($catalogue);
        }

        ksort($result);

        return $result;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function catalogueToArray(MessageCatalogueInterface $catalogue): array
    {
        $result = [];

        foreach ($catalogue->getDomains() as $domain) {
            $messages = $catalogue->all($domain);
            ksort($messages);
            $result[$domain] = $messages;
        }

        ksort($result);

        return $result;
    }
}
