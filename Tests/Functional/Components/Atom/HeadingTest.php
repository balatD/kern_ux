<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Atom;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Semantic level and visual size are separate arguments on purpose.
 *
 * KERN requires it: its Alert and Task List components ship a heading and tell
 * integrators to re-level it to fit the surrounding document while keeping the look
 * constant. Collapsing the two would make correct heading order impossible.
 */
final class HeadingTest extends AbstractComponentTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function appearanceProvider(): array
    {
        return [
            'display' => ['display', 'kern-heading-display'],
            'x-large' => ['x-large', 'kern-heading-x-large'],
            'large' => ['large', 'kern-heading-large'],
            'medium' => ['medium', 'kern-heading-medium'],
            'small' => ['small', 'kern-heading-small'],
            'title' => ['title', 'kern-title'],
            'title-large' => ['title-large', 'kern-title kern-title--large'],
            'title-small' => ['title-small', 'kern-title kern-title--small'],
        ];
    }

    #[Test]
    #[DataProvider('appearanceProvider')]
    public function mapsAppearanceToKernClass(string $appearance, string $expectedClass): void
    {
        $rendered = $this->renderSource(
            sprintf('<k:atom.heading appearance="%s">X</k:atom.heading>', $appearance),
        );

        self::assertSame(sprintf('<h2 class="%s">X</h2>', $expectedClass), $rendered);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function levelProvider(): array
    {
        return ['h1' => [1], 'h2' => [2], 'h3' => [3], 'h4' => [4], 'h5' => [5], 'h6' => [6]];
    }

    #[Test]
    #[DataProvider('levelProvider')]
    public function rendersRequestedSemanticLevel(int $level): void
    {
        $rendered = $this->renderSource(
            sprintf('<k:atom.heading level="%d" appearance="title">X</k:atom.heading>', $level),
        );

        self::assertSame(sprintf('<h%d class="kern-title">X</h%d>', $level, $level), $rendered);
    }

    #[Test]
    public function visualSizeIsIndependentOfLevel(): void
    {
        $rendered = $this->renderSource(
            '<k:atom.heading level="4" appearance="display">X</k:atom.heading>',
        );

        self::assertSame('<h4 class="kern-heading-display">X</h4>', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }
}
