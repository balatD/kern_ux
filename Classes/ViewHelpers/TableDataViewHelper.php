<?php

declare(strict_types=1);

namespace BalatD\KernUx\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Turns the core table wizard's stored text into head, body and foot rows.
 *
 * The wizard stores one row per line with cells separated by a pipe, and replaces a
 * newline inside a cell with `<br>`. Fluid can neither split a string on a delimiter nor
 * take the first and last element off a list without arithmetic, so both jobs live here
 * rather than half here and half in a template.
 *
 * Not CsvUtility::csvToArray(): fgetcsv() insists on a single-character enclosure, the
 * wizard writes none, and passing `"` anyway would eat the quotes off a cell like
 * "Zitat". A plain explode is what the wizard's own writeTableSyntaxToTextarea()
 * produces, so it is what this reads.
 *
 * Rows are padded to the widest, which core does too: a short row would otherwise shift
 * every following cell one column left and put a `<th scope="col">` over the wrong data.
 */
final class TableDataViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('value', 'string', 'The stored table, one row per line, cells separated by a pipe.', true);
        $this->registerArgument('headerRow', 'bool', 'Peel the first row off as the table head.', false, false);
        $this->registerArgument('footerRow', 'bool', 'Peel the last row off as the table foot.', false, false);
    }

    /**
     * @return array{head: list<string>, rows: list<list<string>>, foot: list<string>}
     */
    public function render(): array
    {
        $value = $this->arguments['value'] ?? null;
        $rows = is_string($value) ? self::parse($value) : [];

        $head = [];
        if ($rows !== [] && ($this->arguments['headerRow'] ?? false)) {
            $head = array_shift($rows);
        }

        $foot = [];
        if ($rows !== [] && ($this->arguments['footerRow'] ?? false)) {
            $foot = array_pop($rows);
        }

        // array_shift reindexes and array_pop takes off the end, so $rows is still a list.
        return ['head' => $head, 'rows' => $rows, 'foot' => $foot];
    }

    /**
     * @return list<list<string>>
     */
    private static function parse(string $value): array
    {
        $rows = [];
        $width = 0;

        foreach (preg_split('/\r\n|\n|\r/', $value) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = array_map(
                static fn(string $cell): string => (string)preg_replace('|<br\s*/?>|i', "\n", $cell),
                explode('|', $line),
            );

            $width = max($width, count($cells));
            $rows[] = $cells;
        }

        return array_map(
            static fn(array $cells): array => array_pad($cells, $width, ''),
            $rows,
        );
    }
}
