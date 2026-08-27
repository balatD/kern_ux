<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\ContentBlocks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use TYPO3Fluid\Fluid\Core\Parser\Exception as FluidParserException;
use TYPO3Fluid\Fluid\Core\Parser\ParsedTemplateInterface;

/**
 * Every content-block template has to parse.
 *
 * Content-block templates are never compiled until something actually renders that
 * element, so a syntax error in one of them stays invisible until an editor places
 * the block on a page - and then it is a fatal, not a warning. Two templates carried
 * a broken `id=\"...\"` attribute for exactly that reason: nothing in the suite ever
 * looked at them.
 *
 * Parsing rather than rendering on purpose. Several blocks call f:cObject or query the
 * database, so a full render needs a frontend request and real records; parsing needs
 * neither and still catches the whole class of defect this test exists for.
 */
final class TemplateSyntaxTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function templates(): array
    {
        $root = dirname(__DIR__, 3);
        $cases = [];

        $patterns = [
            '/ContentBlocks/ContentElements/*/templates/*.html',
            '/Resources/Private/Components/*/*/*.html',
            '/Resources/Private/PageView/*/*.html',
            '/Resources/Private/Partials/*/*/*/*.html',
            '/Resources/Private/Templates/*/*.html',
        ];

        foreach ($patterns as $pattern) {
            foreach (glob($root . $pattern) ?: [] as $file) {
                $cases[substr($file, strlen($root) + 1)] = [$file];
            }
        }
        ksort($cases);

        return $cases;
    }

    #[Test]
    #[DataProvider('templates')]
    public function parsesWithoutError(string $file): void
    {
        $factory = $this->get(RenderingContextFactory::class);
        self::assertInstanceOf(RenderingContextFactory::class, $factory);

        $source = (string)file_get_contents($file);

        try {
            $parsed = $factory->create()->getTemplateParser()->parse($source);
        } catch (FluidParserException $e) {
            self::fail(basename($file) . ' does not parse: ' . $e->getMessage());
        }

        self::assertInstanceOf(ParsedTemplateInterface::class, $parsed);
    }

    /**
     * Component templates must not end in a newline: a trailing newline is a
     * root-level text node and lands in the output as a stray space, which shows up as
     * "Status: X ." wherever a component is used inline.
     */
    #[Test]
    public function componentTemplatesDoNotEndInANewline(): void
    {
        $root = dirname(__DIR__, 3) . '/Resources/Private/Components';
        $offenders = [];

        foreach (glob($root . '/*/*/*.html') ?: [] as $file) {
            $source = (string)file_get_contents($file);
            if ($source !== rtrim($source, "\r\n")) {
                $offenders[] = substr($file, strlen($root) + 1);
            }
        }

        self::assertSame([], $offenders, 'These component templates end in a newline: ' . implode(', ', $offenders));
    }

    /**
     * Every backend preview has to declare the Preview layout and a Content section.
     *
     * TYPO3's page module asks a preview renderer for three parts - header, content and
     * footer - and Content Blocks answers all three by rendering backend-preview.html
     * through a layout that pulls the matching section out of it. A template without a
     * layout has no sections, so the same output is returned three times and the editor
     * sees every element's preview printed three times over. Nothing errors, and the
     * suite never noticed, because the templates parse perfectly either way.
     *
     * Only Content is defined on purpose: with the section missing Content Blocks falls
     * back to core's own header and footer, and core's header is better than anything we
     * would write - it links to the edit form and marks a hidden heading.
     */
    #[Test]
    public function everyBackendPreviewDeclaresTheLayoutAndTheContentSection(): void
    {
        $root = dirname(__DIR__, 3) . '/ContentBlocks/ContentElements';
        $offenders = [];

        foreach (glob($root . '/*/templates/backend-preview.html') ?: [] as $file) {
            $source = (string)file_get_contents($file);
            $block = basename(dirname($file, 2));

            if (!str_contains($source, '<f:layout name="Preview" />')) {
                $offenders[$block] = 'no <f:layout name="Preview" />';
                continue;
            }
            if (!str_contains($source, '<f:section name="Content">')) {
                $offenders[$block] = 'no <f:section name="Content">';
                continue;
            }
            foreach (['Header', 'Footer'] as $section) {
                if (str_contains($source, '<f:section name="' . $section . '">')) {
                    $offenders[$block] = 'defines a ' . $section . ' section instead of leaving core\'s to it';
                }
            }
        }

        self::assertSame([], $offenders, 'Broken backend previews: ' . json_encode($offenders));
    }

    /**
     * A preview must not repeat the element's own heading.
     *
     * Core's preview header already renders the label field, as a link to the edit form,
     * so a template that prints {data.header} again puts the same line twice into every
     * card. The blocks with no heading field of their own - divider, progress,
     * button-group - are the exception: there core's header slot renders nothing.
     */
    #[Test]
    public function backendPreviewsDoNotRepeatTheHeaderField(): void
    {
        $root = dirname(__DIR__, 3) . '/ContentBlocks/ContentElements';
        $offenders = [];

        foreach (glob($root . '/*/templates/backend-preview.html') ?: [] as $file) {
            $block = basename(dirname($file, 2));
            $config = (string)file_get_contents(dirname($file, 2) . '/config.yaml');
            if (!str_contains($config, 'KernUx/Heading')) {
                continue;
            }

            $source = (string)file_get_contents($file);
            if (str_contains($source, '{data.header}')) {
                $offenders[] = $block;
            }
        }

        self::assertSame([], $offenders, 'These previews repeat the heading: ' . implode(', ', $offenders));
    }

    /**
     * f:translate arguments must be an array literal, not a wrapped variable.
     *
     * `arguments="{0: {data.columns}}"` looks like a nested array and is a string: the
     * inner braces interpolate, so Fluid receives "{0: 3}" as text and throws
     * InvalidArgumentValueException at *render* time. Nothing catches it earlier - the
     * template parses, the unit tests pass, and the editor gets a red box in the page
     * module instead of a preview. Two of ours shipped like that.
     */
    #[Test]
    #[DataProvider('templates')]
    public function translateArgumentsAreArraysNotStrings(string $file): void
    {
        $source = (string)file_get_contents($file);

        self::assertDoesNotMatchRegularExpression(
            '/arguments="\{[^"]*\{/',
            $source,
            basename($file) . ' wraps a variable in braces inside f:translate arguments. '
            . 'Write arguments="{0: some.variable}", not arguments="{0: {some.variable}}".',
        );
    }
}
