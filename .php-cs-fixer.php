<?php

declare(strict_types=1);

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->getFinder()
    ->in(__DIR__ . '/Classes')
    ->in(__DIR__ . '/Configuration')
    ->in(__DIR__ . '/Tests')
    ->in(__DIR__ . '/Build/phpunit')
    ->append([__FILE__, __DIR__ . '/ext_localconf.php', __DIR__ . '/ext_emconf.php']);

return $config;
