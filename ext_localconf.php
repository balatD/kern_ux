<?php

declare(strict_types=1);

defined('TYPO3') or die();

(static function (): void {
    // Configuration/Fluid/Namespaces.php only exists as an API from TYPO3 14.1
    // (#108524). On v13 the same registration has to go through TYPO3_CONF_VARS.
    if ((new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() < 14) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['k'][]
            = 'BalatD\\KernUx\\Components\\ComponentCollection';
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['kux'][]
            = 'BalatD\\KernUx\\ViewHelpers';
    }

    // ext:form. On TYPO3 14 Configuration/Form/KernUx/config.yaml is a "form set"
    // and is discovered automatically. Form sets do not exist on 13, so there the same
    // file is registered through yamlConfigurations - which 14.2 deprecated, hence the
    // version guard: registering it on 14 as well would work but emit a deprecation
    // for no benefit.
    //
    // Both keys are needed on 13: plugin.* drives the frontend, module.* the form
    // editor's preview. The odd numeric key is the convention the core docs use to keep
    // unrelated extensions from overwriting each other's entry.
    if ((new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() < 14) {
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(
            'plugin.tx_form.settings.yamlConfigurations.1756140000 = EXT:kern_ux/Configuration/Form/KernUx/config.yaml' . LF
            . 'module.tx_form.settings.yamlConfigurations.1756140000 = EXT:kern_ux/Configuration/Form/KernUx/config.yaml',
        );
    }

    // Registered even when rte_ckeditor is absent: the preset is inert without it,
    // and guarding the registration would make the extension order-dependent.
    $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['kern_ux']
        = 'EXT:kern_ux/Configuration/RTE/KernUx.yaml';
})();
