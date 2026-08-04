<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\System\Snippet\Exception;

/**
 * Thrown when a provider name is configured but no matching provider is registered in the
 * translation provider collection (e.g. a typo in the config or a removed provider bundle).
 */
final class UnknownProviderException extends TranslationProviderException
{
    public function __construct(
        private readonly string $providerName,
        private readonly ?string $salesChannelId = null
    ) {
        parent::__construct($salesChannelId === null
            ? \sprintf('Configured translation provider "%s" is not registered.', $providerName)
            : \sprintf(
                'Configured translation provider "%s" for salesChannel "%s" is not registered.',
                $providerName,
                $salesChannelId
            ));
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }
}
