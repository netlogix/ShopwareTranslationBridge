<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Core\System;

interface RelevantLocaleResolverInterface
{
    public function getAll(): array;

    public function getForSalesChannel(string $salesChannelId): array;
}