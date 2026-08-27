<?php

declare(strict_types=1);

defined('TYPO3') or die();

// Own group in the "new content element" wizard so KERN blocks are not scattered
// through the core groups. Registered here rather than in page TSconfig: Content
// Blocks moved group registration to the TCA API in its 1.0 restructure.
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTcaSelectItemGroup(
    'tt_content',
    'CType',
    'kern',
    'LLL:EXT:kern_ux/Resources/Private/Language/locallang_be.xlf:group.kern',
    'after:default',
);

// Content blocks carry a default vertical rhythm (see Molecule/Section), and core's
// spacing fields offer no way to close it again: their first option means "leave it to
// the CSS", not "no gap". Without this an editor could only ever add space, never take
// it away - so two blocks that belong tightly together could not be put together.
foreach (['space_before_class', 'space_after_class'] as $kernUxSpacingField) {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTcaSelectItem(
        'tt_content',
        $kernUxSpacingField,
        [
            'label' => 'LLL:EXT:kern_ux/Resources/Private/Language/locallang_be.xlf:spacing.none',
            'value' => 'none',
        ],
        '',
        'after',
    );
}
