<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Molecule;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * KERN's card, and the two municipal patterns built on it.
 *
 * The markup is a contract because the accessibility guarantees hang off the exact
 * classes - but the load-bearing test here is the first one. Card is the most reused
 * component in the extension, so its default output is what every existing page
 * depends on; an argument added for quick-access tiles is only safe as long as a card
 * that does not use it renders byte for byte what it rendered before. That assertion
 * is the proof, not a formality.
 *
 * The footer slot is what lets a dated teaser exist at all: preline is a string
 * argument and would escape a <time> element, so the slot is the only opening in the
 * signature that accepts markup.
 */
final class CardTest extends AbstractComponentTestCase
{
    #[Test]
    public function rendersNeitherIconNorFooterByDefault(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.card title="Ringstrasse wird saniert" level="4" link="#" '
            . 'preline="Verkehr" text="Ab Montag gesperrt." />',
        );

        self::assertSame(
            '<article class="kern-card kern-card--interactive">'
            . '<div class="kern-card__container">'
            . '<header class="kern-card__header">'
            . '<hgroup class="kern-hgroup">'
            . '<p class="kern-preline">Verkehr</p>'
            . '<h4 class="kern-title"><a class="kern-link kern-link--stretched" href="#">Ringstrasse wird saniert</a></h4>'
            . '</hgroup>'
            . '</header>'
            . '<section class="kern-card__body"><p class="kern-body">Ab Montag gesperrt.</p></section>'
            . '</div>'
            . '</article>',
            $rendered,
            'A card that sets neither icon nor footer must render exactly what it rendered '
            . 'before those were available, or every page already using Card changes.',
        );
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function placesTheIconInTheContainerRatherThanTheHeader(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.card icon="calendar-today" title="Termin buchen" level="4" link="#" />',
        );

        self::assertStringContainsString(
            '<div class="kern-card__container">'
            . '<span class="kern-icon kern-icon--calendar-today kern-icon--x-large" aria-hidden="true"></span>'
            . '<header class="kern-card__header">',
            $rendered,
            'kern-card__header is gap: 0, so an icon inside it sits flush against the '
            . 'title; the container carries KERN\'s own 16px gap.',
        );
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function announcesTheIconToNobodyBecauseTheTitleCarriesTheMeaning(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.card icon="download" title="Formulare" level="4" link="#" />',
        );

        self::assertStringContainsString('aria-hidden="true"', $rendered);
        self::assertStringNotContainsString('role="img"', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function rendersADateAsATimeElementInTheFooter(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.card title="Meldung" level="4">'
            . '<f:fragment name="footer"><k:atom.body variant="small" tag="span">'
            . '<time datetime="2026-03-14">14.03.2026</time></k:atom.body></f:fragment>'
            . '<f:fragment name="default"><k:atom.body>Kurztext.</k:atom.body></f:fragment>'
            . '</k:molecule.card>',
        );

        self::assertStringContainsString(
            '<footer class="kern-card__footer">'
            . '<span class="kern-body kern-body--small"><time datetime="2026-03-14">14.03.2026</time></span>'
            . '</footer>',
            $rendered,
            'The date needs the kern-body wrapper: kern-card__footer sets layout only, so '
            . 'a bare <time> falls back to the browser font next to Fira Sans.',
        );
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function omitsTheFooterWhenAPresentSlotRendersNothing(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.card title="Ohne Datum" level="4">'
            . '<f:fragment name="footer"><f:if condition=""><time>x</time></f:if></f:fragment>'
            . '<f:fragment name="default"><k:atom.body>Kurztext.</k:atom.body></f:fragment>'
            . '</k:molecule.card>',
        );

        self::assertStringNotContainsString(
            'kern-card__footer',
            $rendered,
            'A dateless card in a grid keeps an unconditional footer fragment whose body is '
            . 'guarded from the inside; an empty slot must therefore emit no footer at all.',
        );
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function stretchesTheLinkFromTheTitleSoItsAccessibleNameIsTheTitle(): void
    {
        $rendered = $this->renderSource('<k:molecule.card title="Koelnpass" level="4" link="/koelnpass" />');

        self::assertStringContainsString('kern-card--interactive', $rendered);
        self::assertStringContainsString(
            '<h4 class="kern-title"><a class="kern-link kern-link--stretched" href="/koelnpass">Koelnpass</a></h4>',
            $rendered,
        );
        self::assertNoStrayWhitespace($rendered);
    }
}
