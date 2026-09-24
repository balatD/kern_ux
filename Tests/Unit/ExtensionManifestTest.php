<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Pins composer.json and ext_emconf.php to each other.
 *
 * The two files describe the same package to two installers that never meet: Composer
 * reads one, the Extension Manager and TER read the other, and nothing in TYPO3 keeps
 * them aligned. They are synced by hand, so every divergence is a silent one - it
 * surfaces as a refused install in somebody else's project, months later.
 *
 * The two notations cannot be compared as strings: Composer writes caret unions and TER
 * writes a single continuous dash-range. So this test DERIVES the expected TER range
 * from the Composer constraint instead, which makes the emconf side a mechanical
 * consequence of the composer side rather than a second thing to remember.
 *
 * That derivation is the outer hull, and it is deliberately wider than what CI proves:
 * `^13.4 || ^14.3` becomes `13.4.0-14.99.99`. A dash range cannot express a disjunction
 * anyway - any range covering both 1.6 and 2.4 also covers the 2.0-2.3 gap between them -
 * so a hull is the closest honest translation. Which versions are actually *tested* is
 * stated where humans read it: the README badges and the CI matrix.
 *
 * A unit test on purpose: it reads the files straight from disk, so it needs neither a
 * database nor a TYPO3 bootstrap and runs the same way under both supported majors.
 */
final class ExtensionManifestTest extends UnitTestCase
{
    private const EXT_ROOT = __DIR__ . '/../..';

    private const EXTENSION_KEY = 'kern_ux';

    /**
     * Composer package names whose extension key is not derivable from the name.
     *
     * Everything else has to match `typo3/cms-<key>`; an unmapped package fails the test
     * rather than guessing, because a wrong key here would silently drop a dependency
     * from the comparison and the test would go green while the manifests diverged.
     */
    private const PACKAGE_KEYS = [
        'php' => 'php',
        'typo3/cms-core' => 'typo3',
        'friendsoftypo3/content-blocks' => 'content_blocks',
    ];

    #[Test]
    public function theTwoManifestsDeclareTheSameVersion(): void
    {
        self::assertSame(
            self::typo3Extra()['version'] ?? null,
            self::emconf()['version'] ?? null,
            'TER ships the ext_emconf.php version while Packagist ships the git tag, so a '
            . 'half-applied bump stays invisible until somebody installs the extension.',
        );
    }

    #[Test]
    public function theTwoManifestsDeclareTheSameExtensionKey(): void
    {
        self::assertSame(
            self::EXTENSION_KEY,
            self::typo3Extra()['extension-key'] ?? null,
            'composer.json names a different extension key than this test expects.',
        );
        self::assertArrayHasKey(
            self::EXTENSION_KEY,
            self::emConfBlock(),
            'ext_emconf.php defines $EM_CONF under a different key, which splits the '
            . 'package in two: Composer installs one extension, the Extension Manager sees another.',
        );
    }

    #[Test]
    public function theTwoManifestsDeclareTheSameAutoloadMap(): void
    {
        self::assertSame(
            self::composer()['autoload'] ?? null,
            self::emconf()['autoload'] ?? null,
            'A classic-mode install reads the ext_emconf.php autoload map. Drift there is a '
            . 'class-not-found that never reproduces under Composer.',
        );
    }

    #[Test]
    public function everyComposerRequirementIsDeclaredAsAnEmconfDependency(): void
    {
        self::assertSame(
            self::extensionKeysOf(self::section(self::composer(), 'require')),
            array_keys(self::section(self::constraints(), 'depends')),
            'composer.json and ext_emconf.php require a different set of packages.',
        );
    }

    #[Test]
    public function everyComposerSuggestionIsDeclaredAsAnEmconfSuggestion(): void
    {
        self::assertSame(
            self::extensionKeysOf(self::section(self::composer(), 'suggest')),
            array_keys(self::section(self::constraints(), 'suggests')),
            'composer.json and ext_emconf.php suggest a different set of packages. A '
            . 'non-Composer install is then never told what an optional feature needs.',
        );
    }

    /**
     * Only `require` has a derivable counterpart.
     *
     * Composer's `suggest` maps a package to a sentence explaining why you might want it,
     * not to a version constraint, so there is nothing to derive the emconf `suggests`
     * ranges from. Those stay judgement, and the set of packages is what
     * everyComposerSuggestionIsDeclaredAsAnEmconfSuggestion() pins.
     */
    #[Test]
    public function everyEmconfDependencyIsTheHullOfItsComposerConstraint(): void
    {
        $depends = self::section(self::constraints(), 'depends');

        foreach (self::section(self::composer(), 'require') as $package => $constraint) {
            $key = self::extensionKeyOf($package);
            if ($key === null) {
                continue;
            }

            self::assertIsString($constraint);
            self::assertSame(
                self::hullOf($constraint, $package),
                $depends[$key] ?? null,
                "ext_emconf.php constrains {$key} to something other than the hull of "
                . "composer.json's \"{$package}\": \"{$constraint}\".",
            );
        }
    }

