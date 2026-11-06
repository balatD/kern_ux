<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Unit\ViewHelpers;

use BalatD\KernUx\ViewHelpers\TableDataViewHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Pins how the core table wizard's storage format is read back.
 *
 * The format is not this extension's to choose - the wizard writes it - so these cases
 * are a record of what it actually produces: a pipe between cells, `<br>` for a newline
 * inside one, and no guarantee that every row has the same number of cells.
 */
final class TableDataViewHelperTest extends UnitTestCase
{
    /**
     * @return array<string, array{0: array<string, mixed>, 1: array<string, mixed>}>
     */
    public static function tableProvider(): array
    {
        return [
            'body only' => [
                ['value' => "a|b\nc|d"],
                ['head' => [], 'rows' => [['a', 'b'], ['c', 'd']], 'foot' => []],
            ],
            'header row peeled off' => [
                ['value' => "Jahr|Betrag\n2024|100", 'headerRow' => true],
                ['head' => ['Jahr', 'Betrag'], 'rows' => [['2024', '100']], 'foot' => []],
            ],
            'footer row peeled off' => [
                ['value' => "2024|100\nSumme|100", 'footerRow' => true],
                ['head' => [], 'rows' => [['2024', '100']], 'foot' => ['Summe', '100']],
            ],
            'header and footer together' => [
                ['value' => "Jahr|Betrag\n2024|100\nSumme|100", 'headerRow' => true, 'footerRow' => true],
                ['head' => ['Jahr', 'Betrag'], 'rows' => [['2024', '100']], 'foot' => ['Summe', '100']],
            ],
            // A short row would otherwise pull every later cell one column left, which
            // puts the header of one column over the data of another.
            'ragged rows pad to the widest' => [
                ['value' => "a|b|c\nd"],
                ['head' => [], 'rows' => [['a', 'b', 'c'], ['d', '', '']], 'foot' => []],
            ],
            'the wizard writes an in-cell newline as a br' => [
                ['value' => 'a<br />b|c'],
                ['head' => [], 'rows' => [["a\nb", 'c']], 'foot' => []],
            ],
            'blank lines are not rows' => [
                ['value' => "a|b\n\nc|d\n"],
                ['head' => [], 'rows' => [['a', 'b'], ['c', 'd']], 'foot' => []],
            ],
            'empty input' => [
                ['value' => ''],
                ['head' => [], 'rows' => [], 'foot' => []],
            ],
            // One row that is both the head and the foot would leave the body empty and
            // the same cells rendered twice; peeling the head first means there is
            // nothing left for the foot to take.
            'a single row asked to be both head and foot' => [
                ['value' => 'only|row', 'headerRow' => true, 'footerRow' => true],
                ['head' => ['only', 'row'], 'rows' => [], 'foot' => []],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('tableProvider')]
    public function splitsTheStoredTableIntoHeadBodyAndFoot(array $arguments, array $expected): void
    {
        $subject = new TableDataViewHelper();
        $subject->setArguments($arguments);

        self::assertSame($expected, $subject->render());
    }
}
