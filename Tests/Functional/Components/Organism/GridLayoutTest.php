<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Organism;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * The two grid organisms, which are a computed class list around a slot.
 *
 * The contract worth pinning is the responsive ladder rather than the wrapper: KERN's
 * grid is one column below md and two at md regardless of what the editor chose, and
 * only the lg step takes the requested count. Emitting the chosen count at every
 * breakpoint is the obvious mistake, and on a phone it produces four unreadable columns.
 */
final class GridLayoutTest extends AbstractComponentTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function markupProvider(): array
    {
        return [
            'card grid of two' => [
                '<k:organism.cardGrid columns="2"><p>x</p></k:organism.cardGrid>',
                '<div class="kern-grid kern-grid-cols-1 kern-grid-cols-2-md kern-grid-cols-2-lg kern-gap-lg">'
                . '<p>x</p></div>',
            ],
            'card grid of four still starts at one column' => [
                '<k:organism.cardGrid columns="4"><p>x</p></k:organism.cardGrid>',
                '<div class="kern-grid kern-grid-cols-1 kern-grid-cols-2-md kern-grid-cols-4-lg kern-gap-lg">'
                . '<p>x</p></div>',
            ],
            // A gallery is a list of images, so it is a ul: the count matters to a
            // screen reader, and a div would not announce one.
            'gallery is a list' => [
                '<k:organism.gallery columns="3"><li>x</li></k:organism.gallery>',
                '<ul class="kern-list kern-grid kern-grid-cols-1 kern-grid-cols-2-md kern-grid-cols-3-lg '
                . 'kern-gap-md"><li>x</li></ul>',
            ],
            'gallery of two' => [
                '<k:organism.gallery columns="2"><li>x</li></k:organism.gallery>',
                '<ul class="kern-list kern-grid kern-grid-cols-1 kern-grid-cols-2-md kern-grid-cols-2-lg '
                . 'kern-gap-md"><li>x</li></ul>',
            ],
        ];
    }

    #[Test]
    #[DataProvider('markupProvider')]
    public function rendersTheResponsiveLadder(string $source, string $expected): void
    {
        $rendered = $this->renderSource($source);

        self::assertSame($expected, $rendered);
        self::assertNoStrayWhitespace($rendered);
    }
}
