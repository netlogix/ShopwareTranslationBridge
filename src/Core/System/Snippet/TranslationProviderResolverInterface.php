<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\System\Snippet;

use Symfony\Component\Translation\Provider\ProviderInterface;

interface TranslationProviderResolverInterface
{
    public function hasDefaultProvider(): bool;

    public function getDefaultProvider(): ProviderInterface;

    public function hasSalesChannelProvider(string $salesChannelId): bool;

    public function getSalesChannelProvider(string $salesChannelId): ProviderInterface;

    public function hasProvider(string $salesChannelId): bool;

    public function getProvider(string $salesChannelId): ProviderInterface;
}
