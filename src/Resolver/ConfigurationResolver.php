<?php

declare(strict_types=1);

/*
 * Created by netlogix GmbH & Co. KG
 *
 * @copyright netlogix GmbH & Co. KG
 */

namespace Netlogix\ShopwareTranslationBridge\Resolver;

use Shopware\Core\System\SystemConfig\SystemConfigService;

readonly class ConfigurationResolver
{
    public const string PLUGIN_CONFIG_PREFIX = 'ShopwareTranslationBridge.config';

    public const string KEY_DEFAULT_PROVIDER = self::PLUGIN_CONFIG_PREFIX . '.keyProvider';

    public const string KEY_RESPECT_TRANSLATION_FILES = self::PLUGIN_CONFIG_PREFIX . '.respectTranslationFiles';

    public function __construct(
        private SystemConfigService $systemConfigService,
    ) {
    }

    public function respectTranslationFiles(string $salesChannelId): bool
    {
        return $this->systemConfigService->getBool(self::KEY_RESPECT_TRANSLATION_FILES, $salesChannelId);
    }

    /**
     * Returns the effective provider name for the given scope.
     *
     * Relies on Shopware's SystemConfig inheritance: with a sales channel id the
     * channel-specific value is returned if set, otherwise the global value. Returns
     * null when nothing is configured so callers can safely no-op on a fresh install.
     */
    public function getProviderName(?string $salesChannelId = null): ?string
    {
        $providerName = $this->systemConfigService->getString(self::KEY_DEFAULT_PROVIDER, $salesChannelId);

        return $providerName !== '' ? $providerName : null;
    }
}
