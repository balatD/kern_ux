<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\ContentBlocks;

use BalatD\KernUx\Rendering\FluidSourceRenderer;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The Tabelle block, rendered rather than only parsed.
 *
 * Two things are pinned here that no other test can see. The first is the seam between
 * the core table wizard's storage format and the component: the block is the only place
 * where "a string with pipes in it" becomes rows, and a header flag becomes a `scope`.
 *
 * The second is that the block registers at all and hands the editor its fields in the
 * order KERN's field groups expect.
 */
final class TableBlockTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function data(array $overrides = []): array
    {
        return array_merge([
            'uid' => 42,
            'header' => 'Gebühren',
            'header_layout' => 2,
            'tx_kernux_table_rows' => "Leistung|Gebühr\nPersonalausweis|37,00 €\nReisepass|70,00 €",
            'tx_kernux_table_headerRow' => 1,
            'tx_kernux_table_headerColumn' => 0,
            'tx_kernux_table_footerRow' => 0,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function render(array $overrides = []): string
    {
        $template = dirname(__DIR__, 3) . '/ContentBlocks/ContentElements/table/templates/frontend.html';
        self::assertFileExists($template);

        $renderer = $this->get(FluidSourceRenderer::class);
        self::assertInstanceOf(FluidSourceRenderer::class, $renderer);

        return $renderer->render((string)file_get_contents($template), ['data' => self::data($overrides)]);
    }

    /**
     * The renderType itself is not asserted, and that is not an oversight.
     *
     * Content Blocks only builds its full column TCA when the real backend boots; in a
     * functional test every block's column comes back as the bare `['type' => 'text']`
     * skeleton, labels and all other settings included. A renderType assertion here
     * would fail for every Content Blocks version alike and say nothing about any of
     * them. What this environment can prove is that the block registered and that the
     * editor is offered the four fields in the intended order, which is what would
     * silently break if a field were renamed or the Basics slipped out of place.
     */
    #[Test]
    public function registersTheCtypeWithItsFieldsInOrder(): void
    {
        // Narrowed a step at a time rather than in one chain: $GLOBALS is mixed, and the
        // alternative is a PHPStan suppression for something a test can simply assert.
        $tca = $GLOBALS['TCA'] ?? null;
        self::assertIsArray($tca);
        $ttContent = $tca['tt_content'] ?? null;
        self::assertIsArray($ttContent);
        $types = $ttContent['types'] ?? null;
        self::assertIsArray($types);
        $table = $types['kernux_table'] ?? null;
        self::assertIsArray($table, 'The table block did not register a content type.');

        $showitem = $table['showitem'] ?? null;
        self::assertIsString($showitem);

        self::assertStringContainsString('tx_kernux_table_header_palette', $showitem);
        self::assertStringContainsString(
            'tx_kernux_table_rows,tx_kernux_table_headerRow,tx_kernux_table_headerColumn,tx_kernux_table_footerRow',
            $showitem,
        );
        // Spacing opens the Appearance tab, so anything after it would disappear into
        // that tab rather than staying on the main one.
        self::assertStringContainsString('tab.appearance', $showitem);
    }

    #[Test]
    public function turnsTheStoredPipesIntoAHeadedTable(): void
    {
        $rendered = $this->render();

        self::assertStringContainsString('<th class="kern-table__header" scope="col">Leistung</th>', $rendered);
        self::assertStringContainsString('<td class="kern-table__cell">Personalausweis</td>', $rendered);
        self::assertStringContainsString('<td class="kern-table__cell">37,00 €</td>', $rendered);
    }

    #[Test]
    public function marksTheFirstColumnAsRowHeadersWhenAsked(): void
    {
        $rendered = $this->render([
            'tx_kernux_table_headerColumn' => 1,
            'tx_kernux_table_rows' => "Tag|Vormittag\nMontag|8-12 Uhr",
        ]);

        self::assertStringContainsString('<th class="kern-table__header" scope="row">Montag</th>', $rendered);
    }

    #[Test]
    public function setsTheLastRowApartAsAFooter(): void
    {
        $rendered = $this->render(['tx_kernux_table_footerRow' => 1]);

        self::assertStringContainsString('<tfoot class="kern-table__footer">', $rendered);
        self::assertStringContainsString('<td class="kern-table__cell">Reisepass</td>', $rendered);
    }

    #[Test]
    public function namesTheTableAfterItsHeadingWithoutPrintingItTwice(): void
    {
        $rendered = $this->render();

        // The visible h2 already sits above the table, so repeating it as a visible
        // caption would say the same thing twice; hidden, it still gives the table the
        // accessible name a screen reader announces on entering it.
        self::assertStringContainsString('<caption class="kern-title kern-sr-only">Gebühren</caption>', $rendered);
        self::assertStringContainsString('<h2 class="kern-heading-medium">Gebühren</h2>', $rendered);
    }

    #[Test]
    public function rendersNoTableAtAllForARecordWithoutRows(): void
    {
        $rendered = $this->render(['tx_kernux_table_rows' => '']);

        self::assertStringNotContainsString('<table', $rendered);
        self::assertStringNotContainsString('kern-table-responsive', $rendered);
        // The section and its heading still render - an empty table is the only thing
        // suppressed, not the whole element.
        self::assertStringContainsString('Gebühren', $rendered);
    }

    #[Test]
    public function keepsTheSectionAnchorEveryOtherBlockAlsoEmits(): void
    {
        // The toc block links at exactly this id, so it has to look the same here.
        self::assertStringContainsString('id="kern-content-42"', $this->render());
    }
}
