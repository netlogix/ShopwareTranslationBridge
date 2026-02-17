<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Tests\Unit\Core\System;

use Netlogix\ShopwareTranslationBridge\Core\System\RelevantLocaleResolver;
use Netlogix\ShopwareTranslationBridge\Tests\Support\CreatesLanguageEntitiesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[CoversClass(RelevantLocaleResolver::class)]
final class RelevantLocaleResolverTest extends TestCase
{
    use CreatesLanguageEntitiesTrait;

    public function testGetAllReturnsLocalesFromAllSalesChannels(): void
    {
        $salesChannelRepository = $this->createMock(EntityRepository::class);
        $salesChannelRepository
            ->expects(self::once())
            ->method('search')
            ->willReturn($this->createSalesChannelSearchResult(
                $this->createSalesChannel('sc-1', ['de-DE', 'en-GB']),
                $this->createSalesChannel('sc-2', ['fr-FR'])
            ));

        $resolver = new RelevantLocaleResolver($salesChannelRepository);

        static::assertSame(['de-DE', 'en-GB', 'fr-FR'], array_values($resolver->getAll()));
    }

    public function testGetForSalesChannelReturnsLocalesForGivenSalesChannel(): void
    {
        $salesChannelRepository = $this->createMock(EntityRepository::class);
        $salesChannelRepository
            ->expects(self::once())
            ->method('search')
            ->willReturn($this->createSalesChannelSearchResult($this->createSalesChannel('sc-1', ['de-DE', 'en-GB'])));

        $resolver = new RelevantLocaleResolver($salesChannelRepository);

        static::assertSame(['de-DE', 'en-GB'], array_values($resolver->getForSalesChannel('sc-1')));
    }

    private function createSalesChannel(string $id, array $localeCodes): SalesChannelEntity
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setUniqueIdentifier($id);
        $salesChannel->setLanguages($this->createLanguageCollection($localeCodes));

        return $salesChannel;
    }

    private function createSalesChannelSearchResult(SalesChannelEntity ...$salesChannels): EntitySearchResult
    {
        return new EntitySearchResult(
            'sales_channel',
            count($salesChannels),
            new SalesChannelCollection($salesChannels),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
