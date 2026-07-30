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

    public const string KEY_DEFAULT_PROVIDER = self::PLUGIN_CONFIG_PREFIX . '.defaultProvider';

    public const string KEY_RESPECT_TRANSLATION_FILES = self::PLUGIN_CONFIG_PREFIX . '.respectTranslationFiles';

    public function __construct(
        private SystemConfigService $systemConfigService,
    ) {
    }

    public function respectTranslationFiles(?string $salesChannelId = null): bool
    {
        return $this->systemConfigService->getBool(self::KEY_RESPECT_TRANSLATION_FILES, $salesChannelId);
    }

    public function getDefaultProviderName(): string
    {
        return $this->systemConfigService->getString(self::KEY_DEFAULT_PROVIDER);
    }

    public function getSalesChannelProviderOverride(string $salesChannelId): ?string
    {
        return $this->systemConfigService->getString(self::KEY_DEFAULT_PROVIDER, $salesChannelId);
    }
}
