<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Core\System;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

readonly class RelevantLocaleResolver implements RelevantLocaleResolverInterface
{
    function __construct(
        private EntityRepository $salesChannelRepository
    ) {
    }

    public function getAll(): array
    {
        return $this->resolve();
    }

    public function getForSalesChannel(string $salesChannelId): array
    {
        return $this->resolve($salesChannelId);
    }

    private function resolve(?string $salesChannelId = null): array
    {
        $criteria = new Criteria($salesChannelId ? [$salesChannelId] : null);
        $criteria->addAssociation('languages.locale');
        $salesChannels = $this->salesChannelRepository->search($criteria, Context::createCLIContext());
        $languages = new LanguageCollection();
        foreach ($salesChannels as $salesChannel) {
            assert($salesChannel instanceof SalesChannelEntity);
            $salesChannelLanguages = $salesChannel->getLanguages();
            if ($salesChannelLanguages === null) {
                continue;
            }
            assert($salesChannelLanguages instanceof LanguageCollection);

            $languages->merge($salesChannelLanguages);
        }

        return array_filter($languages->map(fn(LanguageEntity $language) => $language->getLocale()?->getCode()));
    }
}
