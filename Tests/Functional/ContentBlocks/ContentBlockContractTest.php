<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\ContentBlocks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Every Content Block, rendered, with the KERN markup its accessibility hangs off.
 *
 * One class with a case per block rather than a class per block, because what a block
 * owns is narrow: the mapping from {data} onto the components. The section wrapper and
 * the heading are already pinned by SectionTest and ContentHeaderTest, so a per-block
 * class would mostly re-assert those. What is *not* covered anywhere else is whether a
 * given block reaches the right component with the right arguments - a numbered list
 * becoming an <ol>, a semantic divider losing its aria-hidden - and that is one
 * deliberate marker per block rather than a uniform assertion repeated 22 times.
 *
 * The fixtures leave rich-text and Link fields empty throughout; see the base class for
 * why that is the harness rather than a half-written fixture.
 *
 * The sitemap block is absent on purpose and its absence is asserted, so the exclusion
 * cannot quietly grow into somewhere to put a block that broke.
 */
final class ContentBlockContractTest extends AbstractContentBlockTestCase
{
    private const EXCLUDED = ['sitemap'];

    /** Blocks big enough to have earned a class of their own. */
    private const COVERED_ELSEWHERE = ['location', 'table'];

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: list<string>}>
     */
    public static function blockProvider(): array
    {
        $base = ['uid' => 42, 'header' => 'Überschrift', 'header_layout' => 2];

        return [
            // Panels stay empty: the template puts every panel body through
            // f:format.html unconditionally, so one populated panel hits the rich-text
            // wall. AccordionItemTest covers the markup a panel turns into.
            'accordion' => ['accordion', $base, ['kern-heading-medium']],

            'alert, warning variant' => ['alert', $base + [
                'tx_kernux_alert_variant' => 'warning',
            ], ['kern-alert kern-alert--warning']],

            'button-group' => ['button-group', $base + [
                'tx_kernux_buttongroup_buttons' => [['label' => 'Antrag starten', 'variant' => 'primary']],
            ], ['kern-btn-wrapper']],

            'card-grid' => ['card-grid', $base + [
                'tx_kernux_cardgrid_columns' => 3,
                'tx_kernux_cardgrid_cards' => [['title' => 'Personalausweis', 'text' => 'Kurz erklärt']],
            ], ['kern-grid', '<article class="kern-card']],

            'description-list' => ['description-list', $base + [
                'tx_kernux_descriptionlist_entries' => [['term' => 'Gebühr', 'definition' => '37,00 €']],
            ], ['<dl class="kern-description-list"', 'kern-description-list-item__key']],

            'dialog' => ['dialog', $base + [
                'tx_kernux_dialog_triggerLabel' => 'Hinweis lesen',
                'tx_kernux_dialog_triggerVariant' => 'secondary',
            ], ['data-kernt3-dialog="kern-dialog-42"', 'class="kern-dialog" aria-labelledby="kern-dialog-42-title"']],

            // The field is named for what an editor decides, and it inverts: a semantic
            // divider is the one that is NOT hidden from assistive technology.
            'divider, semantic' => ['divider', ['uid' => 42, 'tx_kernux_divider_semantic' => 1],
                ['<hr class="kern-divider" />']],
            'divider, decorative' => ['divider', ['uid' => 42, 'tx_kernux_divider_semantic' => 0],
                ['kern-divider--decorative', 'aria-hidden="true"']],

            'downloads' => ['downloads', $base + [
                'tx_kernux_downloads_files' => [[
                    'publicUrl' => '/fileadmin/merkblatt.pdf',
                    'name' => 'merkblatt.pdf',
                    'title' => 'Merkblatt',
                    'extension' => 'pdf',
                    'size' => 1024,
                ]],
            ], ['kernt3-download-list', 'download', '(PDF, 1 KB)']],

            'gallery' => ['gallery', $base + ['tx_kernux_gallery_columns' => 3], ['kern-heading-medium']],

            'heading' => ['heading', $base + [
                'tx_kernux_heading_preline' => 'Dienstleistung',
                'tx_kernux_heading_subline' => 'Bearbeitungszeit etwa 5 Werktage',
            ], ['<hgroup class="kern-hgroup"', 'kern-preline', 'kern-subline']],

            'hero' => ['hero', $base + [
                'tx_kernux_hero_preline' => 'Willkommen',
                'tx_kernux_hero_noteText' => 'Neue Gebühren ab Januar',
                'tx_kernux_hero_noteVariant' => 'info',
            ], ['kernt3-hero', 'kern-alert--info']],

            'image' => ['image', $base + ['tx_kernux_image_caption' => 'Das Rathaus'], ['kern-heading-medium']],

            'list, numbered' => ['list', $base + [
                'tx_kernux_list_variant' => 'number',
                'tx_kernux_list_items' => [['text' => 'Antrag ausfüllen', 'link' => '']],
            ], ['<ol class="kern-list kern-list--number">', '<li>Antrag ausfüllen</li>']],

            'media, audio with captions' => ['media', $base + [
                'tx_kernux_media_audio' => 1,
                'tx_kernux_media_file' => [['publicUrl' => '/fileadmin/rede.mp3']],
                'tx_kernux_media_captions' => [['publicUrl' => '/fileadmin/rede.vtt']],
            ], ['<audio class="kernt3-media__player" controls', '<track kind="captions"', 'default']],

            'progress' => ['progress', [
                'uid' => 42,
                'tx_kernux_progress_label' => 'Schritt 2 von 5',
                'tx_kernux_progress_value' => 2,
                'tx_kernux_progress_max' => 5,
            ], ['<div class="kern-progress">', '<progress id="kern-progress-42" value="2" max="5">']],

            'quicklinks' => ['quicklinks', $base + [
                'tx_kernux_quicklinks_columns' => 4,
                'tx_kernux_quicklinks_tiles' => [['title' => 'Termin buchen', 'icon' => 'calendar', 'text' => 'Online']],
            ], ['kern-grid', '<article class="kern-card']],

            'service' => ['service', $base + [
                'tx_kernux_service_requirements' => [['text' => 'Wohnsitz in der Stadt']],
            ], ['kern-heading-medium', 'Wohnsitz in der Stadt']],

            'task-list' => ['task-list', $base + [
                'tx_kernux_tasklist_numbered' => 1,
                'tx_kernux_tasklist_steps' => [
                    ['title' => 'Daten erfassen', 'status' => 'Erledigt', 'statusVariant' => 'success'],
                ],
            ], ['kern-task-list', '<ol', 'kern-badge--success']],

            'text' => ['text', $base, ['<div id="kern-content-42" class="kernt3-content">']],

            'text-media' => ['text-media', $base + [
                'tx_kernux_textmedia_media_position' => 'right',
            ], ['kern-heading-medium']],

            'toc' => ['toc', $base + ['tx_kernux_toc_label' => 'Auf dieser Seite'], ['kern-heading-medium']],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $markers
     */
    #[Test]
    #[DataProvider('blockProvider')]
    public function rendersTheKernMarkupItsAccessibilityHangsOff(string $block, array $data, array $markers): void
    {
        $rendered = $this->renderBlock($block, $data);

        foreach ($markers as $marker) {
            self::assertStringContainsString($marker, $rendered, "The {$block} block did not emit {$marker}.");
        }
        self::assertNoStrayWhitespace($rendered);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $markers
     */
    #[Test]
    #[DataProvider('blockProvider')]
    public function emitsNoEmptyWrapperForARecordAnEditorLeftBlank(string $block, array $data, array $markers): void
    {
        // An editor who places an element and saves it without filling anything in must
        // not get a bare <ul>, an empty <dl> or a link to nowhere on the live page.
        $blank = ['uid' => 1, 'header' => 'Nur Überschrift', 'header_layout' => 2];
        if ($block === 'progress') {
            // value and max are typed int on the component, so "blank" for this block is
            // the zero record the database actually holds, not a missing key.
            $blank += ['tx_kernux_progress_value' => 0, 'tx_kernux_progress_max' => 0];
        }

        $rendered = $this->renderBlock($block, $blank);

        self::assertStringNotContainsString('<ul></ul>', $rendered);
        self::assertStringNotContainsString('<ol></ol>', $rendered);
        self::assertStringNotContainsString('<dl></dl>', $rendered);
        self::assertStringNotContainsString('href=""', $rendered);
        self::assertStringNotContainsString('src=""', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function theOnlyBlockThatCannotRenderHeadlessIsTheSitemap(): void
    {
        $root = dirname(__DIR__, 3) . '/ContentBlocks/ContentElements';
        $onDisk = array_values(array_filter(
            scandir($root) ?: [],
            static fn(string $entry): bool => is_file($root . '/' . $entry . '/templates/frontend.html'),
        ));

        $covered = array_map(static fn(array $case): string => $case[0], array_values(self::blockProvider()));
        $missing = array_values(array_diff($onDisk, $covered, self::EXCLUDED, self::COVERED_ELSEWHERE));

        self::assertSame([], $missing, 'These blocks have no contract case: ' . implode(', ', $missing));

        // Asserted in both directions: the exclusion has to keep earning its place, and
        // the reason has to stay true - the sitemap is one f:cObject, which needs a
        // frontend request no functional test provides.
        foreach (self::EXCLUDED as $block) {
            self::assertStringContainsString(
                'f:cObject',
                (string)file_get_contents($root . '/' . $block . '/templates/frontend.html'),
                "The {$block} block is excluded but no longer renders through f:cObject.",
            );
        }
    }
}
