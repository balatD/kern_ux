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
final class FluidSourceRenderer
{
    /**
     * Distinguishes this instance's temporary files from any other instance's.
     *
     * The filename used to be the content hash alone, which collides across
     * concurrent renders of the *same* snippet: one request writes the file, a second
     * writes it, the first renders and deletes it, and the second renders a file that
     * is no longer there. The styleguide route made that reachable rather than
     * theoretical - it renders every gallery snippet on every request and is explicitly
     * uncached.
     *
     * A token rather than a random name per call, because the hash is what lets Fluid
     * reuse a parsed template for a snippet that appears twice in one render. This class
     * is a shared service, so the token is per request, which is the scope that matters.
     */
    private readonly string $token;

    public function __construct(private readonly ViewFactoryInterface $viewFactory)
    {
        $this->token = bin2hex(random_bytes(5));
    }

    /**
     * @param array<string, mixed> $variables Assigned to the view, the way a page
     *                                        template hands menu data to a component.
     */
    public function render(string $source, array $variables = []): string
    {
        $directory = Environment::getVarPath() . '/transient/kern-ux-render';
        GeneralUtility::mkdir_deep($directory);

        // Unique per source so a parsed-template cache entry is never reused across
        // differing snippets within one request, and unique per instance so two
        // requests rendering the same snippet do not share - and delete - one file.
        $file = $directory . '/' . hash('xxh128', $source) . '-' . $this->token . '.html';

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
