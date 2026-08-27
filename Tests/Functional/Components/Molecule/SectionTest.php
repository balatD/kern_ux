<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Molecule;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * The wrapper every content block sits in.
 *
 * It also pins the translation between core's spacing vocabulary and KERN's, which is
 * the price of reusing space_before_class / space_after_class instead of inventing
 * own fields - and the reason existing content stays migratable.
 */
final class SectionTest extends AbstractComponentTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function spacingProvider(): array
    {
        return [
            // Not one of core's values: added by this extension so an editor can close
            // the default gap between two blocks, which core's own list cannot express.
            'none' => ['none', 'kern-mt-none'],
            'extra-small' => ['extra-small', 'kern-mt-xs'],
            'small' => ['small', 'kern-mt-sm'],
            'medium' => ['medium', 'kern-mt-md'],
            'large' => ['large', 'kern-mt-lg'],
            'extra-large' => ['extra-large', 'kern-mt-xl'],
        ];
    }

    #[Test]
    #[DataProvider('spacingProvider')]
    public function translatesCoreSpacingValuesToKernUtilities(string $coreValue, string $kernClass): void
    {
        $rendered = $this->renderSource(
            sprintf('<k:molecule.section spaceBefore="%s">X</k:molecule.section>', $coreValue),
        );

        self::assertSame(sprintf('<div class="kernt3-content %s">X</div>', $kernClass), $rendered);
    }

    #[Test]
    public function appliesBothDirections(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.section spaceBefore="large" spaceAfter="small">X</k:molecule.section>',
        );

        self::assertSame('<div class="kernt3-content kern-mt-lg kern-mb-sm">X</div>', $rendered);
    }

    #[Test]
    public function alwaysCarriesTheBaseClass(): void
    {
        // The base class is what the default vertical rhythm between blocks hangs off.
        // Without it a block with no editorial spacing had no class at all, and the
        // page had no rhythm unless every single block was spaced by hand.
        self::assertSame(
            '<div class="kernt3-content">X</div>',
            $this->renderSource('<k:molecule.section>X</k:molecule.section>'),
        );
        self::assertSame(
            '<div class="kernt3-content">X</div>',
            $this->renderSource('<k:molecule.section spaceBefore="">X</k:molecule.section>'),
        );
    }

    #[Test]
    public function ignoresUnknownSpacingValues(): void
    {
        // A value core does not define must not become a class that does not exist.
        $rendered = $this->renderSource('<k:molecule.section spaceBefore="huge">X</k:molecule.section>');

        self::assertSame('<div class="kernt3-content">X</div>', $rendered);
    }

    #[Test]
    public function rendersSemanticTagsOnRequest(): void
    {
        self::assertSame(
            '<section id="s1" class="kernt3-content">X</section>',
            $this->renderSource('<k:molecule.section tag="section" id="s1">X</k:molecule.section>'),
        );
        self::assertSame(
            '<article class="kernt3-content">X</article>',
            $this->renderSource('<k:molecule.section tag="article">X</k:molecule.section>'),
        );
    }
}
