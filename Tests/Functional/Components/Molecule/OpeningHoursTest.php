<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Molecule;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Opening hours of an authority location.
 *
 * The markup is a contract twice over. Once for the usual reason - it is KERN's
 * description list, and the accessibility guarantees hang off those exact classes.
 * And once for a reason specific to this component: the <time datetime> elements are
 * the only machine-readable form the extension emits anywhere, so a refactor that
 * quietly drops them would be invisible on screen and total for anything parsing the
 * page.
 *
 * The absence of kern-description-list--col is asserted rather than assumed. The
 * modifier reads as if it made columns and does the opposite, so a future edit
 * "fixing" its absence would silently stack every weekday on top of its hours.
 */
final class OpeningHoursTest extends AbstractComponentTestCase
{
    private const WEEKDAY = "{0: {day: 'Montag', opens: '08:00', closes: '16:00'}}";

    #[Test]
    public function emitsAMachineReadableTimeForBothEndsOfTheRange(): void
    {
        $rendered = $this->renderSource(
            sprintf('<k:molecule.openingHours days="%s" />', self::WEEKDAY),
        );

        self::assertStringContainsString('<time datetime="08:00">08:00</time>', $rendered);
        self::assertStringContainsString('<time datetime="16:00">16:00</time>', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function keepsTheDayAndItsHoursInOneDescriptionListItem(): void
    {
        $rendered = $this->renderSource(
            sprintf('<k:molecule.openingHours days="%s" />', self::WEEKDAY),
        );

        self::assertSame(
            '<dl class="kern-description-list">'
            . '<div class="kern-description-list-item">'
            . '<dt class="kern-description-list-item__key">Montag</dt>'
            . '<dd class="kern-description-list-item__value">'
            . '<time datetime="08:00">08:00</time>&#8211;<time datetime="16:00">16:00</time>'
            . '</dd>'
            . '</div>'
            . '</dl>',
            $rendered,
        );
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function doesNotStackTheDayOnTopOfItsHours(): void
    {
        $rendered = $this->renderSource(
            sprintf('<k:molecule.openingHours days="%s" />', self::WEEKDAY),
        );

        self::assertStringNotContainsString(
            'kern-description-list--col',
            $rendered,
            'KERN\'s --col modifier forces key and value to width: 100% and so keeps them '
            . 'stacked; without it the item becomes a 30/70 row at 768px, which is the '
            . 'reading a table of opening times needs.',
        );
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function rendersAClosedDayWithoutATimeElement(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.openingHours days="{0: {day: \'Mittwoch\', closed: 1}}" />',
        );

        self::assertStringContainsString(
            '<dd class="kern-description-list-item__value">closed</dd>',
            $rendered,
        );
        self::assertStringNotContainsString('<time', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function takesAnOverrideForTheClosedWording(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.openingHours closedLabel="Ruhetag" days="{0: {day: \'Mittwoch\', closed: 1}}" />',
        );

        self::assertStringContainsString('>Ruhetag</dd>', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function carriesTheQualifierInsideTheSameDefinition(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.openingHours days="{0: {day: \'Dienstag\', opens: \'08:00\', closes: \'18:00\', note: \'nur mit Termin\'}}" />',
        );

        self::assertStringContainsString(
            '<time datetime="18:00">18:00</time>'
            . '<p class="kern-body kern-body--small">nur mit Termin</p>'
            . '</dd>',
            $rendered,
            'The qualifier belongs in the same <dd> as the hours it qualifies; a sibling '
            . 'row would detach it from its day.',
        );
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function rendersOneItemPerDay(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.openingHours days="{0: {day: \'Montag\', opens: \'08:00\', closes: \'16:00\'}, '
            . '1: {day: \'Dienstag\', opens: \'08:00\', closes: \'18:00\'}}" />',
        );

        self::assertSame(2, substr_count($rendered, 'kern-description-list-item"'));
        self::assertNoStrayWhitespace($rendered);
    }
}
