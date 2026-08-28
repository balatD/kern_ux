<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Unit\Language;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Keeps the language files complete.
 *
 * A missing key does not fail anywhere: TYPO3's sL() returns an empty string and
 * Content Blocks silently falls back to the raw field identifier, so an untranslated
 * label looks like a design decision rather than an omission. That is exactly how the
 * whole set of content-block field labels went missing once. These tests turn each of
 * those silent gaps into a build failure.
 *
 * A unit test rather than a functional one on purpose: it reads the XLIFF and the YAML
 * straight from disk, so it needs no database and no TYPO3 bootstrap, and it runs the
 * same way under both supported majors.
 */
final class LanguageCoverageTest extends UnitTestCase
{
    private const EXT_ROOT = __DIR__ . '/../../..';
    protected bool $resetSingletonInstances = true;

    /**
     * @return array<string, array{string, string}>
     */
    public static function translationPairs(): array
    {
        $pairs = [];
        foreach (['locallang', 'locallang_be', 'locallang_form'] as $name) {
            $pairs[$name] = [
                self::EXT_ROOT . "/Resources/Private/Language/{$name}.xlf",
                self::EXT_ROOT . "/Resources/Private/Language/de.{$name}.xlf",
            ];
        }
        foreach (self::blockDirectories() as $block => $dir) {
            $pairs["content block {$block}"] = [
                "{$dir}/language/labels.xlf",
                "{$dir}/language/de.labels.xlf",
            ];
        }

        return $pairs;
    }

    /**
     * Every source key must have a German counterpart, and the German file must not
     * carry keys the source no longer has - a stale translation is a key nobody will
     * ever see again.
     */
    #[Test]
    #[DataProvider('translationPairs')]
    public function germanTranslationCoversTheSource(string $source, string $translation): void
    {
        self::assertFileExists($source);
        self::assertFileExists($translation);

        $sourceKeys = array_keys(self::unitsOf($source));
        $targets = self::unitsOf($translation);

        $missing = array_values(array_diff($sourceKeys, array_keys($targets)));
        self::assertSame([], $missing, 'Untranslated in ' . basename($translation) . ': ' . implode(', ', $missing));

        $stale = array_values(array_diff(array_keys($targets), $sourceKeys));
        self::assertSame([], $stale, 'No longer in the source file: ' . implode(', ', $stale));

        $empty = [];
        foreach ($targets as $key => $unit) {
            if (trim($unit['target'] ?? '') === '') {
                $empty[] = $key;
            }
        }
        self::assertSame([], $empty, 'Empty <target> in ' . basename($translation) . ': ' . implode(', ', $empty));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function contentBlocks(): array
    {
        $cases = [];
        foreach (self::blockDirectories() as $block => $dir) {
            $cases[$block] = [$dir, $block];
        }

        return $cases;
    }

    /**
     * Content Blocks resolves a field label as "<identifier>.label" inside the block's
     * own labels.xlf, and where the key is absent it falls back to the bare identifier
     * - so a forgotten label shows the editor "aboveTheFold" instead of a sentence.
     */
    #[Test]
    #[DataProvider('contentBlocks')]
    public function everyOwnFieldHasALabel(string $dir, string $block): void
    {
        $config = self::parseYaml("{$dir}/config.yaml");
        $keys = self::unitsOf("{$dir}/language/labels.xlf");

        $missing = [];
        foreach (self::labelPathsOf(self::fieldsOf($config), '') as $path) {
            if (!isset($keys[$path])) {
                $missing[] = $path;
            }
        }

        self::assertSame(
            [],
            $missing,
            "Content block {$block} declares fields with no label in labels.xlf: " . implode(', ', $missing),
        );
    }

    /**
     * Both blocks need a title and a description: without them the element shows up in
     * the new-content wizard as its bare name.
     */
    #[Test]
    #[DataProvider('contentBlocks')]
    public function hasATitleAndADescription(string $dir, string $block): void
    {
        $keys = self::unitsOf("{$dir}/language/labels.xlf");

        foreach (['title', 'description'] as $required) {
            self::assertArrayHasKey($required, $keys, "Content block {$block} has no '{$required}'");
            self::assertNotSame('', trim($keys[$required]['source']), "Content block {$block} has an empty '{$required}'");
        }
    }

    /**
     * Collects "<identifier>.label" for every field the block declares itself,
     * recursing into Collection children. Fields taken over from core via
     * useExistingField keep core's own label and must not be listed.
     *
     * @param list<array<string, mixed>> $fields
     * @return list<string>
     */
    private static function labelPathsOf(array $fields, string $prefix): array
    {
        $paths = [];
        foreach ($fields as $field) {
            $identifier = self::stringValue($field['identifier'] ?? null);
            $type = self::stringValue($field['type'] ?? null);

            // Palettes, tabs and linebreaks are layout, and they carry explicit LLL
            // labels in the Basics rather than generated ones.
            if (in_array($type, ['Palette', 'Tab', 'Linebreak'], true)) {
                continue;
            }
            // A Basic is a reference, not a field of this block: its identifier is a
            // path into ContentBlocks/Basics and the fields it pulls in label
            // themselves there.
            if ($type === 'Basic') {
                continue;
            }
            if ($identifier === '' || ($field['useExistingField'] ?? false) === true) {
                continue;
            }

            $path = $prefix === '' ? $identifier : "{$prefix}.{$identifier}";
            $paths[] = "{$path}.label";

            if ($type === 'Collection') {
                foreach (self::labelPathsOf(self::fieldsOf($field), $path) as $nested) {
                    $paths[] = $nested;
                }
            }
        }

        return $paths;
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
     * @return array<string, array{source: string, target: string|null}>
     */
    private static function unitsOf(string $file): array
    {
        $xml = simplexml_load_string((string)file_get_contents($file));
        self::assertNotFalse($xml, "Not parseable as XML: {$file}");

        $units = [];
        foreach ($xml->file->body->{'trans-unit'} as $unit) {
            $units[(string)($unit['id'] ?? '')] = [
                'source' => (string)$unit->source,
                'target' => isset($unit->target) ? (string)$unit->target : null,
            ];
        }

        return $units;
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

    /**
     * The "fields" entry of a block or of a Collection, with anything that is not a
     * field definition dropped - YAML gives no guarantees, and level max holds us to
     * proving the shape rather than assuming it.
     *
     * @param array<string, mixed> $definition
     * @return list<array<string, mixed>>
     */
    private static function fieldsOf(array $definition): array
    {
        $fields = $definition['fields'] ?? [];
        if (!is_array($fields)) {
            return [];
        }

        $typed = [];
        foreach ($fields as $field) {
            if (is_array($field)) {
                /** @var array<string, mixed> $field */
                $typed[] = $field;
            }
        }

        return $typed;
    }

    private static function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
