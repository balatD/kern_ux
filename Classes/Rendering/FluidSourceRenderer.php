<?php

declare(strict_types=1);

namespace BalatD\KernUx\Rendering;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Renders a Fluid source string through TYPO3's ViewFactory.
 *
 * Development and test helper. It exists because component markup has to be
 * comparable between TYPO3 majors and assertable in tests, and TYPO3's ViewFactory
 * only accepts template *files*. Going through the ViewFactory rather than a bare
 * Fluid view is the point: global namespaces and TYPO3's ViewHelper overrides then
 * behave exactly as they do on a rendered page.
 *
 * Writes to the transient var path, which is never web-accessible.
 */
final readonly class FluidSourceRenderer
{
    public function __construct(private ViewFactoryInterface $viewFactory) {}

    /**
     * @param array<string, mixed> $variables Assigned to the view, the way a page
     *                                        template hands menu data to a component.
     */
    public function render(string $source, array $variables = []): string
    {
        $directory = Environment::getVarPath() . '/transient/kern-ux-render';
        GeneralUtility::mkdir_deep($directory);

        // Unique per source so a parsed-template cache entry is never reused
        // across differing snippets within one request.
        $file = $directory . '/' . hash('xxh128', $source) . '.html';

        try {
            GeneralUtility::writeFile($file, $source, true);

            $view = $this->viewFactory->create(new ViewFactoryData(
                templateRootPaths: [$directory],
                templatePathAndFilename: $file,
            ));
            $view->assignMultiple($variables);

            return $view->render();
        } finally {
            @unlink($file);
        }
    }
}
