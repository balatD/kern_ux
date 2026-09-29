<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Molecule;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The accordion item, which is a native disclosure and must stay one.
 *
 * details/summary brings the expanded state, the keyboard operation and the screen
 * reader announcement with it, and browser find-in-page can now open a closed details
 * to reveal a match. A hand-built div with aria-expanded gets none of that, and is the
 * shape this component exists to avoid - so the element names are the contract here,
 * not an implementation detail.
 */
final class AccordionItemTest extends AbstractComponentTestCase
{
    #[Test]
    public function rendersANativeDisclosure(): void
    {
        $rendered = $this->renderSource('<k:molecule.accordionItem title="Frage">Antwort</k:molecule.accordionItem>');

        self::assertSame(
            '<details class="kern-accordion"><summary class="kern-accordion__header">'
            . '<span class="kern-title">Frage</span></summary>'
            . '<section class="kern-accordion__body">Antwort</section></details>',
            $rendered,
        );
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function writesNoAriaExpandedOfItsOwn(): void
    {
        $rendered = $this->renderSource('<k:molecule.accordionItem title="Frage">Antwort</k:molecule.accordionItem>');

        // summary carries the state natively. A hand-written aria-expanded goes stale
        // the moment the user toggles it, because nothing here updates it.
        self::assertStringNotContainsString('aria-expanded', $rendered);
    }

    #[Test]
    public function opensWhenAsked(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.accordionItem title="Frage" open="{true}">Antwort</k:molecule.accordionItem>',
        );

        self::assertStringContainsString('<details class="kern-accordion" open>', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function carriesAnIdWhenOneIsGiven(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.accordionItem title="Frage" id="a1">Antwort</k:molecule.accordionItem>',
        );

        self::assertStringContainsString('<details class="kern-accordion" id="a1">', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function keepsTheTitleInAKernTitleSpan(): void
    {
        $rendered = $this->renderSource('<k:molecule.accordionItem title="Frage">Antwort</k:molecule.accordionItem>');

        // KERN styles the summary text through `.kern-accordion__header .kern-title`, so
        // text placed straight into the summary inherits the surrounding context instead.
        self::assertStringContainsString('<span class="kern-title">Frage</span>', $rendered);
    }
}
