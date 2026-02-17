<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Tests\Unit\Core\System\Snippet;

use Netlogix\ShopwareTranslationBridge\Core\Framework\Adapter\Translator\TranslationCacheInvalidationInterface;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\SalesChannelTranslationRefresher;
use Netlogix\ShopwareTranslationBridge\Tests\Support\CreatesLanguageEntitiesTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\Translation\MessageCatalogue;

#[CoversClass(SalesChannelTranslationRefresher::class)]
final class SalesChannelTranslationRefresherTest extends TestCase
{
    use CreatesLanguageEntitiesTrait;

    private const string SALES_CHANNEL_ID_1 = '2b919afec10730f413cb5682bbed09fd';
    private const string SALES_CHANNEL_ID_2 = '3b919afec10730f413cb5682bbed09fd';
    private const string LANGUAGE_ID_1 = '4b919afec10730f413cb5682bbed09fd';
    private const string LANGUAGE_ID_2 = '5b919afec10730f413cb5682bbed09fd';

    public function testRefreshInvalidatesCachesWarmsUpAndResetsInjection(): void
    {
        $cacheInvalidation = $this->createMock(TranslationCacheInvalidationInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        $translator = $this->createMock(AbstractTranslator::class);

        $cacheInvalidation
            ->expects(static::exactly(2))
            ->method('invalidateBySalesChannel')
            ->willReturnCallback(static function (string $salesChannelId, bool $force): void {
                static $calls = 0;
                ++$calls;

                static::assertTrue($force);
                static::assertContains($salesChannelId, [self::SALES_CHANNEL_ID_1, self::SALES_CHANNEL_ID_2]);
                static::assertLessThanOrEqual(2, $calls);
            });

        $searchResult = $this->createDomainSearchResult(
            $this->createDomain(self::SALES_CHANNEL_ID_1, self::LANGUAGE_ID_1, 'de-DE'),
            $this->createDomain(self::SALES_CHANNEL_ID_2, self::LANGUAGE_ID_2, 'en-GB')
        );

        $repository
            ->expects(static::once())
            ->method('search')
            ->with(static::callback(static function (Criteria $criteria): bool {
                $filters = $criteria->getFilters();
                static::assertCount(1, $filters);
                static::assertSame('salesChannelId', $filters[0]->getField());
                static::assertSame([self::SALES_CHANNEL_ID_1, self::SALES_CHANNEL_ID_2], $filters[0]->getValue());
                $associations = $criteria->getAssociations();
                static::assertArrayHasKey('language', $associations);
                static::assertArrayHasKey('locale', $associations['language']->getAssociations());

                return true;
            }), static::isInstanceOf(Context::class))
            ->willReturn($searchResult);

        $translator
            ->expects(static::exactly(2))
            ->method('injectSettings')
            ->willReturnCallback(static function (
                string $salesChannelId,
                string $languageId,
                string $localeCode,
                Context $context
            ): void {
                static $calls = [];
                $calls[] = [$salesChannelId, $languageId, $localeCode, $context];

                static::assertContains([$salesChannelId, $languageId, $localeCode], [
                    [self::SALES_CHANNEL_ID_1, self::LANGUAGE_ID_1, 'de-DE'],
                    [self::SALES_CHANNEL_ID_2, self::LANGUAGE_ID_2, 'en-GB']
                ]);
                static::assertInstanceOf(Context::class, $context);
            });
        $translator->expects(static::exactly(2))->method('getCatalogue')->willReturn(new MessageCatalogue('de-DE'));
        $translator->expects(static::once())->method('resetInjection');

        $refresher = new SalesChannelTranslationRefresher($cacheInvalidation, $repository, $translator);
        $refresher->refresh(self::SALES_CHANNEL_ID_1, self::SALES_CHANNEL_ID_2);
    }

    public function testRefreshAlwaysResetsInjectionOnFailure(): void
    {
        $cacheInvalidation = $this->createMock(TranslationCacheInvalidationInterface::class);
        $repository = $this->createMock(EntityRepository::class);
        $translator = $this->createMock(AbstractTranslator::class);

        $cacheInvalidation
            ->expects(static::once())
            ->method('invalidateBySalesChannel')
            ->with(self::SALES_CHANNEL_ID_1, true);
        $repository
            ->expects(static::once())
            ->method('search')
            ->willThrowException(new RuntimeException('search failed'));
        $translator->expects(static::once())->method('resetInjection');

        $refresher = new SalesChannelTranslationRefresher($cacheInvalidation, $repository, $translator);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('search failed');

        $refresher->refresh(self::SALES_CHANNEL_ID_1);
    }

    private function createDomain(
        string $salesChannelId,
        string $languageId,
        string $localeCode
    ): SalesChannelDomainEntity {
        $domain = new SalesChannelDomainEntity();
        $domain->setUniqueIdentifier(md5($salesChannelId . '-' . $languageId . '-' . $localeCode));
        $domain->setSalesChannelId($salesChannelId);
        $domain->setLanguageId($languageId);
        $domain->setLanguage($this->createLanguageEntity($localeCode, $languageId));

        return $domain;
    }

    private function createDomainSearchResult(SalesChannelDomainEntity ...$domains): EntitySearchResult
    {
        return new EntitySearchResult(
            'sales_channel_domain',
            count($domains),
            new SalesChannelDomainCollection($domains),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
