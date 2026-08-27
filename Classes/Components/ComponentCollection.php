<?php

declare(strict_types=1);

namespace BalatD\KernUx\Components;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Component\AbstractComponentCollection;
use TYPO3Fluid\Fluid\View\TemplatePaths;

/**
 * The KERN component collection.
 *
 * Every piece of KERN markup lives here as a Fluid component, so Content Blocks,
 * ext:form partials and page templates all render the same HTML. Call them via
 * the collection's own namespace:
 *
 *     <html xmlns:k="http://typo3.org/ns/BalatD/KernUx/Components/ComponentCollection"
 *           data-namespace-typo3-fluid="true">
 *         <k:atom.button variant="primary">Weiter</k:atom.button>
 *     </html>
 *
 * `atom.button` resolves to Components/Atom/Button/Button.html - one folder per
 * component, so labels and component-scoped assets sit next to the template.
 *
 * Deliberately a class rather than TYPO3 14.1's Configuration/Fluid/ComponentCollections.php
 * (#108508): that file does not exist in v13, and this class behaves identically on
 * Fluid 4.6 and 5.3.
 */
final class ComponentCollection extends AbstractComponentCollection
{
    public function getTemplatePaths(): TemplatePaths
    {
        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths($this->resolveRootPaths());

        return $templatePaths;
    }

    /**
     * Fluid resolves template root paths in reverse order, so anything an integrator
     * appends here overrides our own component of the same name.
     *
     * @return list<string>
     */
    private function resolveRootPaths(): array
    {
        $paths = [
            GeneralUtility::getFileAbsFileName('EXT:kern_ux/Resources/Private/Components/'),
        ];

        foreach ($this->configuredRootPaths() as $path) {
            $absolute = GeneralUtility::getFileAbsFileName($path);
            if ($absolute !== '') {
                $paths[] = $absolute;
            }
        }

        return $paths;
    }

    /**
     * Integrator-supplied component paths, via
     * $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['kern_ux']['componentRootPaths'].
     *
     * Walked key by key because $GLOBALS carries no type information: a malformed
     * entry has to degrade to "no override" rather than take the site down.
     *
     * @return list<string>
     */
    private function configuredRootPaths(): array
    {
        $node = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        foreach (['EXTCONF', 'kern_ux', 'componentRootPaths'] as $key) {
            if (!is_array($node) || !isset($node[$key])) {
                return [];
            }
            $node = $node[$key];
        }

        if (!is_array($node)) {
            return [];
        }

        $paths = [];
        foreach ($node as $path) {
            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        return $paths;
    }
}
