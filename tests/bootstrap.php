<?php

declare(strict_types = 1);

require __DIR__ . '/../vendor/autoload.php';

$supportFiles = glob(__DIR__ . '/Support/*.php');
foreach ($supportFiles === false ? [] : $supportFiles as $supportFile) {
    require_once $supportFile;
}
