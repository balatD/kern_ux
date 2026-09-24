<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Organism;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The dialog's markup, which is where its accessibility actually lives.
 *
 * Everything behavioural - the focus trap, Escape, focus return - comes from the
 * browser's own showModal(), so none of it is this component's to get right. What is
 * this component's job is the part showModal() cannot supply: a name for the dialog, a
 * close control that is reachable and announced, and the data hook dialog.js needs to
 * find the element at all. Each of those fails silently: a dialog with a dangling
 * aria-labelledby is announced as just "dialog", and a close button whose only content
 * is a decorative icon is announced as nothing at all.
 */
final class DialogTest extends AbstractComponentTestCase
{
    #[Test]
    public function namesTheDialogByItsOwnHeading(): void
    {
        $rendered = $this->renderSource('<k:organism.dialog id="d1" title="Hinweis">Text</k:organism.dialog>');

        // The id is derived from the dialog's own id, so the two cannot disagree - but
        // only as long as both halves keep deriving it the same way.
        self::assertStringContainsString('aria-labelledby="d1-title"', $rendered);
        self::assertStringContainsString('id="d1-title">Hinweis</h2>', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function rendersANativeDialogElement(): void
    {
        $rendered = $this->renderSource('<k:organism.dialog id="d1" title="Hinweis">Text</k:organism.dialog>');

        // A div with role="dialog" would need a hand-written focus trap; the native
        // element brings one, along with the top layer and inertness of the page behind.
        self::assertStringContainsString('<dialog id="d1" class="kern-dialog"', $rendered);
        self::assertStringContainsString('<section class="kern-dialog__body">Text</section>', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function givesTheCloseButtonAnAccessibleNameBesideItsIcon(): void
    {
        $rendered = $this->renderSource('<k:organism.dialog id="d1" title="Hinweis">Text</k:organism.dialog>');

        // The icon is decorative, so without the visually hidden label the only way out
        // of the dialog would be announced as an unnamed button.
        self::assertStringContainsString('data-kernt3-dialog-close', $rendered);
        self::assertStringContainsString('<span class="kern-icon kern-icon--close" aria-hidden="true"></span>', $rendered);
        self::assertStringContainsString('<span class="kern-label kern-sr-only">Close dialog</span>', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function letsTheCloseLabelBeGiven(): void
    {
        $rendered = $this->renderSource(
            '<k:organism.dialog id="d1" title="Hinweis" closeLabel="Schließen">Text</k:organism.dialog>',
        );

        self::assertStringContainsString('<span class="kern-label kern-sr-only">Schließen</span>', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function keepsTheHeadingLevelSeparateFromItsAppearance(): void
    {
        $rendered = $this->renderSource(
            '<k:organism.dialog id="d1" title="Hinweis" level="3">Text</k:organism.dialog>',
        );

        // The dialog title always looks like a large KERN title; where it sits in the
        // document outline is a separate question the caller answers.
        self::assertStringContainsString('<h3 class="kern-title kern-title--large" id="d1-title">', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }
}
