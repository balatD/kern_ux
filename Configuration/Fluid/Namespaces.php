<?php

declare(strict_types=1);

/**
 * Global Fluid namespaces (TYPO3 14.1+, #108524).
 *
 * Registered globally on purpose: Fluid 5 no longer inherits namespaces into
 * partials, layouts and component templates, and this extension is almost entirely
 * templates. Declaring xmlns in every file would be boilerplate that silently
 * renders nothing when forgotten.
 *
 * Two namespaces because a component collection is resolved through its own class
 * and cannot also carry ViewHelpers:
 *   k   - the KERN component collection
 *   kux - this extension's ViewHelpers
 *
 * ext_localconf.php mirrors both for TYPO3 13, where this file is not read.
 */
return [
    'k' => ['BalatD\\KernUx\\Components\\ComponentCollection'],
    'kux' => ['BalatD\\KernUx\\ViewHelpers'],
];
