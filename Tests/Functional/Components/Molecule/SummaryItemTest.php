<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Molecule;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The summary item, which is a row on the "check your answers" page of a form.
 *
 * Its edit link is the reason this needs a contract. A summary page has one "Edit" per
 * section, and a screen reader user listing the links on the page hears "Edit, Edit,
 * Edit" with nothing to tell them apart - so each link carries the section name after
 * it, visually hidden. That suffix is invisible on screen and in a diff, and removing
 * it breaks WCAG 2.4.4 without changing how the page looks at all.
 */
final class SummaryItemTest extends AbstractComponentTestCase
{
    #[Test]
    public function distinguishesEachEditLinkByTheSectionItEdits(): void
    {
        $rendered = $this->renderSource('<k:molecule.summaryItem title="Daten" editLink="/edit" />');

        self::assertStringContainsString('<span class="kern-sr-only"> – Daten</span>', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function keepsTheNumberInsideTheTitleWrapper(): void
    {
        $rendered = $this->renderSource('<k:molecule.summaryItem title="Daten" number="2" />');

        // KERN moved the number inside the title wrapper in 2.7.0. Outside it, the
        // number sits in the item's own flex row and stops wrapping with the title.
        self::assertStringContainsString(
            '<div class="kern-summary__title-wrapper"><span class="kern-number">2</span>'
            . '<h3 class="kern-title">Daten</h3></div>',
            $rendered,
        );
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function omitsTheNumberWhenThereIsNone(): void
    {
        $rendered = $this->renderSource('<k:molecule.summaryItem title="Daten" />');

        self::assertStringNotContainsString('kern-number', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function omitsTheEditLinkWhenThereIsNothingToEdit(): void
    {
        $rendered = $this->renderSource('<k:molecule.summaryItem title="Daten" />');

        self::assertStringNotContainsString('<a', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function letsTheEditLabelBeGiven(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.summaryItem title="Daten" editLink="/edit" editLabel="Ändern" />',
        );

        self::assertStringContainsString('>Ändern<span class="kern-sr-only"> – Daten</span></a>', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function keepsTheLevelSeparateFromTheKernTitleLook(): void
    {
        $rendered = $this->renderSource('<k:molecule.summaryItem title="Daten" level="2" />');

        self::assertStringContainsString('<h2 class="kern-title">Daten</h2>', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }
}