    /**
     * The widest continuous range that covers every branch of a Composer caret union.
     *
     * Floor is the lowest branch, padded to three parts. Ceiling is the highest branch's
     * major with .99.99, which is how a TER range spells "this major, any minor".
     */
    private static function hullOf(string $constraint, string $package): string
    {
        $branches = preg_split('/\s*\|\|\s*/', trim($constraint)) ?: [];

        $floors = [];
        $highestMajor = 0;
        foreach ($branches as $branch) {
            if (preg_match('/^\^(\d+)\.(\d+)(?:\.(\d+))?$/', $branch, $matches) !== 1) {
                self::fail(
                    "composer.json constrains {$package} to \"{$branch}\", which is not a plain "
                    . 'caret. Teach this test how that spells as a TER range before using it.',
                );
            }

            $floors[] = sprintf('%d.%d.%d', (int)$matches[1], (int)$matches[2], (int)($matches[3] ?? 0));
            $highestMajor = max($highestMajor, (int)$matches[1]);
        }

        usort($floors, static fn(string $a, string $b): int => version_compare($a, $b));

        return sprintf('%s-%d.99.99', $floors[0], $highestMajor);
    }

    /**
     * One map out of a decoded manifest, or an empty one where the section is absent.
     *
     * @param array<string, mixed> $manifest
     *
     * @return array<string, mixed>
     */
    private static function section(array $manifest, string $name): array
    {
        $section = $manifest[$name] ?? [];
        self::assertIsArray($section, "The \"{$name}\" section is not a map.");

        /** @var array<string, mixed> $section */
        return $section;
    }

    /**
     * @param array<string, mixed> $packages
     *
     * @return list<string>
     */
    private static function extensionKeysOf(array $packages): array
    {
        $keys = [];
        foreach (array_keys($packages) as $package) {
            $key = self::extensionKeyOf($package);
            if ($key !== null) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Null for a package that has no place in ext_emconf.php at all.
     *
     * That is only ever a PHP extension: TER's dependency notation can name extensions
     * and PHP itself, and has no way to spell "ext-gd".
     */
    private static function extensionKeyOf(string $package): ?string
    {
        if (str_starts_with($package, 'ext-')) {
            return null;
        }

        if (isset(self::PACKAGE_KEYS[$package])) {
            return self::PACKAGE_KEYS[$package];
        }

        if (str_starts_with($package, 'typo3/cms-')) {
            return str_replace('-', '_', substr($package, strlen('typo3/cms-')));
        }

        self::fail(
            "composer.json requires \"{$package}\", and this test does not know which TYPO3 "
            . 'extension key that is. Add it to PACKAGE_KEYS.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function constraints(): array
    {
        $constraints = self::emconf()['constraints'] ?? null;
        self::assertIsArray($constraints, 'ext_emconf.php declares no constraints.');

        /** @var array<string, mixed> $constraints */
        return $constraints;
    }

    /**
     * @return array<string, mixed>
     */
    private static function typo3Extra(): array
    {
        $extra = self::composer()['extra'] ?? [];
        self::assertIsArray($extra);
        $typo3 = $extra['typo3/cms'] ?? null;
        self::assertIsArray($typo3, 'composer.json declares no extra."typo3/cms" block.');

        /** @var array<string, mixed> $typo3 */
        return $typo3;
    }

    /**
     * @return array<string, mixed>
     */
    private static function emconf(): array
    {
        $conf = self::emConfBlock()[self::EXTENSION_KEY] ?? null;
        self::assertIsArray($conf, 'ext_emconf.php defines no $EM_CONF entry for this extension.');

        /** @var array<string, mixed> $conf */
        return $conf;
    }

    /**
     * @return array<string, mixed>
     */
    private static function emConfBlock(): array
    {
        // require, never require_once: the second test method to call this would otherwise
        // get the empty array back, because the include has already run.
        $_EXTKEY = self::EXTENSION_KEY;
        /** @var array<string, mixed> $EM_CONF */
        $EM_CONF = [];
        require self::EXT_ROOT . '/ext_emconf.php';

        return $EM_CONF;
    }

    /**
     * @return array<string, mixed>
     */
    private static function composer(): array
    {
        $raw = file_get_contents(self::EXT_ROOT . '/composer.json');
        self::assertIsString($raw, 'composer.json is unreadable.');

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded, 'composer.json does not decode to an array.');

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
