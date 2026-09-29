<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Molecule;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The media player, whose accessibility is almost entirely in what it does not do.
 *
 * It uses the browser's own controls rather than custom ones, so keyboard operation,
 * the volume control and the scrubber are all native and none of them can be broken
 * here. What this component does own is the caption track - WCAG 1.2.2 for video and
 * the reason the transcript exists at all - and the distinction between an audio and a
 * video element, which decides whether a player occupies a video box on the page.
 */
final class MediaPlayerTest extends AbstractComponentTestCase
{
    #[Test]
    public function rendersAVideoElementWithNativeControls(): void
    {
        $rendered = $this->renderSource('<k:molecule.mediaPlayer src="/a.mp4" />');

        self::assertStringContainsString(
            '<video class="kernt3-media__player" controls preload="metadata" src="/a.mp4">',
            $rendered,
        );
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function rendersAnAudioElementWhenAsked(): void
    {
        $rendered = $this->renderSource('<k:molecule.mediaPlayer src="/a.mp3" audio="{true}" />');

        // An audio file in a video element gets a black box the size of a video, which
        // is why this is a switch rather than something inferred from the extension.
        self::assertStringContainsString('<audio class="kernt3-media__player" controls', $rendered);
        self::assertStringNotContainsString('<video', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function addsTheCaptionTrackOnlyWhenThereAreCaptions(): void
    {
        $withCaptions = $this->renderSource('<k:molecule.mediaPlayer src="/a.mp4" captionsSrc="/a.vtt" />');
        $without = $this->renderSource('<k:molecule.mediaPlayer src="/a.mp4" />');

        self::assertStringContainsString('<track kind="captions"', $withCaptions);
        // An empty track element would advertise captions the file does not have.
        self::assertStringNotContainsString('<track', $without);
        self::assertNoStrayWhitespace($withCaptions);
    }

    #[Test]
    public function turnsTheCaptionTrackOnByDefault(): void
    {
        $rendered = $this->renderSource('<k:molecule.mediaPlayer src="/a.mp4" captionsSrc="/a.vtt" />');

        // Captions that exist but are switched off help nobody who cannot turn them on.
        self::assertStringContainsString('src="/a.vtt" default />', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function labelsTheCaptionTrackSoAViewerCanTellTracksApart(): void
    {
        $rendered = $this->renderSource('<k:molecule.mediaPlayer src="/a.mp4" captionsSrc="/a.vtt" />');

        self::assertStringContainsString('srclang="en"', $rendered);
        self::assertStringContainsString('label="English"', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function letsTheCaptionLanguageAndLabelBeGiven(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.mediaPlayer src="/a.mp4" captionsSrc="/a.vtt" captionsLanguage="de" captionsLabel="Deutsch" />',
        );

        self::assertStringContainsString('srclang="de"', $rendered);
        self::assertStringContainsString('label="Deutsch"', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function putsTheTranscriptBehindADisclosureRatherThanBeforeTheMedia(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.mediaPlayer src="/a.mp4" transcript="Guten Tag." />',
        );

        // A transcript printed in full above the player pushes the player off the
        // screen; in an accordion it stays one keystroke away.
        self::assertStringContainsString('<details class="kern-accordion"', $rendered);
        self::assertStringContainsString('Guten Tag.', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }
}
