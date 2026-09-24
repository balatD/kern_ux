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
    'state' => 'beta',
    'version' => '1.0.0',
    // Every range below is the outer hull of the matching composer.json constraint, and
    // ExtensionManifestTest derives it from there rather than reading it here. A dash
    // range cannot express a disjunction: composer requires content_blocks
    // "^1.6 || ^2.4", but any single range covering both also admits the 2.0-2.3 gap
    // between them. The hull is the closest honest translation; what is actually tested
    // is stated by the README badges and the CI matrix.
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.99.99',
            'typo3' => '13.4.0-14.99.99',
            'content_blocks' => '1.6.0-2.99.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'form' => '13.4.0-14.99.99',
            'rte_ckeditor' => '13.4.0-14.99.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'BalatD\\KernUx\\' => 'Classes/',
        ],
    ],
];
