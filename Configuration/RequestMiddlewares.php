<?php

declare(strict_types=1);

return [
    'frontend' => [
        'balatd/kern-ux/styleguide' => [
            'target' => \BalatD\KernUx\Middleware\StyleguideMiddleware::class,
            // Needs the resolved site to read its settings, and has to answer before
            // page routing turns the unknown path into a 404. Backend user
            // authentication has to have run too: outside Development the gallery is
            // only served to a logged-in backend user, and that is the middleware that
            // puts one on the request.
            'after' => [
                'typo3/cms-frontend/site',
                'typo3/cms-frontend/backend-user-authentication',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
