<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\System\Snippet\Exception;

/**
 * Thrown when no translation provider can be resolved for a specific sales channel.
 */
final class MissingSalesChannelProviderException extends \RuntimeException implements TranslationProviderExceptionInterface
{
    public function __construct(public readonly string $salesChannelId, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            \sprintf('No translation provider configured for salesChannel "%s".', $salesChannelId),
            $code,
            $previous
        );
    }
}
