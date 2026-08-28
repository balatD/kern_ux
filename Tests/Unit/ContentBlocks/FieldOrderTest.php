<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Unit\ContentBlocks;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Pins the order in which a content block presents its fields to an editor.
 *
 * The heading is what an editor writes first and what the page module shows as the
 * element's label, so it has to be the first thing in the form - and the spacing
 * palette opens the Appearance tab, so every field placed after it silently moves
 * into that tab. Content Blocks appends the Basics listed under the root `basics:`
 * key *after* the block's own fields, which is why both Basics are referenced inline
 * as `type: Basic` instead: there the position is the one written down.
 *
 * A unit test on purpose: it reads the YAML straight from disk, so it needs neither a
 * database nor a TYPO3 bootstrap and runs the same way under both supported majors.
 */
final class FieldOrderTest extends UnitTestCase
{
    private const EXT_ROOT = __DIR__ . '/../../..';

    #[Test]
    public function noBlockAppendsItsBasics(): void
    {
        $offenders = [];
        foreach (self::blockDirectories() as $block => $dir) {
            if (array_key_exists('basics', self::parseYaml("{$dir}/config.yaml"))) {
                $offenders[] = $block;
            }
        }

        self::assertSame(
            [],
            $offenders,
            'These blocks list Basics under the root "basics" key, which appends them after '
            . 'their own fields. Reference them as "type: Basic" inside "fields": '
            . implode(', ', $offenders),
        );
    }

    #[Test]
    public function theHeadingComesFirst(): void
    {
        $offenders = [];
        foreach (self::blockDirectories() as $block => $dir) {
            $identifiers = self::fieldIdentifiersOf($dir);
            if (!in_array('KernUx/Heading', $identifiers, true)) {
                continue;
            }
            if ($identifiers[0] !== 'KernUx/Heading') {
                $offenders[$block] = $identifiers[0];
            }
        }

        self::assertSame(
            [],
            $offenders,
            'These blocks bury the heading below other fields: ' . json_encode($offenders),
        );
    }

    #[Test]
    public function theSpacingComesLast(): void
    {
        $offenders = [];
        foreach (self::blockDirectories() as $block => $dir) {
            $identifiers = self::fieldIdentifiersOf($dir);
            if (!in_array('KernUx/Spacing', $identifiers, true)) {
                continue;
            }
            $last = $identifiers[count($identifiers) - 1];
            if ($last !== 'KernUx/Spacing') {
                $offenders[$block] = $last;
            }
        }

        self::assertSame(
            [],
            $offenders,
            'These blocks put fields behind the spacing palette, which hides them in the '
            . 'Appearance tab: ' . json_encode($offenders),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function blockDirectories(): array
    {
        $blocks = [];
        foreach (glob(self::EXT_ROOT . '/ContentBlocks/ContentElements/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $blocks[basename($dir)] = $dir;
        }
        ksort($blocks);

        return $blocks;
    }

    /**
     * The top-level field identifiers of a block, in the order the editor meets them.
     *
     * @return list<string>
     */
    private static function fieldIdentifiersOf(string $dir): array
    {
        $fields = self::parseYaml("{$dir}/config.yaml")['fields'] ?? null;
        self::assertIsArray($fields, 'Content block ' . basename($dir) . ' declares no fields');

        $identifiers = [];
        foreach ($fields as $field) {
            if (is_array($field) && is_string($field['identifier'] ?? null)) {
                $identifiers[] = $field['identifier'];
            }
        }

        return $identifiers;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseYaml(string $file): array
    {
        $parsed = Yaml::parseFile($file);
        self::assertIsArray($parsed, "Not parseable as YAML: {$file}");
        /** @var array<string, mixed> $parsed */
        return $parsed;
    }
}
