<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\System\Snippet;

interface SalesChannelTranslationRefresherInterface
{
    public function refresh(string ...$salesChannelIds): void;
}
