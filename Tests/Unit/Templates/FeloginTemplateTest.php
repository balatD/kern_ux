<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Unit\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The felogin templates, which are the one KERN surface no other test can reach.
 *
 * felogin renders through an Extbase controller, so its templates cannot be rendered the
 * way component and content-block templates are: f:form, f:link.action and the partials
 * they call all need a request and a controller context. TemplateSyntaxTest proves they
 * parse; what it cannot prove is that they still carry the KERN contract, and that is
 * the whole reason for overriding core's templates in the first place.
 *
 * So this reads them from disk. Crude, and it is the level at which the two things that
 * actually break are visible: a view path that no longer wins over felogin's own, and a
 * kern-* class that does not exist.
 *
 * That second check is the one that earns its keep. KERN names the control after the
 * control, not after its wrapper - kern-form-check__checkbox, never
 * kern-form-check__input - and a class that KERN does not define fails silently: the
 * markup renders, the page looks nearly right, and the focus ring and error state that
 * the class carried are simply gone. Only a selector in the fetched distribution counts
 * as evidence; documentation and examples do not.
 *
 * A unit test on purpose: it reads files, so it needs neither a database nor a TYPO3
 * bootstrap and runs the same way under both supported majors.
 */
final class FeloginTemplateTest extends UnitTestCase
{
    private const EXT_ROOT = __DIR__ . '/../../..';

    /**
     * @return array<string, array{string}>
     */
    public static function feloginTemplates(): array
    {
        return [
            'login' => ['Resources/Private/Templates/Felogin/Login/Login.html'],
            'logout' => ['Resources/Private/Templates/Felogin/Login/Logout.html'],
            'overview' => ['Resources/Private/Templates/Felogin/Login/Overview.html'],
            'password recovery' => ['Resources/Private/Templates/Felogin/PasswordRecovery/Recovery.html'],
            'change password' => ['Resources/Private/Templates/Felogin/PasswordRecovery/ShowChangePassword.html'],
            'field partial' => ['Resources/Private/Partials/Felogin/Field.html'],
            'validation errors partial' => ['Resources/Private/Partials/Felogin/ValidationErrors.html'],
        ];
    }

    #[Test]
    #[DataProvider('feloginTemplates')]
    public function shipsTheTemplateFeloginWillLookFor(string $relative): void
    {
        self::assertFileExists(
            self::EXT_ROOT . '/' . $relative,
            'felogin resolves templates by name. A missing file falls back to core\'s, which '
            . 'renders without KERN classes and without any error to notice it by.',
        );
    }

    #[Test]
    #[DataProvider('feloginTemplates')]
    public function usesOnlyClassesTheKernDistributionDefines(string $relative): void
    {
        $css = self::kernCss();
        $markup = (string)file_get_contents(self::EXT_ROOT . '/' . $relative);

        preg_match_all('/kern-[a-z0-9_-]+/', $markup, $matches);

        $invented = [];
        foreach (array_unique($matches[0]) as $class) {
            if (preg_match('/\.' . preg_quote($class, '/') . '(?![\w-])/', $css) !== 1) {
                $invented[] = $class;
            }
        }
        sort($invented);

        self::assertSame(
            [],
            $invented,
            "These classes are not defined anywhere in the KERN distribution:\n  "
            . implode("\n  ", $invented)
            . "\n\nKERN's examples and documentation are not evidence - only a selector in "
            . 'kern.css is. A class KERN does not ship carries no styling and no focus or '
            . 'error state, and nothing reports it. If KERN really has no such class, define '
            . 'it in Resources/Public/Css/kernt3.css under the kernt3- prefix instead.',
        );
    }

    #[Test]
    public function registersItsViewPathsAboveFeloginsOwn(): void
    {
        $setup = (string)file_get_contents(self::EXT_ROOT . '/Configuration/Sets/KernUx/setup.typoscript');

        foreach (['templateRootPaths', 'partialRootPaths'] as $kind) {
            $matched = preg_match(
                '/^\s*' . $kind . '\.(\d+)\s*=\s*EXT:kern_ux\/Resources\/Private\/\w+\/Felogin\//m',
                $setup,
                $matches,
            );
            if ($matched !== 1) {
                self::fail(
                    "The site set does not register a Felogin {$kind} entry, so felogin keeps "
                    . 'rendering its own templates and none of this exists as far as a visitor '
                    . 'is concerned.',
                );
            }

            // felogin puts its own paths at 10. Fluid resolves the highest index first.
            self::assertGreaterThan(
                10,
                (int)$matches[1],
                "The Felogin {$kind} entry sits at or below felogin's own index 10, so felogin's "
                . 'templates win and the KERN ones are never reached.',
            );
        }
    }

