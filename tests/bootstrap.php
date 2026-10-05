<?php

declare(strict_types = 1);

$pluginAutoload = __DIR__ . '/../vendor/autoload.php';

if (is_file($pluginAutoload)) {
    require $pluginAutoload;

    return;
}

// In CI the plugin lives in custom/plugins/ of a Shopware installation without its own vendor/,
// and the root autoloader does not include autoload-dev of dependencies.
$classLoader = require __DIR__ . '/../../../../vendor/autoload.php';
$classLoader->addPsr4('Netlogix\\ShopwareTranslationBridge\\', __DIR__ . '/../src');
$classLoader->addPsr4('Netlogix\\ShopwareTranslationBridge\\Tests\\', __DIR__);
