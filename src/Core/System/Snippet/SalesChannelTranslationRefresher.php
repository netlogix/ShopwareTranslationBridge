<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\System\Snippet;

use Netlogix\ShopwareTranslationBridge\Core\Framework\Adapter\Translator\TranslationCacheInvalidationInterface;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class SalesChannelTranslationRefresher implements SalesChannelTranslationRefresherInterface
{
    function __construct(
        private TranslationCacheInvalidationInterface $translationCacheInvalidation,
        private EntityRepository $salesChannelDomainRepository,
        #[Autowire(service: Translator::class)]
        private AbstractTranslator $translator,
    ) {
    }

    public function refresh(string ...$salesChannelIds): void
    {
        foreach ($salesChannelIds as $salesChannelId) {
            $this->translationCacheInvalidation->invalidateBySalesChannel($salesChannelId, true);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('salesChannelId', $salesChannelIds));
        $criteria->addAssociation('language.locale');
        try {
            $this->salesChannelDomainRepository
                ->search($criteria, Context::createCLIContext())
                ->map($this->warmUpTranslation(...));
        } finally {
            $this->translator->resetInjection();
        }
    }

    private function warmUpTranslation(SalesChannelDomainEntity $domain): void
    {
        $this->translator->injectSettings(
            $domain->getSalesChannelId(),
            $domain->getLanguageId(),
            $domain->getLanguage()->getLocale()->getCode(),
            Context::createCLIContext()
        );
        $this->translator->getCatalogue();
    }
}
