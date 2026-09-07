<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Form;

use BalatD\KernUx\Rendering\FluidSourceRenderer;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Since TYPO3 14.2 the ext:form Checkbox `label` is edited through an RTE, so a consent
 * checkbox can link its privacy policy - and so the stored value arrives wrapped in
 * <p>. Core's Checkbox partial runs it through sanitize -> transform -> stripTags; this
 * extension replaces that partial, and when the chain went missing the paragraph tags
 * appeared on screen as literal text.
 *
 * Two halves, because either one alone would pass while the defect is present: the
 * first pins the chain into the templates that need it, the second pins what the chain
 * has to do to an RTE-shaped label. The chain is written out in both, so they cannot
 * drift apart unnoticed.
 */
final class RichTextLabelTest extends FunctionalTestCase
{
    /**
     * The exact chain core applies, whitespace included, so a diff against
     * EXT:form's own Checkbox.fluid.html stays a single grep.
     */
    private const SANITIZE_CHAIN = '-> f:sanitize.html() -> f:transform.html()'
        . " -> f:format.stripTags(allowedTags: '<a><br><i><strong>')";

    private const RTE_LABEL = '<p>Ich habe die <a href="/datenschutz">Datenschutzerklärung</a> gelesen</p>';

    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    #[Test]
    public function theCheckboxLabelIsSanitizedRatherThanEscaped(): void
    {
        self::assertStringContainsString(
            self::SANITIZE_CHAIN,
            self::partial('Checkbox.html'),
            'The checkbox <label> must apply core\'s sanitize chain - without it an '
            . 'RTE-edited label renders its <p> wrapper as visible text.',
        );
    }

    #[Test]
    public function theConfirmationPageSanitizesTheSameLabel(): void
    {
        // The confirmation step reuses the element label as the <dt>, so it inherits
        // the same RTE payload.
        self::assertStringContainsString(
            self::SANITIZE_CHAIN,
            self::partial('SummaryPage.html'),
        );
    }

    #[Test]
    public function theErrorSummaryStripsTheLabelCompletely(): void
    {
        // An allowlist keeping <a> would nest a link inside the summary's own link.
        self::assertStringContainsString(
            '{error.label -> f:format.stripTags()}',
            self::partial('Form/ErrorSummary.html'),
        );
    }

    #[Test]
    public function theChainUnwrapsTheParagraphAndKeepsTheLink(): void
    {
        $rendered = $this->render('{label ' . self::SANITIZE_CHAIN . '}');

        // The wrapper has to go: a <label> may not contain flow content, and an
        // unfiltered <p> is what showed up as text on the page.
        self::assertStringNotContainsString('<p>', $rendered);
        self::assertStringNotContainsString('&lt;p&gt;', $rendered);

        // The link is the whole reason the property became an RTE field.
        self::assertStringContainsString('<a href="/datenschutz">Datenschutzerklärung</a>', $rendered);

        self::assertSame('Ich habe die <a href="/datenschutz">Datenschutzerklärung</a> gelesen', trim($rendered));
    }

    #[Test]
    public function strippingBareLeavesReadableLinkText(): void
    {
        $rendered = $this->render('{label -> f:format.stripTags()}');

        self::assertSame('Ich habe die Datenschutzerklärung gelesen', trim($rendered));
    }

    private function render(string $expression): string
    {
        $renderer = $this->get(FluidSourceRenderer::class);
        self::assertInstanceOf(FluidSourceRenderer::class, $renderer);

        return $renderer->render($expression, ['label' => self::RTE_LABEL]);
    }

    private static function partial(string $relativePath): string
    {
        $file = dirname(__DIR__, 3) . '/Resources/Private/Partials/Form/Frontend/' . $relativePath;
        $contents = file_get_contents($file);
        self::assertIsString($contents, "Could not read {$file}.");

        return $contents;
    }
}
