<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\System\Snippet\Exception;

/**
 * Thrown when no translation provider can be resolved for a specific sales channel.
 */
final class MissingSalesChannelProviderException extends TranslationProviderException
{
    public function __construct(private readonly string $salesChannelId)
    {
        parent::__construct(
            \sprintf('No translation provider configured for salesChannel "%s".', $salesChannelId)
        );
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }
}
