<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Organism;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

final class HeroTest extends AbstractComponentTestCase
{
    private const PLACEHOLDER = 'EXT:kern_ux/Resources/Public/Images/styleguide-placeholder.svg';

    #[Test]
    public function defaultsToAnH1AtKernsLargestSize(): void
    {
        // A hero carries the page title, so both defaults have to fit that: the shipped
        // heading default of "medium" would render a page title at third-level size.
        $rendered = $this->renderSource('<k:organism.hero heading="Einen Hund anmelden" />');

        self::assertStringContainsString('<h1 class="kern-heading-x-large">Einen Hund anmelden</h1>', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function keepsTheKickerOutOfTheHeadingsAccessibleName(): void
    {
        // KERN's hgroup exists for exactly this: the kicker is associated with the
        // heading without becoming part of what a screen reader reads as its name.
        $rendered = $this->renderSource(
            '<k:organism.hero heading="Einen Hund anmelden" preline="Hunderegister" />',
        );

        self::assertStringContainsString('<hgroup class="kern-hgroup">', $rendered);
        self::assertStringContainsString('<p class="kern-preline">Hunderegister</p>', $rendered);
        self::assertStringNotContainsString('Hunderegister Einen Hund', $rendered);
    }

    #[Test]
    public function theNoteIsAnAlertWithoutAHeading(): void
    {
        // "New registrations cost 30 euros" is a statement, not a section. As a heading
        // it would show up in the document outline and in every heading list.
        $rendered = $this->renderSource(
            '<k:organism.hero heading="Anmelden" noteText="Neue Registrierungen kosten 30 Euro." />',
        );

        self::assertStringContainsString('class="kern-alert kern-alert--info"', $rendered);
        self::assertStringContainsString('<p class="kern-title">Neue Registrierungen kosten 30 Euro.</p>', $rendered);
        self::assertStringNotContainsString('<h2', $rendered);
    }

    #[Test]
    public function aNoteWithoutABodyLeavesTheBodyOut(): void
    {
        // KERN's alert body carries the white background and the rounded bottom corners,
        // so an empty one renders as a blank white strip under the note.
        $rendered = $this->renderSource('<k:organism.hero heading="A" noteText="B" />');

        self::assertStringNotContainsString('kern-alert__body', $rendered);
    }

    #[Test]
    public function readsTextBeforeTheImageEvenWhenTheImageIsShownFirst(): void
    {
        $rendered = $this->renderSource(
            '<k:organism.hero heading="Anmelden" mediaPosition="left" imageSrc="' . self::PLACEHOLDER . '" />',
        );

        $text = strpos($rendered, 'kernt3-hero__text');
        $media = strpos($rendered, 'kernt3-hero__media');
        self::assertIsInt($text);
        self::assertIsInt($media);
        self::assertLessThan($media, $text, 'The image must never precede the text in the source.');

        // The visual side is a grid order, and the class is what carries it.
        self::assertStringContainsString('kernt3-hero--media-left', $rendered);
    }

    #[Test]
    public function theHeroImageIsNotLazyLoaded(): void
    {
        // A hero is above the fold by definition; lazy loading delays exactly the image
        // the page is judged by.
        $rendered = $this->renderSource(
            '<k:organism.hero heading="Anmelden" imageSrc="' . self::PLACEHOLDER . '" />',
        );

        self::assertStringContainsString('loading="eager"', $rendered);
    }

    #[Test]
    public function positionNoneDropsTheImageEvenWhenOneIsGiven(): void
    {
        $rendered = $this->renderSource(
            '<k:organism.hero heading="Anmelden" mediaPosition="none" imageSrc="' . self::PLACEHOLDER . '" />',
        );

        self::assertStringNotContainsString('kernt3-hero__media', $rendered);
        self::assertStringContainsString('kernt3-hero--media-none', $rendered);
    }

    #[Test]
    public function fallsBackToTheNoImageLayoutWhenThereIsNoImage(): void
    {
        // Otherwise the text would keep half the grid and the other half would be empty.
        $rendered = $this->renderSource('<k:organism.hero heading="Anmelden" />');

        self::assertStringContainsString('kernt3-hero--media-none', $rendered);
    }

    #[Test]
    public function honoursAHiddenHeading(): void
    {
        // header_layout = 100 is core's "hide this heading". The block passes an empty
        // heading through for it, and that has to mean no hgroup rather than an empty one.
        $rendered = $this->renderSource('<k:organism.hero><k:atom.body>Nur Text</k:atom.body></k:organism.hero>');

        self::assertStringNotContainsString('hgroup', $rendered);
        self::assertStringContainsString('Nur Text', $rendered);
    }

    #[Test]
    public function rendersNothingWhenThereIsNothingToShow(): void
    {
        // An empty grid still eats its own gap, which shows up as an unexplained hole at
        // the top of the page.
        self::assertSame('', $this->renderSource('<k:organism.hero />'));
    }
}
