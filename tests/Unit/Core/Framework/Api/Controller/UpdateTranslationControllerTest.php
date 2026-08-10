<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Tests\Unit\Core\Framework\Api\Controller;

use Netlogix\ShopwareTranslationBridge\Core\Framework\Api\Controller\UpdateTranslationController;
use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\TranslationProviderResolverInterface;
use Netlogix\ShopwareTranslationBridge\Message\TranslationUpdateMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(UpdateTranslationController::class)]
final class UpdateTranslationControllerTest extends TestCase
{
    private const string SALES_CHANNEL_ID_1 = '2b919afec10730f413cb5682bbed09fd';
    private const string SALES_CHANNEL_ID_2 = '3b919afec10730f413cb5682bbed09fd';
    private const string SALES_CHANNEL_ID_3 = '4b919afec10730f413cb5682bbed09fd';

    public function testInvokeReturnsServiceUnavailableWhenNoProviderCanBeResolved(): void
    {
        $salesChannelRepository = $this->createMock(EntityRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $providerResolver = $this->createMock(TranslationProviderResolverInterface::class);

        $salesChannelRepository
            ->expects(self::once())
            ->method('searchIds')
            ->willReturn($this->createIdSearchResult([self::SALES_CHANNEL_ID_1]));

        $providerResolver
            ->expects(self::once())
            ->method('hasProvider')
            ->with(self::SALES_CHANNEL_ID_1)
            ->willReturn(false);

        $messageBus->expects(self::never())->method('dispatch');

        $controller = new UpdateTranslationController($salesChannelRepository, $messageBus, $providerResolver);
        $response = $controller->__invoke();

        static::assertSame(503, $response->getStatusCode());
        static::assertStringContainsString('errorMissingTranslationProvider', (string) $response->getContent());
    }

    public function testInvokeDispatchesBatchedMessages(): void
    {
        $salesChannelRepository = $this->createMock(EntityRepository::class);
        $messageBus = $this->createMock(MessageBusInterface::class);
        $providerResolver = $this->createMock(TranslationProviderResolverInterface::class);

        $salesChannelRepository
            ->expects(self::once())
            ->method('searchIds')
            ->willReturn($this->createIdSearchResult([
                self::SALES_CHANNEL_ID_1,
                self::SALES_CHANNEL_ID_2,
                self::SALES_CHANNEL_ID_3
            ]));

        $providerResolver->expects(self::exactly(3))->method('hasProvider')->willReturn(true);

        $messageBus
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->with(static::callback(static function (object $message): bool {
                static $chunks = [
                    [self::SALES_CHANNEL_ID_1, self::SALES_CHANNEL_ID_2],
                    [self::SALES_CHANNEL_ID_3]
                ];

                if (!$message instanceof TranslationUpdateMessage) {
                    return false;
                }

                $expectedChunk = array_shift($chunks);
                if ($expectedChunk === null) {
                    return false;
                }

                return $message->salesChannelIds === $expectedChunk;
            }))
            ->willReturnCallback(static fn(object $message): Envelope => new Envelope($message));

        $controller = new UpdateTranslationController($salesChannelRepository, $messageBus, $providerResolver, 2);
        $response = $controller->__invoke();

        static::assertSame(200, $response->getStatusCode());
        static::assertStringContainsString('"success":true', (string) $response->getContent());
    }

    /**
     * @param list<string> $ids
     */
    private function createIdSearchResult(array $ids): IdSearchResult
    {
        $idSearchResult = $this->createStub(IdSearchResult::class);
        $idSearchResult->method('getIds')->willReturn($ids);

        return $idSearchResult;
    }
}
