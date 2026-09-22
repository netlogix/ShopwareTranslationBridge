<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\MessageHandler;

use Netlogix\ShopwareTranslationBridge\Core\System\Snippet\SalesChannelTranslationRefresherInterface;
use Netlogix\ShopwareTranslationBridge\Message\TranslationUpdateMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class TranslationUpdateHandler
{
    public function __construct(
        private SalesChannelTranslationRefresherInterface $salesChannelTranslationRefresher
    ) {
    }

    public function __invoke(TranslationUpdateMessage $message): void
    {
        $this->salesChannelTranslationRefresher->refresh(...$message->salesChannelIds);
    }
}
