<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components;

use BalatD\KernUx\Rendering\FluidSourceRenderer;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Base class for component markup tests.
 *
 * These tests are the contract for KERN conformance: they assert the exact markup
 * KERN prescribes, because the accessibility guarantees hang off specific classes
 * and ARIA attributes rather than off anything a renderer could infer. They also
 * run under both supported TYPO3 majors, which is what keeps Fluid 4 and Fluid 5
 * from drifting apart unnoticed.
 */
abstract class AbstractComponentTestCase extends FunctionalTestCase
{
    /**
     * The testing framework refuses to build the package collection when anything
     * ext_emconf.php names - dependency or suggestion - is missing, so both have to
     * be loaded here. `form` is a system extension and belongs in the core list.
     */
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    /**
     * Renders Fluid source relying on the globally registered `k` namespace, exactly
     * as Content Blocks and form partials do.
     */
    /**
     * @param array<string, mixed> $variables
     */
    protected function renderSource(string $source, array $variables = []): string
    {
        $renderer = $this->get(FluidSourceRenderer::class);
        self::assertInstanceOf(FluidSourceRenderer::class, $renderer);

        return $renderer->render($source, $variables);
    }

    /**
     * Components must emit their markup and nothing else.
     *
     * A trailing newline in a component template is a root-level text node, so it
     * lands in the output - and for an inline component that means a stray space
     * before whatever follows ("Status: X ." instead of "Status: X."). Component
     * templates therefore end without a final newline, and root-level
     * <f:argument>/<f:variable> tags are chained (`/><f:...`) so no text nodes
     * remain between them. This assertion is what keeps both rules honest.
     */
    protected static function assertNoStrayWhitespace(string $rendered): void
    {
        self::assertSame(
            trim($rendered),
            $rendered,
            'Component emitted surrounding whitespace. Chain root-level tags with '
            . '`/><f:...` and make sure the template file does not end in a newline.',
        );
    }
}
