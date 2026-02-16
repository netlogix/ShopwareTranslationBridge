<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Message;

use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
readonly class TranslationUpdateMessage
{
    /**
     * @param string[] $salesChannelIds
     */
    public array $salesChannelIds;

    function __construct(
        string ...$salesChannelIds
    ) {
        foreach ($salesChannelIds as $salesChannelId) {
            if (!Uuid::isValid($salesChannelId)) {
                throw new \InvalidArgumentException(
                    sprintf('SalesChannelId "%s" is not a valid UUID', $salesChannelId)
                );
            }
        }

        $this->salesChannelIds = $salesChannelIds;
    }
}
