<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\ContentBlocks;

use BalatD\KernUx\Rendering\FluidSourceRenderer;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The Standort block, rendered rather than only parsed.
 *
 * TemplateSyntaxTest deliberately stops at parsing, because several blocks need a
 * frontend request to render. That left a whole class of defect uncovered: this
 * template derived its tel: URI with an f:replace whose search array had five entries
 * and whose replace argument was a single empty string, which parses cleanly and
 * throws on render ("Count of \"search\" and \"replace\" arguments must be the same").
 * Nothing in the suite would have noticed until an editor placed the element.
 *
 * The fixture leaves bodytext, homepage and mapLink empty on purpose. Those three are
 * the only values that reach f:format.html and f:uri.typolink, both of which need a
 * frontend; with them unset the real template file renders end to end under a plain
 * functional test. Do not "complete" the fixture - it would trade this coverage for
 * none.
 */
final class LocationBlockTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    /**
     * @return array<string, mixed>
     */
    private static function data(): array
    {
        return [
            'uid' => 42,
            'header' => 'Bürgerbüro Mitte',
            'header_layout' => 2,
            'tx_kernux_location_noticeTitle' => 'Der Aufzug ist außer Betrieb',
            'tx_kernux_location_noticeVariant' => 'warning',
            'tx_kernux_location_authority' => 'Stadt Musterstadt',
            'tx_kernux_location_street' => 'Rathausplatz 1',
            'tx_kernux_location_postalCode' => '12345',
            'tx_kernux_location_city' => 'Musterstadt',
            'tx_kernux_location_phone' => '01234 567-0',
            'tx_kernux_location_email' => 'buergerbuero@musterstadt.example',
            'tx_kernux_location_accessibility' => [
                ['label' => 'Stufenloser Zugang über den Hintereingang'],
            ],
            'tx_kernux_location_openingHours' => [
                ['day' => 'Montag', 'opens' => '08:00', 'closes' => '15:00'],
                ['day' => 'Dienstag', 'opens' => '08:00', 'closes' => '18:00', 'note' => 'nur mit Termin'],
                ['day' => 'Mittwoch', 'closed' => 1],
            ],
            'tx_kernux_location_transport' => [
                ['mode' => 'Bus', 'lines' => 'Linien 1, 5 und 12, Haltestelle Rathaus'],
            ],
            'tx_kernux_location_payment' => [['label' => 'Girocard']],
        ];
    }

    private function render(): string
    {
        $template = dirname(__DIR__, 3) . '/ContentBlocks/ContentElements/location/templates/frontend.html';
        self::assertFileExists($template);

        $renderer = $this->get(FluidSourceRenderer::class);
        self::assertInstanceOf(FluidSourceRenderer::class, $renderer);

        return $renderer->render((string)file_get_contents($template), ['data' => self::data()]);
    }

    #[Test]
    public function derivesADiallableTelUriFromTheWrittenPhoneNumber(): void
    {
        self::assertStringContainsString(
            '<a class="kern-link" href="tel:012345670">01234 567-0</a>',
            $this->render(),
            'The href drops spaces, slashes, brackets and hyphens; the visible number keeps them.',
        );
    }

    #[Test]
    public function keepsEveryTextNodeOfTheAddressInsideAKernTypographyClass(): void
    {
        preg_match('#<address>.*</address>#s', $this->render(), $matches);
        $fragment = $matches[0] ?? null;
        self::assertIsString($fragment, 'The contact block must render an <address>.');

        $document = new \DOMDocument();
        self::assertTrue($document->loadXML('<?xml version="1.0" encoding="UTF-8"?>' . $fragment));
        $address = $document->documentElement;
        self::assertInstanceOf(\DOMElement::class, $address);

        // Only direct children matter: a bare text node here is what the browser's own
        // `address { font-style: italic }` would reach. Anything nested is already
        // inside a KERN class that declares font-style: normal.
        foreach ($address->childNodes as $child) {
            if (!$child instanceof \DOMText) {
                continue;
            }
            self::assertSame(
                '',
                trim($child->wholeText),
                'A bare text node sits directly in the <address>. KERN never styles the '
                . 'element, so that text renders italic. Labels belong inside the '
                . 'k:atom.body slot, not beside it.',
            );
        }

        self::assertGreaterThan(0, $address->getElementsByTagName('p')->length);
    }

    #[Test]
    public function putsTheHeadingOutsideTheAddressElement(): void
    {
        preg_match('#<address>(.*)</address>#s', $this->render(), $matches);
        $inside = $matches[1] ?? null;
        self::assertIsString($inside, 'The contact block must render an <address>.');
        self::assertStringNotContainsString('<h', $inside, 'HTML forbids headings inside <address>.');
    }

    #[Test]
    public function rendersOpeningHoursAsMachineReadableTimeElements(): void
    {
        $rendered = $this->render();

        self::assertStringContainsString('<time datetime="08:00">08:00</time>', $rendered);
        self::assertStringContainsString('<time datetime="15:00">15:00</time>', $rendered);
        self::assertStringNotContainsString('kern-description-list--col', $rendered);
    }

    #[Test]
    public function namesEveryAccessibilityFeatureInWords(): void
    {
        $rendered = $this->render();

        self::assertStringContainsString('<li>Stufenloser Zugang über den Hintereingang</li>', $rendered);
    }

    #[Test]
    public function embedsNoMapAndRequestsNothingFromAThirdParty(): void
    {
        $rendered = $this->render();

        self::assertStringNotContainsString('<iframe', $rendered);
        self::assertStringNotContainsString('<script', $rendered);
    }

    #[Test]
    public function omitsEverySectionWhoseFieldsAreEmpty(): void
    {
        $renderer = $this->get(FluidSourceRenderer::class);
        self::assertInstanceOf(FluidSourceRenderer::class, $renderer);
        $template = dirname(__DIR__, 3) . '/ContentBlocks/ContentElements/location/templates/frontend.html';

        $rendered = $renderer->render(
            (string)file_get_contents($template),
            ['data' => ['uid' => 1, 'header' => 'Leer', 'header_layout' => 2]],
        );

        self::assertStringNotContainsString('<address>', $rendered);
        self::assertStringNotContainsString('<dl', $rendered);
        self::assertStringNotContainsString('<ul', $rendered);
        self::assertStringNotContainsString('kern-alert', $rendered);
    }

}
