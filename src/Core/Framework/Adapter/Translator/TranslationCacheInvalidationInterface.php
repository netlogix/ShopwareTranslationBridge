<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\Framework\Adapter\Translator;

interface TranslationCacheInvalidationInterface
{
    public function invalidate(bool $force = false): void;

    public function invalidateBySnippetSet(string $snippetSetId, bool $force = false): void;

    public function invalidateBySalesChannel(string $salesChannelId, bool $force = false): void;

    public function invalidateBySalesChannelAndSnippetSet(
        string $salesChannelId,
        string $snippetSetId,
        bool $force = false
    ): void;
}
