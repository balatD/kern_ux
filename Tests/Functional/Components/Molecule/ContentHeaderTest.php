<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Molecule;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Bridges core's header fields to the heading component.
 *
 * The point of the bridge is that level and visual size stay separate: KERN requires
 * it so a block can sit anywhere in the document outline without changing its look.
 */
final class ContentHeaderTest extends AbstractComponentTestCase
{
    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function layoutProvider(): array
    {
        return [
            'default falls back to the block level' => ['0', 2],
            'h1' => ['1', 1],
            'h2' => ['2', 2],
            'h3' => ['3', 3],
            'h4' => ['4', 4],
            'h5' => ['5', 5],
        ];
    }

    #[Test]
    #[DataProvider('layoutProvider')]
    public function mapsCoreHeaderLayoutToSemanticLevel(string $layout, int $expectedLevel): void
    {
        $rendered = $this->renderSource(
            sprintf('<k:molecule.contentHeader header="T" layout="%s" />', $layout),
        );

        self::assertSame(
            sprintf('<h%d class="kern-heading-medium">T</h%d>', $expectedLevel, $expectedLevel),
            $rendered,
        );
    }

    #[Test]
    public function hiddenHeaderRendersNoElementAtAll(): void
    {
        // header_layout = 100 is core's "hide this heading". Hiding it visually would
        // leave it in the outline and mislead screen reader users.
        self::assertSame(
            '',
            $this->renderSource('<k:molecule.contentHeader header="Titel" layout="100" />'),
        );
    }

    #[Test]
    public function emptyHeaderRendersNothing(): void
    {
        self::assertSame('', $this->renderSource('<k:molecule.contentHeader />'));
    }

    #[Test]
    public function visualSizeIsIndependentOfLevel(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.contentHeader header="T" layout="4" appearance="display" />',
        );

        self::assertSame('<h4 class="kern-heading-display">T</h4>', $rendered);
    }

    #[Test]
    public function blockCanRaiseItsOwnDefaultLevel(): void
    {
        // A card inside a grid sits deeper in the outline than a page heading.
        $rendered = $this->renderSource(
            '<k:molecule.contentHeader header="T" defaultLevel="3" appearance="title" />',
        );

        self::assertSame('<h3 class="kern-title">T</h3>', $rendered);
    }

    #[Test]
    public function wrapsTheHeadingTextInALinkWhenGiven(): void
    {
        $rendered = $this->renderSource('<k:molecule.contentHeader header="T" link="/ziel" />');

        self::assertSame(
            '<h2 class="kern-heading-medium"><a class="kern-link" href="/ziel">T</a></h2>',
            $rendered,
        );
    }
}
