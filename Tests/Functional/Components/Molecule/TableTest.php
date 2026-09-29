<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Molecule;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Pins the table markup.
 *
 * A data table is the one component where the accessibility guarantee is almost entirely
 * in attributes a sighted reader never sees: without `scope` a screen reader cannot say
 * which header belongs to a cell, and the table degrades into a list of unrelated values.
 * The same goes for the scroll wrapper's tabindex, without which a table wider than the
 * viewport cannot be reached by keyboard at all.
 *
 * This is a contract rather than an implementation detail for a second reason: the RTE
 * path in setup.typoscript wraps editor-drawn tables in the same markup, and if the two
 * drift apart the same content gets two different accessibility stories.
 */
final class TableTest extends AbstractComponentTestCase
{
    private const ROWS = "{0: {0: 'Personalausweis', 1: '37,00 €'}, 1: {0: 'Reisepass', 1: '70,00 €'}}";

    #[Test]
    public function wrapsTheTableInAKeyboardReachableScrollContainer(): void
    {
        $rendered = $this->renderSource('<k:molecule.table rows="' . self::ROWS . '" />');

        // Byte-for-byte what lib.kernUx.rte injects, so the component and the RTE cannot
        // tell two different stories about the same table.
        self::assertStringContainsString('<div class="kern-table-responsive" tabindex="0">', $rendered);
        self::assertStringContainsString('<table class="kern-table">', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function marksEveryHeaderCellWithTheAxisItLabels(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.table head="{0: \'Leistung\', 1: \'Gebühr\'}" rows="' . self::ROWS . '" />',
        );

        self::assertStringContainsString('<th class="kern-table__header" scope="col">Leistung</th>', $rendered);
        self::assertStringContainsString('<td class="kern-table__cell">Personalausweis</td>', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function makesOnlyTheFirstCellOfARowARowHeader(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.table rowHeaders="{true}" rows="' . self::ROWS . '" />',
        );

        self::assertStringContainsString('<th class="kern-table__header" scope="row">Personalausweis</th>', $rendered);
        self::assertStringContainsString('<td class="kern-table__cell">37,00 €</td>', $rendered);
        // Two row headers in one row would leave the second column labelled by nothing.
        self::assertSame(2, substr_count($rendered, 'scope="row"'));
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function keepsTheBodyInATbodyKernCanStripe(): void
    {
        $rendered = $this->renderSource('<k:molecule.table rows="' . self::ROWS . '" />');

        // kern-table--striped selects through .kern-table__body, so the class is load
        // bearing even though nothing here sets the modifier yet.
        self::assertStringContainsString('<tbody class="kern-table__body">', $rendered);
        self::assertStringContainsString('<tr class="kern-table__row">', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function rendersTheCaptionAsKernsTableTitle(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.table caption="Gebühren" rows="' . self::ROWS . '" />',
        );

        // KERN styles the caption through `.kern-table .kern-title`, which is what turns
        // it into a table-caption box rather than a stray line above the table.
        self::assertStringContainsString('<caption class="kern-title">Gebühren</caption>', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function canHideTheCaptionWithoutLosingTheAccessibleName(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.table caption="Gebühren" captionHidden="{true}" rows="' . self::ROWS . '" />',
        );

        self::assertStringContainsString('<caption class="kern-title kern-sr-only">Gebühren</caption>', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function omitsTheHeadAndFootRatherThanRenderingThemEmpty(): void
    {
        $rendered = $this->renderSource('<k:molecule.table rows="' . self::ROWS . '" />');

        self::assertStringNotContainsString('<thead>', $rendered);
        self::assertStringNotContainsString('<tfoot', $rendered);
        self::assertStringNotContainsString('<caption', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function putsTheFooterRowInATfoot(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.table rows="' . self::ROWS . '" foot="{0: \'Summe\', 1: \'107,00 €\'}" />',
        );

        self::assertStringContainsString('<tfoot class="kern-table__footer">', $rendered);
        self::assertStringContainsString('<td class="kern-table__cell">Summe</td>', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function escapesCellContentRatherThanTrustingIt(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.table rows="{0: {0: \'<script>alert(1)</script>\'}}" />',
        );

        // Cells carry editor input. nl2br in tag form escapes its children, so the only
        // markup a cell can produce is the <br> the table wizard stored.
        self::assertStringNotContainsString('<script>', $rendered);
        self::assertStringContainsString('&lt;script&gt;', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function keepsAnInCellLineBreak(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.table rows="{0: {0: \'Zeile eins' . "\n" . 'Zeile zwei\'}}" />',
        );

        self::assertStringContainsString('Zeile eins<br />', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }
}
