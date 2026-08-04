<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\System\Snippet\Exception;

/**
 * Thrown when a default (global) translation provider is required but none is configured.
 */
final class MissingDefaultProviderException extends TranslationProviderException
{
    public function __construct()
    {
        parent::__construct('No default translation provider configured.');
    }
}
