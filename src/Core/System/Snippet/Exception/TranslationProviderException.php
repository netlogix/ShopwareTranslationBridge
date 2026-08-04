<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Core\System\Snippet\Exception;

use RuntimeException;

/**
 * Base type for all translation provider resolution failures.
 *
 * Catch this to handle any resolver error regardless of scope.
 */
abstract class TranslationProviderException extends RuntimeException
{
}
