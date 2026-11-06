<?php

declare(strict_types=1);

defined('TYPO3') or die();

/*
 * Whether a document is accessible, as a fact about the file rather than about a link.
 *
 * BITV 2.0 covers documents a public body publishes, not just its pages, and a PDF that
 * was exported from a layout program is almost never accessible. Nothing in TYPO3 knows
 * this - it cannot be derived from the file - so it is editorial knowledge, and the only
 * place it can live is the file's own metadata. A download link then says so, which is
 * what turns an inaccessible document from a dead end into something a visitor can plan
 * around: they know to ask for an alternative before opening it.
 *
 * On the metadata record rather than the file reference, because it is a property of the
 * document. The same PDF linked from four pages is accessible in all four or in none.
 *
 * Default 0 - "not known to be accessible" - is deliberate. A default of 1 would mean
 * every file uploaded before this field existed silently claims conformance.
 */
$GLOBALS['TCA']['sys_file_metadata']['columns']['tx_kernux_is_accessible'] = [
    'exclude' => true,
    'label' => 'LLL:EXT:kern_ux/Resources/Private/Language/locallang_be.xlf:file.isAccessible',
    'description' => 'LLL:EXT:kern_ux/Resources/Private/Language/locallang_be.xlf:file.isAccessible.description',
    'config' => [
        'type' => 'check',
        'renderType' => 'checkboxToggle',
        'default' => 0,
    ],
];

/*
 * Next to the alternative text, which is where an editor is already thinking about
 * accessibility. A field in a tab of its own is a field nobody fills in - the same
 * reasoning that keeps captions and transcript at the top level of the media block.
 */
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'sys_file_metadata',
    'tx_kernux_is_accessible',
    '',
    'after:alternative',
);
