<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\System\Snippet\Listener;

use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\TranslationProviderResolverInterface;
use Shopware\Core\System\Snippet\Extension\StorefrontSnippetsExtension;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(StorefrontSnippetsExtension::NAME . '.pre')]
class LoadTranslationsListener
{
    public const string TRANSLATION_DOMAIN = 'messages';

    private static bool $skip = false;

    function __construct(
        private readonly TranslationProviderResolverInterface $providerResolver,
        #[Autowire(param: 'nlx_storefront_translation.respect_translation_files')]
        private readonly bool $respectTranslationFiles
    ) {
    }

    public function __invoke(StorefrontSnippetsExtension $extension): void
    {
        if (self::$skip) {
            return;
        }

        $extension->stopPropagation();

        // Force usage of translation files
        if ($this->respectTranslationFiles) {
            $this->respectTranslationFiles($extension);
        }

        $this->applyProviderTranslations($extension);
    }

    public static function skip(callable $callback): void
    {
        self::$skip = true;
        try {
            $callback();
        } finally {
            self::$skip = false;
        }
    }

    private function respectTranslationFiles(StorefrontSnippetsExtension $extension): void
    {
        $salesChannelId = $extension->salesChannelId;
        $catalog = $extension->catalog;

        foreach ($extension->snippets as $key => $value) {
            if ($catalog->has($key, $salesChannelId)) {
                $extension->result[$key] = $catalog->get($key, $salesChannelId);
            } elseif (!$catalog->has($key, self::TRANSLATION_DOMAIN)) {
                $extension->result[$key] = $value;
            }
        }
    }

    private function applyProviderTranslations(StorefrontSnippetsExtension $extension): void
    {
        $provider = match (true) {
            $this->providerResolver->hasProvider($extension->salesChannelId) =>
            $this->providerResolver->getProvider($extension->salesChannelId),
            $this->providerResolver->hasDefaultProvider() => $this->providerResolver->getDefaultProvider(),
            default => null
        };

        if ($provider == null) {
            return;
        }

        $locales = array_unique(array_filter([
            $extension->locale,
            $extension->fallbackLocale
        ]));

        $translationBag = $provider->read([self::TRANSLATION_DOMAIN], $locales);

        $catalogue = $translationBag->getCatalogue($extension->locale);
        $fallbackCatalogue = is_string($extension->fallbackLocale) ?
            $translationBag->getCatalogue($extension->fallbackLocale) : null;

        foreach ($extension->result as $key => $value) {
            if ($catalogue->has($key, self::TRANSLATION_DOMAIN)) {
                $extension->result[$key] = $catalogue->get($key, self::TRANSLATION_DOMAIN);
            }
            if ($fallbackCatalogue?->has($key, self::TRANSLATION_DOMAIN)) {
                $extension->result[$key] = $fallbackCatalogue->get($key, self::TRANSLATION_DOMAIN);
            }
        }
    }
}
