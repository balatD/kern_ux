<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Molecule;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The alt attribute is the whole point of this component's contract.
 *
 * It went missing once: the template assigned the file's alternative text over the top
 * of its own `alt` argument and then read a variable that was never set, so every image
 * on every page rendered alt="" - a WCAG 1.1.1 failure that no test noticed because no
 * test looked at the attribute. These do.
 */
final class FigureTest extends AbstractComponentTestCase
{
    #[Test]
    public function usesTheGivenAlternativeText(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.figure src="EXT:kern_ux/Resources/Public/Css/kernt3.css" alt="Rathaus mit Freitreppe" />',
        );

        self::assertStringContainsString('alt="Rathaus mit Freitreppe"', $rendered);
    }

    /**
     * An image nobody described is decorative, and alt="" is how that is stated. The
     * attribute has to be there either way: an img without alt is announced by its file
     * name instead of being skipped.
     */
    #[Test]
    public function emitsAnEmptyAltRatherThanNoAlt(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.figure src="EXT:kern_ux/Resources/Public/Css/kernt3.css" />',
        );

        self::assertStringContainsString('alt=""', $rendered);
        self::assertSame(1, substr_count($rendered, 'alt='), 'alt must appear exactly once');
    }

    /*
     * The remaining path - falling back to the file reference's own alternative text -
     * needs a real FAL object, because f:uri.image rejects anything else. That means
     * storage, file and reference fixtures, which this suite does not have yet; until it
     * does, that path is covered by auditing the rendered demo pages, where every image
     * carries a non-empty alt on both majors.
     */

    #[Test]
    public function rendersNothingWithoutASource(): void
    {
        self::assertSame('', trim($this->renderSource('<k:molecule.figure />')));
    }
}
