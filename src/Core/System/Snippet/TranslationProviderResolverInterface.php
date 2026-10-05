<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\System\Snippet;

use Symfony\Component\Translation\Provider\ProviderInterface;

interface TranslationProviderResolverInterface
{
    public function hasProvider(?string $salesChannelId = null): bool;

    public function getProvider(?string $salesChannelId = null): ProviderInterface;
}