    /**
     * Nothing core's own template can say may go missing from ours.
     *
     * Replacing a template means re-typing every label and every setting it reads, and a
     * dropped one is invisible: the page still renders, it is just quieter than before.
     * That is not hypothetical - `change_password_description` was lost here exactly that
     * way, and only a line-by-line comparison against core found it.
     *
     * So this compares against core's templates rather than a list somebody maintains:
     * every `f:translate` key, every message an editor can override through the plugin's
     * FlexForm via the RenderLabelOrMessage partial, and every `settings.*` the template
     * reads. Variables are followed, because a key that has to be hoisted into an
     * f:variable is the normal case here - braces inside an inline `arguments` array fail
     * at render time, see TemplateSyntaxTest::translateArgumentsAreArraysNotStrings.
     *
     * @param string $relative template path below Resources/Private/Templates/Felogin
     */
    #[Test]
    #[DataProvider('overriddenTemplates')]
    public function saysEverythingCoresTemplateSays(string $relative): void
    {
        $core = self::coreTemplate($relative);
        if ($core === null) {
            self::markTestSkipped('EXT:felogin is not installed here, so there is nothing to compare against.');
        }

        $theirs = (string)file_get_contents($core);
        $ours = (string)file_get_contents(self::EXT_ROOT . '/Resources/Private/Templates/Felogin/' . $relative);

        $missing = [];
        foreach (self::labelKeysOf($theirs) as $key) {
            if (!self::mentions($ours, $key)) {
                $missing[] = 'label ' . $key;
            }
        }
        foreach (self::settingsOf($theirs) as $setting) {
            if (!str_contains($ours, 'settings.' . $setting)) {
                $missing[] = 'settings.' . $setting;
            }
        }
        sort($missing);

        self::assertSame(
            [],
            $missing,
            "{$relative} no longer carries everything core's template does:\n  "
            . implode("\n  ", $missing)
            . "\n\nEach of these is either a label a visitor reads or a plugin setting an "
            . 'editor can fill in. Dropping one fails silently - the form still renders.',
        );
    }

    /**
     * Core's copy of a template, under whichever name this major gives it.
     *
     * TYPO3 14 renamed the whole set to *.fluid.html. Looking only for the 13 spelling
     * did not fail the test - it skipped it, so the comparison quietly stopped running on
     * the newer major while still reporting green. Which is the same kind of silence this
     * test exists to catch, so both names are tried and only a genuinely absent felogin
     * skips.
     */
    private static function coreTemplate(string $relative): ?string
    {
        $base = self::EXT_ROOT . '/vendor/typo3/cms-felogin/Resources/Private/Templates/';
        foreach ([$relative, preg_replace('/\.html$/', '.fluid.html', $relative)] as $candidate) {
            if (is_string($candidate) && is_file($base . $candidate)) {
                return $base . $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function overriddenTemplates(): array
    {
        return [
            'login' => ['Login/Login.html'],
            'logout' => ['Login/Logout.html'],
            'overview' => ['Login/Overview.html'],
            'password recovery' => ['PasswordRecovery/Recovery.html'],
            'change password' => ['PasswordRecovery/ShowChangePassword.html'],
        ];
    }

    /**
     * Translation keys and overridable message keys, however they are spelled.
     *
     * `id` is a documented alias of `key` on f:translate and core uses both.
     *
     * @return list<string>
     */
    private static function labelKeysOf(string $markup): array
    {
        preg_match_all('/f:translate\s+(?:key|id)="([^"]+)"/', $markup, $tagged);
        preg_match_all("/f:translate\(key:\s*'([^']+)'/", $markup, $inline);
        preg_match_all("/RenderLabelOrMessage[^>]*?\{key:\s*'([^']+)'/", $markup, $overridable);

        return array_values(array_unique([...$tagged[1], ...$inline[1], ...$overridable[1]]));
    }

    /**
     * @return list<string>
     */
    private static function settingsOf(string $markup): array
    {
        preg_match_all('/settings\.(\w+)/', $markup, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Whether the template reaches a key, directly or through an f:variable.
     *
     * `{messageKey}_header` is built as a variable before it is passed on, so a plain
     * substring search would report it missing and hide the keys that really are.
     */
    private static function mentions(string $markup, string $key): bool
    {
        if (str_contains($markup, $key)) {
            return true;
        }

        // A composed key such as {messageKey}_header: the literal tail has to appear
        // somewhere, and it does so in the f:variable that builds it.
        $tail = preg_replace('/^\{\w+\}/', '', $key);

        return is_string($tail) && $tail !== $key && $tail !== '' && str_contains($markup, $tail);
    }

    private static function kernCss(): string
    {
        $file = self::EXT_ROOT . '/Resources/Public/Vendor/KernUx/kern.css';
        if (!is_file($file)) {
            self::markTestSkipped(
                'The KERN distribution is not fetched here. Run kern-ux:assets:install; the '
                . 'CI job that owns this check does.',
            );
        }

        return (string)file_get_contents($file);
    }
}
