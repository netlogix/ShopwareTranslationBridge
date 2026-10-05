<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\System\Snippet\Exception;

/**
 * Thrown when a default (global) translation provider is required but none is configured.
 */
final class MissingDefaultProviderException extends \RuntimeException implements TranslationProviderExceptionInterface
{
    public function __construct(int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(
            \sprintf('No default translation provider configured.'),
            $code,
            $previous
        );
    }
}
