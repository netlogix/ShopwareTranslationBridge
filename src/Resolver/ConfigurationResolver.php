<?php

declare(strict_types=1);

/*
 * Created by netlogix GmbH & Co. KG
 *
 * @copyright netlogix GmbH & Co. KG
 */

namespace Netlogix\ShopwareTranslationBridge\Resolver;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigurationResolver
{
    public const string PLUGIN_CONFIG_PREFIX = 'ShopwareTranslationBridge.config';

    public const string KEY_DEFAULT_PROVIDER = self::PLUGIN_CONFIG_PREFIX . '.defaultProvider';

    public const string KEY_RESPECT_TRANSLATION_FILES = self::PLUGIN_CONFIG_PREFIX . '.respectTranslationFiles';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function respectTranslationFiles(?string $salesChannelId = null): bool
    {
        return $this->systemConfigService->getBool(
            self::KEY_RESPECT_TRANSLATION_FILES,
            $salesChannelId
        );
    }

    public function getDefaultProviderName(): string
    {
        return $this->systemConfigService->getString(
            self::KEY_DEFAULT_PROVIDER
        );
    }

    /**
     * Returns the provider name only if it was explicitly configured for this sales channel
     * (i.e. not inherited from the global default). Returns null if the sales channel has no
     * override of its own, even if a global default provider is configured.
     */
    public function getSalesChannelProviderOverride(string $salesChannelId): ?string
    {
        $config = $this->systemConfigService->getDomain(
            self::PLUGIN_CONFIG_PREFIX,
            $salesChannelId,
            false
        );

        $providerName = $config[self::KEY_DEFAULT_PROVIDER] ?? null;

        return is_string($providerName) && $providerName !== '' ? $providerName : null;
    }
}
