<?php

declare(strict_types=1);

defined('TYPO3') or die();

/*
 * An optional icon per page, for the service navigation in the header.
 *
 * KERN's own header pattern shows those links with a leading symbol - sign language,
 * easy language, the language switch - and which symbol belongs to which page is
 * editorial knowledge, not something a template can infer from a title. So it is a
 * field, and an empty one simply renders the link without an icon.
 *
 * The list is restricted to the icons KERN actually ships and that make sense as a
 * service link. A free text field would let an editor type a class that does not exist,
 * and the result would be an empty box rather than an error.
 */
$GLOBALS['TCA']['pages']['columns']['tx_kernux_nav_icon'] = [
    'exclude' => true,
    'label' => 'LLL:EXT:kern_ux/Resources/Private/Language/locallang_be.xlf:page.navIcon',
    'description' => 'LLL:EXT:kern_ux/Resources/Private/Language/locallang_be.xlf:page.navIcon.description',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'items' => [
            ['label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.none', 'value' => ''],
            ['label' => 'sign-language', 'value' => 'sign-language'],
            ['label' => 'easy-language', 'value' => 'easy-language'],
            ['label' => 'language', 'value' => 'language'],
            ['label' => 'account-circle', 'value' => 'account-circle'],
            ['label' => 'help', 'value' => 'help'],
            ['label' => 'info', 'value' => 'info'],
            ['label' => 'mail', 'value' => 'mail'],
            ['label' => 'search', 'value' => 'search'],
            ['label' => 'calendar-today', 'value' => 'calendar-today'],
            ['label' => 'download', 'value' => 'download'],
            ['label' => 'home', 'value' => 'home'],
            ['label' => 'visibility', 'value' => 'visibility'],
        ],
        'default' => '',
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    'tx_kernux_nav_icon',
    '',
    'after:nav_title',
);
