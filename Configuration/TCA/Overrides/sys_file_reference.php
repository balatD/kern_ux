<?php

declare(strict_types=1);

defined('TYPO3') or die();

/*
 * A long description for an image whose content cannot be carried by alt text.
 *
 * WCAG 1.1.1 asks for a text alternative that serves the same purpose. For a chart, a
 * process diagram or an organisation chart that is a paragraph, not a phrase, and alt
 * text is the wrong place for it: a screen reader reads alt in one breath, with no way
 * to pause, re-read or skip.
 *
 * On the reference, not the metadata: the same diagram means something different where
 * it is used, and the description has to describe it in that context.
 *
 * Deliberately NOT rendered as the HTML longdesc attribute, which is what the field is
 * called elsewhere. longdesc is obsolete, was removed from HTML5, and is surfaced by
 * almost nothing - a description put there reaches nobody. It is rendered as real
 * content instead, associated through aria-describedby; see Molecule/Figure.
 */
$GLOBALS['TCA']['sys_file_reference']['columns']['tx_kernux_longdesc'] = [
    'exclude' => true,
    'label' => 'LLL:EXT:kern_ux/Resources/Private/Language/locallang_be.xlf:file.longdesc',
    'description' => 'LLL:EXT:kern_ux/Resources/Private/Language/locallang_be.xlf:file.longdesc.description',
    'config' => [
        'type' => 'text',
        'rows' => 5,
        'cols' => 40,
    ],
];

/*
 * After the description field of the image palette, where core already groups the
 * text alternatives.
 */
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette(
    'sys_file_reference',
    'imageoverlayPalette',
    'tx_kernux_longdesc',
    'after:description',
);
