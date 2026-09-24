<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\ContentBlocks;

use PHPUnit\Framework\Attributes\Test;

/**
 * Ids that are written twice, from two places, and have to agree.
 *
 * Each case here is the same defect class: one expression emits an id, a second emits a
 * reference to it, and nothing connects them but the fact that both call kux:uniqueId
 * with the same arguments. Change the prefix on one line and the markup still validates,
 * the page still renders, and the only symptom is that a dialog no longer opens or a
 * status is no longer announced with the step it belongs to.
 *
 * Grouped in one class because splitting them by block would hide what they have in
 * common, which is the thing worth testing.
 */
final class IdWiringTest extends AbstractContentBlockTestCase
{
    #[Test]
    public function pointsTheDialogTriggerAtTheDialogItOpens(): void
    {
        $rendered = $this->renderBlock('dialog', [
            'uid' => 42,
            'header' => 'Hinweis',
            'header_layout' => 2,
            'tx_kernux_dialog_triggerLabel' => 'Hinweis lesen',
            'tx_kernux_dialog_triggerVariant' => 'secondary',
        ]);

        $trigger = self::captureOf(
            '/data-kernt3-dialog="([^"]+)"/',
            $rendered,
            'The trigger carries no dialog reference, so dialog.js has nothing to open.',
        );
        $dialog = self::captureOf('/<dialog id="([^"]+)"/', $rendered, 'No dialog element was rendered.');

        self::assertSame($trigger, $dialog);
    }

    #[Test]
    public function namesTheDialogByItsOwnHeading(): void
    {
        $rendered = $this->renderBlock('dialog', [
            'uid' => 42,
            'header' => 'Hinweis',
            'header_layout' => 2,
            'tx_kernux_dialog_triggerLabel' => 'Hinweis lesen',
            'tx_kernux_dialog_triggerVariant' => 'secondary',
        ]);

        $labelledBy = self::captureOf(
            '/<dialog [^>]*aria-labelledby="([^"]+)"/',
            $rendered,
            'The dialog names nothing as its label.',
        );

        // A dialog whose aria-labelledby points at nothing is announced as just
        // "dialog", with no indication of what it is about.
        self::assertStringContainsString('id="' . $labelledBy . '"', $rendered);
    }

    /**
     * The id half only.
     *
     * The reference half - aria-describedby on the step - is emitted only on the linked
     * branch, because an unlinked step's title is a <p> with nothing focusable to
     * describe. Producing a linked step needs typolink and therefore a frontend, so that
     * half belongs to TaskListTest, which passes the href straight to the component.
     * What the block owns, and what this pins, is that every step gets an id of its own.
     */
    #[Test]
    public function givesEachTaskStatusAnIdOfItsOwn(): void
    {
        $rendered = $this->renderBlock('task-list', [
            'uid' => 42,
            'header' => 'Antrag',
            'header_layout' => 2,
            'tx_kernux_tasklist_numbered' => 1,
            'tx_kernux_tasklist_steps' => [
                ['title' => 'Daten erfassen', 'status' => 'Erledigt', 'statusVariant' => 'success'],
                ['title' => 'Nachweise', 'status' => 'Offen', 'statusVariant' => 'info'],
            ],
        ]);

        preg_match_all('/<div class="kern-task-list__status" id="([^"]+)"/', $rendered, $statuses);
        self::assertCount(2, $statuses[1], 'Each step must carry its own status id.');

        // Two steps sharing an id would make a linked step announce the other step's
        // status, and the duplicate ids would be invalid markup besides.
        self::assertNotSame($statuses[1][0], $statuses[1][1]);
        foreach ($statuses[1] as $id) {
            self::assertSame(1, substr_count($rendered, 'id="' . $id . '"'));
        }
    }

    /**
     * The one captured group, or a failure naming what was looked for.
     */
    private static function captureOf(string $pattern, string $subject, string $message): string
    {
        self::assertSame(1, preg_match($pattern, $subject, $matches), $message);
        self::assertArrayHasKey(1, $matches);

        return $matches[1];
    }

    #[Test]
    public function anchorsEveryBlockAtTheIdTheTableOfContentsLinksTo(): void
    {
        // The toc builds hrefs as #kern-content-<uid>. Every block emits that id from
        // its own kux:uniqueId call, so the two agree only by convention.
        foreach (['text', 'alert', 'heading', 'task-list'] as $block) {
            $rendered = $this->renderBlock($block, ['uid' => 99, 'header' => 'H', 'header_layout' => 2]);
            self::assertStringContainsString('id="kern-content-99"', $rendered, "The {$block} block lost its anchor.");
        }
    }
}
