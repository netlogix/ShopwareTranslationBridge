<?php

declare(strict_types=1);

namespace Netlogix\ShopwareTranslationBridge\Command;

use Netlogix\ShopwareTranslationBridge\Core\Framework\Adapter\Translator\TranslationCacheInvalidationInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('cache:translation:flush')]
class FlushTranslationCacheCommand extends Command
{
    public function __construct(
        private readonly TranslationCacheInvalidationInterface $translationCacheInvalidation
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->translationCacheInvalidation->invalidate(true);

        return Command::SUCCESS;
    }
}
