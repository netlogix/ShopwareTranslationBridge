<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\System\Snippet\Exception;

/**
 * Thrown when a provider name is configured but no matching provider is registered in the
 * translation provider collection (e.g. a typo in the config or a removed provider bundle).
 */
final class UnknownProviderException extends \RuntimeException implements TranslationProviderExceptionInterface
{

    public function __construct(
        public readonly string $providerName,
        public readonly ?string $salesChannelId = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $salesChannelId === null
                ? \sprintf('Configured translation provider "%s" is not registered.', $providerName)
                : \sprintf(
                'Configured translation provider "%s" for salesChannel "%s" is not registered.',
                $providerName,
                $salesChannelId
            ),
            $code,
            $previous
        );
    }
}
