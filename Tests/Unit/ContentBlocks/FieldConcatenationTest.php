<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Unit\ContentBlocks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * A content-block template may never turn a non-scalar field value into a string.
 *
 * Content Blocks does not hand every field over as a scalar: a Link becomes a
 * TypolinkParameter, a File a FileReference, a DateTime a DateTimeImmutable. Writing
 * `value="{data.a}{data.b}"` therefore works for as long as every field in the list
 * happens to be text, and throws "Object of class TypolinkParameter could not be
 * converted to string" the moment one of them is not - on a rendered page only, with
 * the fields filled, which is the one situation the test suite does not reach.
 *
 * The Standort block shipped exactly that defect: an "is any contact field set" guard
 * concatenated nine values, two of them Links. Parsing was clean, the functional test
 * was green because its fixture left the Links empty, and the page returned a 500.
 *
 * The correct shape for a guard is a boolean expression:
 *   value="{f:if(condition: '{data.a} || {data.b}', then: 1, else: 0)}"
 *
 * The second test covers the same mistake in its other shape: printing a Link field
 * as visible text. The third covers the sharpest one: guarding on a Link field. An
 * object is truthy in Fluid even when the editor left the link empty, so
 * `<f:if condition="{data....link}">` is always true on a real record - the Standort
 * block emitted <a href=""> whose only content was an aria-hidden icon, a focusable
 * link with no accessible name, and the Dienstleistung block would have rendered its
 * call-to-action button pointing nowhere. Resolve the URI first and guard on that
 * string. The Standort block shipped that too - the website line used the raw
 * field as the link label - and it failed in the same place for the same reason.
 *
 * This is a unit test on purpose - it reads the templates off disk and the field types
 * out of config.yaml, needs no TYPO3, and runs on both majors in milliseconds.
 */
