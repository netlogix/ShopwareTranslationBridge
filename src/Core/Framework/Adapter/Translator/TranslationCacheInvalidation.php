<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Core\Framework\Adapter\Translator;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Feature;

readonly class TranslationCacheInvalidation implements TranslationCacheInvalidationInterface
{
    public function __construct(
        private CacheInvalidator $cacheInvalidator
    ) {
    }

    public function invalidate(bool $force = false): void
    {
        $this->cacheInvalidator->invalidate([Translator::ALL_CACHE_TAG], $force);
    }

    public function invalidateBySnippetSet(string $snippetSetId, bool $force = false): void
    {
        $cacheTag = match (Feature::isActive('cache_rework')) {
            true => Translator::tag($snippetSetId),
            false => 'translation.catalog.' . $snippetSetId
        };

        $this->cacheInvalidator->invalidate([$cacheTag], $force);
    }

    public function invalidateBySalesChannel(string $salesChannelId, bool $force = false): void
    {
        $cacheTag = match (Feature::isActive('cache_rework')) {
            true => Translator::tag($salesChannelId),
            false => 'translation.catalog.' . $salesChannelId
        };

        $this->cacheInvalidator->invalidate([$cacheTag], $force);
    }

    public function invalidateBySalesChannelAndSnippetSet(
        string $salesChannelId,
        string $snippetSetId,
        bool $force = false
    ): void {
        $key = \sprintf(
            'translation.catalog.%s.%s',
            $salesChannelId === '' ? 'DEFAULT' : $salesChannelId,
            $snippetSetId
        );

        $this->cacheInvalidator->invalidate([$key], $force);
    }
}
