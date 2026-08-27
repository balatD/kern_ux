<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Organism;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The task list is one of the few components KERN ships whole, so the markup here is
 * not an integration decision - it is KERN's own reference from the 2.7.2 docs, and
 * these tests hold it to that. Each one names the rule it pins, because several of the
 * classes look decorative and are not.
 */
final class TaskListTest extends AbstractComponentTestCase
{
    private const STEP = '<k:molecule.taskListItem title="Angaben zur Person machen" number="1"'
        . ' link="/schritt-1" status="Erledigt" statusVariant="success" statusIcon="success"'
        . ' statusId="task-1-status" />';

    #[Test]
    public function wrapsStepAndStatusInTheTitleElement(): void
    {
        // kern-task-list__title is what wraps the status badge onto its own line below
        // 768px (flex-wrap: wrap, nowrap from there up). Without it both sit in the
        // item's own flex row, which never wraps, and the badge squeezes the step title
        // on a phone. It is the difference this component got wrong.
        $rendered = $this->renderSource('<k:organism.taskList>' . self::STEP . '</k:organism.taskList>');

        self::assertMatchesRegularExpression(
            '#<li class="kern-task-list__item">\s*<div class="kern-task-list__title">.*'
            . '<div class="kern-task-list__status".*</div>\s*</div>\s*</li>#s',
            $rendered,
        );
    }

    #[Test]
    public function keepsTheStepNumberInsideTheLink(): void
    {
        // KERN moved the number into the link in 2.7.0 - outside it, screen readers
        // skip it and a step is announced with no clue which step it is. The old shape
        // (number as a direct child of the item) still has a CSS fallback in KERN, but
        // it is deprecated and slated for removal.
        $rendered = $this->renderSource('<k:organism.taskList>' . self::STEP . '</k:organism.taskList>');

        self::assertStringContainsString(
            '<a class="kern-link kern-link--stretched kern-task-list__content-wrapper"'
            . ' href="/schritt-1" aria-describedby="task-1-status">'
            . '<span class="kern-number">1</span><span>Angaben zur Person machen</span></a>',
            $rendered,
        );
    }

    #[Test]
    public function describesTheStepByItsStatus(): void
    {
        // Without aria-describedby the badge is a stray phrase somewhere after the link
        // rather than part of what the step announces.
        $rendered = $this->renderSource('<k:organism.taskList>' . self::STEP . '</k:organism.taskList>');

        self::assertStringContainsString('aria-describedby="task-1-status"', $rendered);
        self::assertStringContainsString('<div class="kern-task-list__status" id="task-1-status">', $rendered);
    }

    #[Test]
    public function rendersAStepThatCannotBeStartedAsTextRatherThanADeadLink(): void
    {
        $rendered = $this->renderSource(
            '<k:organism.taskList><k:molecule.taskListItem title="Zusammenfassung" number="4"'
            . ' status="Noch nicht zu bearbeiten" statusId="task-4-status" /></k:organism.taskList>',
        );

        self::assertStringNotContainsString('<a ', $rendered);
        // A p, not a span: .kern-body carries block padding, which an inline box drops -
        // so a span left the unlinked step sitting higher than a linked one beside it.
        self::assertStringContainsString(
            '<div class="kern-task-list__content-wrapper">'
            . '<span class="kern-number">4</span><p class="kern-body">Zusammenfassung</p></div>',
            $rendered,
        );
    }

    #[Test]
    public function stretchesTheLinkAcrossTheWholeRowByDefault(): void
    {
        // kern-link--stretched is also what drives KERN's hover and active tint on the
        // item (:has(.kern-link--stretched:hover)), so dropping it costs the row both
        // its click target and any hover feedback.
        $rendered = $this->renderSource('<k:organism.taskList>' . self::STEP . '</k:organism.taskList>');

        self::assertStringContainsString('kern-link--stretched', $rendered);
    }

    #[Test]
    public function canDropTheStretchedLink(): void
    {
        // A stretched link swallows everything under it, so a row that ever gains a
        // second control needs a way out.
        $rendered = $this->renderSource(
            '<k:organism.taskList><k:molecule.taskListItem title="Schritt" link="/s"'
            . ' stretched="{false}" /></k:organism.taskList>',
        );

        self::assertStringNotContainsString('kern-link--stretched', $rendered);
        self::assertStringContainsString('class="kern-link kern-task-list__content-wrapper"', $rendered);
    }

    #[Test]
    public function headingCarriesTheBaseClassKernStylesItThrough(): void
    {
        // KERN styles the header heading through `.kern-task-list__header .kern-heading`,
        // a selector its own example misses by using only the size class.
        $rendered = $this->renderSource(
            '<k:organism.taskList title="Antrag auf Personalausweis" level="3" />',
        );

        self::assertStringContainsString('<div class="kern-task-list__header">', $rendered);
        self::assertStringContainsString('<h3 class="kern-heading-medium kern-heading">', $rendered);
    }

    #[Test]
    public function unnumberedListsAreUnorderedLists(): void
    {
        // Without step numbers the steps are a set, not a sequence - which is the
        // variant KERN documents as "Ohne Nummerierung".
        $rendered = $this->renderSource(
            '<k:organism.taskList numbered="{false}"><k:molecule.taskListItem title="Personalausweis"'
            . ' link="/a" /></k:organism.taskList>',
        );

        self::assertStringContainsString('<ul class="kern-task-list__list">', $rendered);
        self::assertStringNotContainsString('<ol', $rendered);
        self::assertStringNotContainsString('kern-number', $rendered);
    }

    #[Test]
    public function groupsSectionsWithKernsOwnGroupClass(): void
    {
        // A journey with several sections is one heading per list, not one list under
        // two headings. kern-task-list-group supplies the 32px between them; KERN ships
        // the class but no example for it, so it is easy to leave out - and without it
        // the next heading sits flush against the previous section.
        $rendered = $this->renderSource(
            '<k:organism.taskListGroup>'
            . '<k:organism.taskList title="Persönliche Daten">' . self::STEP . '</k:organism.taskList>'
            . '<k:organism.taskList title="Zusammenfassung">' . self::STEP . '</k:organism.taskList>'
            . '</k:organism.taskListGroup>',
        );

        self::assertStringStartsWith('<div class="kern-task-list-group">', $rendered);
        self::assertSame(2, substr_count($rendered, '<div class="kern-task-list">'));
        self::assertSame(2, substr_count($rendered, '<div class="kern-task-list__header">'));
    }

    #[Test]
    public function omitsTheHeaderWhenNoTitleIsGiven(): void
    {
        $rendered = $this->renderSource('<k:organism.taskList>' . self::STEP . '</k:organism.taskList>');

        self::assertStringNotContainsString('kern-task-list__header', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }
}