final class FieldConcatenationTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function frontendTemplates(): array
    {
        $root = dirname(__DIR__, 3) . '/ContentBlocks/ContentElements';
        $cases = [];
        foreach ((array)glob($root . '/*/templates/frontend.html') as $file) {
            if (!is_string($file)) {
                continue;
            }
            $cases[basename(dirname($file, 2))] = [$file];
        }
        self::assertNotSame([], $cases);

        return $cases;
    }

    #[Test]
    #[DataProvider('frontendTemplates')]
    public function noVariableConcatenatesSeveralFieldValues(string $file): void
    {
        $template = (string)file_get_contents($file);

        preg_match_all('#<f:variable\\b[^>]*\\bvalue="([^"]*)"#', $template, $matches);

        $offenders = [];
        foreach ($matches[1] as $value) {
            // An f:if(...) wrapper is the sanctioned shape: it evaluates the fields as
            // booleans instead of stringifying them.
            if (str_contains($value, 'f:if(')) {
                continue;
            }
            if (preg_match_all('#\\{data\\.[A-Za-z0-9_.]+\\}#', $value) > 1) {
                $offenders[] = $value;
            }
        }

        self::assertSame(
            [],
            $offenders,
            sprintf(
                'A f:variable in the %s block builds a string from several field values. '
                . 'Content Blocks returns objects for Link, File and DateTime fields, so this '
                . 'throws as soon as one of them is filled - on a rendered page only. Use '
                . 'value="{f:if(condition: \'{data.a} || {data.b}\', then: 1, else: 0)}".',
                basename(dirname($file, 2)),
            ),
        );
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function linkFields(): array
    {
        $root = dirname(__DIR__, 3) . '/ContentBlocks/ContentElements';
        $cases = [];
        foreach ((array)glob($root . '/*/config.yaml') as $config) {
            if (!is_string($config)) {
                continue;
            }
            $block = basename(dirname($config));
            $raw = (string)file_get_contents($config);

            // Deliberately a scan rather than a YAML parse: the column name is
            // tx_kernux_<block without dashes>_<identifier>, and all this needs is the
            // identifiers whose type is Link.
            preg_match_all('#identifier:\s*(\S+)\s*\n\s*type:\s*Link\b#', $raw, $matches);
            if ($matches[1] === []) {
                continue;
            }
            $prefix = 'tx_kernux_' . str_replace('-', '', $block) . '_';
            $cases[$block] = [
                $root . '/' . $block . '/templates/frontend.html',
                array_map(static fn(string $id): string => $prefix . $id, $matches[1]),
            ];
        }
        self::assertNotSame([], $cases);

        return $cases;
    }

    /**
     * @param list<string> $columns
     */
    #[Test]
    #[DataProvider('linkFields')]
    public function noTemplatePrintsALinkFieldAsText(string $file, array $columns): void
    {
        $template = (string)file_get_contents($file);

        // Remove the two places a Link field may legitimately appear: resolved through
        // typolink, and evaluated as a boolean in a condition.
        $stripped = (string)preg_replace('#<f:uri\.typolink[^>]*/>#', '', $template);
        $stripped = (string)preg_replace('#\{f:uri\.typolink\([^)]*\)\}#', '', $stripped);
        $stripped = (string)preg_replace('#condition="[^"]*"#', '', $stripped);
        // Inline conditions too: f:if(condition: '...') quotes with apostrophes.
        $stripped = (string)preg_replace("#condition:\s*'[^']*'#", '', $stripped);

        foreach ($columns as $column) {
            self::assertStringNotContainsString(
                '{data.' . $column . '}',
                $stripped,
                sprintf(
                    '%s prints the Link field %s outside of typolink. Content Blocks hands a '
                    . 'Link over as a TypolinkParameter, so this throws "could not be converted '
                    . 'to string" as soon as an editor fills it. Resolve it into a variable '
                    . 'first: <f:variable name="uri"><f:uri.typolink parameter="{%s}" /></f:variable>.',
                    basename(dirname($file, 2)),
                    $column,
                    $column,
                ),
            );
        }
    }

    /**
     * @return array<string, array{string, list<string>, list<string>}>
     */
    public static function linkIdentifiers(): array
    {
        $root = dirname(__DIR__, 3) . '/ContentBlocks/ContentElements';
        $cases = [];
        foreach ((array)glob($root . '/*/config.yaml') as $config) {
            if (!is_string($config)) {
                continue;
            }
            $block = basename(dirname($config));
            $raw = (string)file_get_contents($config);

            preg_match_all('#identifier:\s*(\S+)\s*\n\s*type:\s*Link\b#', $raw, $matches);
            if ($matches[1] === []) {
                continue;
            }
            $prefix = 'tx_kernux_' . str_replace('-', '', $block) . '_';
            $columns = [];
            foreach ($matches[1] as $id) {
                $columns[] = $prefix . $id;
            }
            $cases[$block] = [
                $root . '/' . $block . '/templates/frontend.html',
                $columns,
                array_values(array_unique($matches[1])),
            ];
        }
        self::assertNotSame([], $cases);

        return $cases;
    }

    /**
     * @param list<string> $columns
     * @param list<string> $identifiers
     */
    #[Test]
    #[DataProvider('linkIdentifiers')]
    public function noConditionTestsALinkFieldDirectly(string $file, array $columns, array $identifiers): void
    {
        $template = (string)file_get_contents($file);

        preg_match_all('#condition="([^"]*)"#', $template, $matches);
        $conditions = implode(' ', $matches[1]);
        preg_match_all("#condition:\s*'([^']*)'#", $template, $inline);
        $conditions .= ' ' . implode(' ', $inline[1]);

        $offenders = [];
        foreach ($columns as $column) {
            if (str_contains($conditions, '{data.' . $column . '}')) {
                $offenders[] = 'data.' . $column;
            }
        }
        // Collection children are reached as {row.<identifier>}, so the accessor is
        // matched on the identifier rather than on a column name.
        foreach ($identifiers as $id) {
            if (preg_match('#\{[A-Za-z0-9_]+\.' . preg_quote($id, '#') . '\}#', $conditions) === 1) {
                $offenders[] = '*.' . $id;
            }
        }

        self::assertSame(
            [],
            array_values(array_unique($offenders)),
            sprintf(
                'The %s block guards on a Link field. Content Blocks hands a Link over as a '
                . 'TypolinkParameter object, and an object is truthy in Fluid even when the '
                . 'link is empty - the guard therefore always passes and an empty <a href=""> '
                . 'is rendered. Resolve it first and guard on the string: '
                . '<f:variable name="uri"><f:uri.typolink parameter="{...}" /></f:variable>.',
                basename(dirname($file, 2)),
            ),
        );
    }

}
