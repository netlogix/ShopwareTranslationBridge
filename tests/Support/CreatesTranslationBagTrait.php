<?php

declare(strict_types = 1);

namespace Netlogix\ShopwareTranslationBridge\Tests\Support;

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\TranslatorBag;

trait CreatesTranslationBagTrait
{
    /**
     * @param array<string, string> $messages
     */
    protected function createBag(string $locale, array $messages, string $domain = 'messages'): TranslatorBag
    {
        $bag = new TranslatorBag();
        $bag->addCatalogue(new MessageCatalogue($locale, [$domain => $messages]));

        return $bag;
    }
}
