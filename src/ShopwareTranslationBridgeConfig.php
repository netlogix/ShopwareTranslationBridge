<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge;

final class ShopwareTranslationBridgeConfig
{
    public const string DOMAIN = 'ShopwareTranslationBridge.config';

    public const string KEY_DEFAULT_PROVIDER = self::DOMAIN . '.defaultProvider';

    public const string KEY_RESPECT_TRANSLATION_FILES = self::DOMAIN . '.respectTranslationFiles';

    private function __construct()
    {
    }
}
