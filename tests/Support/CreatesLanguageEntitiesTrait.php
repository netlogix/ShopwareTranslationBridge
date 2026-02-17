<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Tests\Support;

use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;

trait CreatesLanguageEntitiesTrait
{
    /**
     * @param list<string> $localeCodes
     */
    protected function createLanguageCollection(array $localeCodes): LanguageCollection
    {
        return new LanguageCollection(array_map($this->createLanguageEntity(...), $localeCodes));
    }

    protected function createLanguageEntity(string $localeCode, ?string $languageId = null): LanguageEntity
    {
        $language = new LanguageEntity();
        $language->setUniqueIdentifier($languageId ?? md5('language-' . $localeCode));
        $language->setLocale($this->createLocaleEntity($localeCode));

        return $language;
    }

    protected function createLocaleEntity(string $localeCode): LocaleEntity
    {
        $locale = new LocaleEntity();
        $locale->setUniqueIdentifier(md5('locale-' . $localeCode));
        $locale->setCode($localeCode);

        return $locale;
    }
}
