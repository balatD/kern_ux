<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\ContentBlocks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * One rule, asserted once, for the eight blocks that each implement it separately.
 *
 * `header_layout` is core's field and its vocabulary is unusual: 0 means "use whatever
 * the block thinks is right", 1 to 5 force that level, and 100 means "no heading" - not
 * a visually hidden one, an absent element. Eight block templates carry their own copy
 * of `{data.header_layout} > 0 && < 6`, and a block whose copy drifts produces a
 * document outline that is wrong in a way nothing else in the suite would notice: the
 * page still renders, the heading still appears, it just sits at the wrong depth.
 *
 * The 100 case has a second half. A block that hides its own heading promotes whatever
 * it contains one level up, because those children were a level below a heading that no
 * longer exists - and a gap in the outline is exactly what a screen reader's heading
 * navigation trips over.
 */
final class HeadingLevelTest extends AbstractContentBlockTestCase
{
    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: int, 3: string}>
     */
    public static function headingProvider(): array
    {
        $cards = ['tx_kernux_cardgrid_cards' => [['title' => 'Karte']]];
        $tiles = ['tx_kernux_quicklinks_tiles' => [['title' => 'Kachel']]];
        $steps = ['tx_kernux_tasklist_steps' => [['title' => 'Schritt']]];

        return [
            // Default: every block but the hero opens at h2, because a page has exactly
            // one h1 and it belongs to the page, not to a content element.
            'alert defaults to h2' => ['alert', [], 0, '<h2'],
            'heading defaults to h2' => ['heading', [], 0, '<h2'],
            'task-list defaults to h2' => ['task-list', $steps, 0, '<h2'],
            'card-grid defaults to h2' => ['card-grid', $cards, 0, '<h2'],
            'dialog defaults to h2' => ['dialog', ['tx_kernux_dialog_triggerLabel' => 'Auf'], 0, '<h2'],
            'service defaults to h2' => ['service', [], 0, '<h2'],
            // The hero is the page opener, so it is the one block that claims the h1.
            'hero defaults to h1' => ['hero', [], 0, '<h1'],

            'alert forced to h3' => ['alert', [], 3, '<h3'],
            'card-grid forced to h4' => ['card-grid', $cards, 4, '<h4'],
            'hero forced to h3' => ['hero', [], 3, '<h3'],
            'task-list forced to h5' => ['task-list', $steps, 5, '<h5'],
            'quicklinks forced to h3' => ['quicklinks', $tiles, 3, '<h3'],
        ];
    }

    /**
     * @param array<string, mixed> $extra
     */
    #[Test]
    #[DataProvider('headingProvider')]
    public function putsTheBlockHeadingAtTheLevelTheEditorChose(
        string $block,
        array $extra,
        int $layout,
        string $expectedTag,
    ): void {
        $rendered = $this->renderBlock($block, [
            'uid' => 7,
            'header' => 'Überschrift',
            'header_layout' => $layout,
        ] + $extra);

        self::assertStringContainsString($expectedTag, $rendered);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function hiddenHeadingProvider(): array
    {
        return [
            'alert' => ['alert', []],
            'heading' => ['heading', []],
            'hero' => ['hero', []],
            'card-grid' => ['card-grid', ['tx_kernux_cardgrid_cards' => [['title' => 'Karte']]]],
            'quicklinks' => ['quicklinks', ['tx_kernux_quicklinks_tiles' => [['title' => 'Kachel']]]],
            'task-list' => ['task-list', ['tx_kernux_tasklist_steps' => [['title' => 'Schritt']]]],
            'service' => ['service', []],
            // The dialog is deliberately absent. Its heading is the dialog's accessible
            // name - aria-labelledby points straight at it - so honouring "hide the
            // header" there would leave the dialog unnamed, which is a worse outcome
            // than an outline entry the editor did not want.
        ];
    }

    /**
     * @param array<string, mixed> $extra
     */
    #[Test]
    #[DataProvider('hiddenHeadingProvider')]
    public function emitsNoHeadingElementAtAllWhenTheEditorHidThe(string $block, array $extra): void
    {
        $rendered = $this->renderBlock($block, [
            'uid' => 7,
            'header' => 'Unsichtbar',
            'header_layout' => 100,
        ] + $extra);

        // 100 is core's "hide the header". A visually hidden heading would still be in
        // the outline, which is the opposite of what the editor asked for.
        self::assertStringNotContainsString('>Unsichtbar<', $rendered);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: string, 3: string}>
     */
    public static function childLevelProvider(): array
    {
        return [
            'card titles' => [
                'card-grid',
                ['tx_kernux_cardgrid_cards' => [['title' => 'Karte']]],
                '<h3',
                '<h2',
            ],
            'quicklink tiles' => [
                'quicklinks',
                ['tx_kernux_quicklinks_tiles' => [['title' => 'Kachel']]],
                '<h3',
                '<h2',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $extra
     */
    #[Test]
    #[DataProvider('childLevelProvider')]
    public function promotesTheChildrenWhenTheBlockHidesItsOwnHeading(
        string $block,
        array $extra,
        string $withHeading,
        string $withoutHeading,
    ): void {
        $base = ['uid' => 7, 'header' => 'Überschrift'];

        self::assertStringContainsString(
            $withHeading,
            $this->renderBlock($block, $base + ['header_layout' => 2] + $extra),
        );
        // With the block heading gone the children move up, or the outline jumps from
        // h2 straight to h4 with nothing in between.
        self::assertStringContainsString(
            $withoutHeading,
            $this->renderBlock($block, $base + ['header_layout' => 100] + $extra),
        );
    }
}
