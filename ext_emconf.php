<?php

/**
 * Kept in sync with composer.json by hand - TER and tailor still read this file,
 * even though TYPO3 deprecated it in 14.2 (#108345).
 */
$EM_CONF[$_EXTKEY] = [
    'title' => 'KERN UX-Standard',
    'description' => 'KERN UX-Standard for TYPO3: accessible Fluid components, Content Blocks and ext:form templates for German public sector websites. Independent community integration, not an official KERN project.',
    'category' => 'templates',
    'author' => 'Dragan Balatinac',
    'state' => 'alpha',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '13.4.19-14.3.99',
            'content_blocks' => '1.6.0-2.4.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'form' => '13.4.19-14.3.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'BalatD\\KernUx\\' => 'Classes/',
        ],
    ],
];
