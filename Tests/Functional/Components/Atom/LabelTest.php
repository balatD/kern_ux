<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Atom;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

final class LabelTest extends AbstractComponentTestCase
{
    #[Test]
    public function rendersLabelWhenBoundToAField(): void
    {
        $rendered = $this->renderSource('<k:atom.label for="name">Name</k:atom.label>');

        self::assertSame('<label class="kern-label" for="name">Name</label>', $rendered);
    }

    #[Test]
    public function rendersLegendWhenNotBoundToAField(): void
    {
        $rendered = $this->renderSource('<k:atom.label>Adresse</k:atom.label>');

        self::assertSame('<legend class="kern-label">Adresse</legend>', $rendered);
    }

    #[Test]
    public function marksOptionalFieldsRatherThanRequiredOnes(): void
    {
        // KERN inverts the usual convention: the optional minority is marked, and
        // required fields carry no asterisk at all.
        $rendered = $this->renderSource(
            '<k:atom.label for="tel" optional="{true}">Telefon</k:atom.label>',
        );

        self::assertSame(
            '<label class="kern-label" for="tel">Telefon'
            . ' <span class="kern-label__optional">- Optional</span></label>',
            $rendered,
        );
    }

    #[Test]
    public function omitsTheOptionalMarkerForRequiredFields(): void
    {
        $rendered = $this->renderSource('<k:atom.label for="name">Name</k:atom.label>');

        self::assertStringNotContainsString('kern-label__optional', $rendered);
        self::assertStringNotContainsString('*', $rendered);
    }

    #[Test]
    public function supportsLargeLegendForComplexSections(): void
    {
        // KERN uses the large legend where it acts as the heading of a whole block,
        // e.g. an address or payment section.
        $rendered = $this->renderSource('<k:atom.label size="large">Adresse</k:atom.label>');

        self::assertSame('<legend class="kern-label kern-label--large">Adresse</legend>', $rendered);
    }
}
