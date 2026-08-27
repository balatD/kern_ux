<?php

declare(strict_types=1);

return [
    'frontend' => [
        'balatd/kern-ux/styleguide' => [
            'target' => \BalatD\KernUx\Middleware\StyleguideMiddleware::class,
            // Needs the resolved site to read its settings, and has to answer before
            // page routing turns the unknown path into a 404.
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
