<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Core\System\Snippet\Listener;

use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\TranslationProviderResolverInterface;
use Netlogix\ShopwareTranslationBridge\ShopwareTranslationBridgeConfig;
use Shopware\Core\System\Snippet\Extension\StorefrontSnippetsExtension;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(StorefrontSnippetsExtension::NAME . '.pre')]
class LoadTranslationsListener
{
    public const string TRANSLATION_DOMAIN = 'messages';

    private static bool $skip = false;

    function __construct(
        private readonly TranslationProviderResolverInterface $providerResolver,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function __invoke(StorefrontSnippetsExtension $extension): void
    {
        if (self::$skip) {
            return;
        }

        $respectTranslationFiles = $this->systemConfigService->getBool(
            ShopwareTranslationBridgeConfig::KEY_RESPECT_TRANSLATION_FILES,
            $extension->salesChannelId
        );

        // Force usage of translation files
        if ($respectTranslationFiles) {
            $this->respectTranslationFiles($extension);
        }

        if ($this->applyProviderTranslations($extension)) {
            $extension->stopPropagation();
        }
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

    private function applyProviderTranslations(StorefrontSnippetsExtension $extension): bool
    {
        if (!$this->providerResolver->hasProvider($extension->salesChannelId)) {
            return false;
        }

        $provider = $this->providerResolver->getProvider($extension->salesChannelId);

        $locales = array_unique(array_filter([
            $extension->locale,
            $extension->fallbackLocale
        ]));

        $translationBag = $provider->read([self::TRANSLATION_DOMAIN], $locales);

        $catalogue = $translationBag->getCatalogue($extension->locale);
        $fallbackCatalogue = is_string($extension->fallbackLocale)
            ? $translationBag->getCatalogue($extension->fallbackLocale)
            : null;

        foreach ($extension->result as $key => $value) {
            if ($catalogue->has($key, self::TRANSLATION_DOMAIN)) {
                $extension->result[$key] = $catalogue->get($key, self::TRANSLATION_DOMAIN);
            }
            if ($fallbackCatalogue?->has($key, self::TRANSLATION_DOMAIN)) {
                $extension->result[$key] = $fallbackCatalogue->get($key, self::TRANSLATION_DOMAIN);
            }
        }

        return true;
    }
}
