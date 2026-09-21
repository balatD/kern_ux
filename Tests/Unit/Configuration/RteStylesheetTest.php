<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Pins how the two rich-text stylesheets may be scoped and coloured.
 *
 * CKEditor 5 does not load a `contentsCss` file as written. TYPO3's
 * CKEditor5Element.prefixContentsCss() fetches it and runs every selector through
 * prefixAndRebaseCss(), which prepends `#<id> .ck-content` - except to a leading
 * `:root`, `html` or `body`, which it REPLACES with that prefix. So a rule spelled
 * `.ck-content p` arrives as `#<id> .ck-content .ck-content p` and matches nothing,
 * and a rule spelled `.kernt3-rte p` lands under a class the editing root never
 * carries. Both fail silently: the file loads, the CSS parses, nothing applies.
 *
 * kern.css is not loaded into the editing view either, so a var(--kern-color-*) there
 * resolves to its literal - and every literal is a light-theme value. On TYPO3 14,
 * whose contents.css follows the backend colour scheme, that is near-black text on the
 * dark editing surface. Colour is core's to set and this file may not take it back.
 *
 * Both failures are silent, which is what makes this a contract. Nothing else in the
 * suite would notice rich text going unstyled or unreadable in the backend.
 *
 * A unit test on purpose: it reads the files straight from disk, so it needs neither a
 * database nor a TYPO3 bootstrap and runs the same way under both supported majors.
 */
final class RteStylesheetTest extends UnitTestCase
{
    private const EXT_ROOT = __DIR__ . '/../../..';

    private const FRONTEND_STYLESHEET = 'Resources/Public/Css/rte.css';

    private const EDITOR_STYLESHEET = 'Resources/Public/Css/rte-editor.css';

    #[Test]
    public function thePresetLoadsTheEditorStylesheetAndNotTheFrontendOne(): void
    {
        self::assertSame([self::EDITOR_STYLESHEET], self::contentsCss());
    }

    #[Test]
    public function noStylesheetLoadedIntoTheEditorNamesTheCkContentScope(): void
    {
        foreach (self::contentsCss() as $stylesheet) {
            self::assertStringNotContainsString(
                '.ck-content',
                self::rulesOf($stylesheet),
                "{$stylesheet} is a contentsCss file, so CKEditor prepends `.ck-content` to "
                . 'every selector in it. Naming the class again nests it and the rule dies.',
            );
        }
    }

    #[Test]
    public function noStylesheetLoadedIntoTheEditorNamesTheFrontendScope(): void
    {
        foreach (self::contentsCss() as $stylesheet) {
            self::assertStringNotContainsString(
                '.kernt3-rte',
                self::rulesOf($stylesheet),
                "{$stylesheet} is a contentsCss file, and the editing root carries no "
                . '.kernt3-rte wrapper - that class exists only on the rendered page.',
            );
        }
    }

    /**
     * The editing view has a colour scheme of its own that only core can know, so every
     * colour here has to come from core or from currentColor. A literal would be a
     * light-theme value pinned onto a surface that may be dark.
     */
    #[Test]
    public function noStylesheetLoadedIntoTheEditorDeclaresAColour(): void
    {
        foreach (self::contentsCss() as $stylesheet) {
            $rules = self::rulesOf($stylesheet);

            self::assertStringNotContainsString(
                '--kern-color-',
                $rules,
                "{$stylesheet} reads a KERN colour token, but kern.css is not loaded into the "
                . 'editing view, so it would resolve to its light-theme literal.',
            );
            self::assertSame(
                0,
                preg_match('/#[0-9a-fA-F]{3,8}\b/', $rules),
                "{$stylesheet} declares a colour literal. Colour belongs to the backend theme, "
                . 'which core sets - derive from currentColor where a rule needs one.',
            );
        }
    }

    #[Test]
    public function theFrontendStylesheetCarriesNoEditorSelector(): void
    {
        self::assertStringNotContainsString(
            '.ck-content',
            self::rulesOf(self::FRONTEND_STYLESHEET),
            'A .ck-content rule never matches on the rendered page: that class exists only '
            . 'inside the backend editing view, which loads rte-editor.css instead.',
        );
    }

    #[Test]
    public function theFrontendStylesheetScopesEverythingToItsWrapper(): void
    {
        self::assertSame(
            0,
            preg_match('/^\s*(body|html)\b/m', self::rulesOf(self::FRONTEND_STYLESHEET)),
            'A body or html selector styles the whole page. Only rte-editor.css may use '
            . 'one, where CKEditor swaps it for the editing root.',
        );
    }

    /**
     * The two files carry the same rules under two scopes, so a size or spacing token
     * added to one and forgotten in the other makes the editing view stop matching the
     * page. Colour tokens are excluded because only the frontend may have them.
     */
    #[Test]
    public function bothStylesheetsReadTheSameTypographyAndSpacingTokens(): void
    {
        self::assertSame(
            self::layoutTokensOf(self::FRONTEND_STYLESHEET),
            self::layoutTokensOf(self::EDITOR_STYLESHEET),
        );
    }

    /**
     * The stylesheets the RTE preset hands to CKEditor, as extension-relative paths.
     *
     * @return list<string>
     */
    private static function contentsCss(): array
    {
        $parsed = Yaml::parseFile(self::EXT_ROOT . '/Configuration/RTE/KernUx.yaml');
        self::assertIsArray($parsed);
        $editor = $parsed['editor'] ?? null;
        self::assertIsArray($editor);
        $config = $editor['config'] ?? null;
        self::assertIsArray($config);
        $declared = $config['contentsCss'] ?? null;
        self::assertIsArray($declared, 'The RTE preset declares no contentsCss');

        $stylesheets = [];
        foreach ($declared as $reference) {
            self::assertIsString($reference);
            $stylesheets[] = str_replace('EXT:kern_ux/', '', $reference);
        }

        return $stylesheets;
    }

    /**
     * @return list<string>
     */
    private static function layoutTokensOf(string $stylesheet): array
    {
        preg_match_all('/--kern-(?:typography|metric)-[a-z0-9-]+/', self::rulesOf($stylesheet), $matches);
        $tokens = array_unique($matches[0]);
        sort($tokens);

        return $tokens;
    }

    /**
     * The stylesheet without its comments, so prose about a selector is not read as one.
     */
    private static function rulesOf(string $stylesheet): string
    {
        $path = self::EXT_ROOT . '/' . $stylesheet;
        self::assertFileExists($path);

        return (string)preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents($path));
    }
}
