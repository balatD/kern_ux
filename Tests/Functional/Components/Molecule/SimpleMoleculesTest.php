<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Molecule;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Exact-markup contracts for the molecules that are one loop or one flag.
 *
 * Same shape as SimpleAtomsTest and for the same reason: where a component has no real
 * branching, the whole markup is the contract and asserting it in full says more than
 * picking out fragments of it.
 */
final class SimpleMoleculesTest extends AbstractComponentTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function markupProvider(): array
    {
        return [
            // The first button is the primary one and the rest are secondary, so a group
            // never shows two equally weighted calls to action.
            'button group weights the first item' => [
                '<k:molecule.buttonGroup items="{0: {label: \'Start\', link: \'/a\'},'
                . ' 1: {label: \'Zweit\', link: \'/b\'}}" />',
                '<div class="kern-btn-wrapper">'
                . '<a class="kern-btn kern-btn--primary" href="/a"><span class="kern-label">Start</span></a>'
                . '<a class="kern-btn kern-btn--secondary" href="/b"><span class="kern-label">Zweit</span></a></div>',
            ],
            'button group honours an explicit variant' => [
                '<k:molecule.buttonGroup items="{0: {label: \'Start\', link: \'/a\', variant: \'tertiary\'}}" />',
                '<div class="kern-btn-wrapper">'
                . '<a class="kern-btn kern-btn--tertiary" href="/a"><span class="kern-label">Start</span></a></div>',
            ],
            // The item div between dl and dt/dd is KERN's, not a wrapper of convenience:
            // its own CSS selects the pair through it.
            'description list' => [
                '<k:molecule.descriptionList items="{0: {key: \'Gebühr\', value: \'37,00 €\'}}" />',
                '<dl class="kern-description-list"><div class="kern-description-list-item">'
                . '<dt class="kern-description-list-item__key">Gebühr</dt>'
                . '<dd class="kern-description-list-item__value">37,00 €</dd></div></dl>',
            ],
            'description list stacked' => [
                '<k:molecule.descriptionList columns="{true}" items="{0: {key: \'Mo\', value: \'8-16\'}}" />',
                '<dl class="kern-description-list kern-description-list--col">'
                . '<div class="kern-description-list-item">'
                . '<dt class="kern-description-list-item__key">Mo</dt>'
                . '<dd class="kern-description-list-item__value">8-16</dd></div></dl>',
            ],
            // Format and size are inside the link text on purpose: a link has to be
            // understandable from its text alone (WCAG 2.4.4), and "Merkblatt" alone
            // does not say what is about to be downloaded.
            'download list names the format and size in the link' => [
                '<k:molecule.downloadList items="{0: {url: \'/a.pdf\', title: \'Merkblatt\','
                . ' format: \'PDF\', size: \'1 KB\'}}" />',
                '<ul class="kern-list kernt3-download-list"><li class="kernt3-download-list__item">'
                . '<a class="kern-link" href="/a.pdf" download>'
                . '<span class="kern-icon kern-icon--download" aria-hidden="true"></span>'
                . '<span>Merkblatt (PDF, 1 KB)</span></a></li></ul>',
            ],
            'download list adds an optional description' => [
                '<k:molecule.downloadList items="{0: {url: \'/a.pdf\', title: \'Merkblatt\','
                . ' format: \'PDF\', size: \'1 KB\', description: \'Stand 2026\'}}" />',
                '<ul class="kern-list kernt3-download-list"><li class="kernt3-download-list__item">'
                . '<a class="kern-link" href="/a.pdf" download>'
                . '<span class="kern-icon kern-icon--download" aria-hidden="true"></span>'
                . '<span>Merkblatt (PDF, 1 KB)</span></a>'
                . '<p class="kern-body kern-body--small">Stand 2026</p></li></ul>',
            ],
            // Preline and subline live inside the hgroup but outside the heading, so
            // neither becomes part of the heading's accessible name.
            'hgroup' => [
                '<k:molecule.hgroup heading="Titel" preline="Vor" subline="Unter" />',
                '<hgroup class="kern-hgroup"><p class="kern-preline">Vor</p>'
                . '<h2 class="kern-heading-large">Titel</h2>'
                . '<p class="kern-subline">Unter</p></hgroup>',
            ],
            'hgroup without the optional lines' => [
                '<k:molecule.hgroup heading="Titel" />',
                '<hgroup class="kern-hgroup"><h2 class="kern-heading-large">Titel</h2></hgroup>',
            ],
            'hgroup keeps level and appearance apart' => [
                '<k:molecule.hgroup heading="Titel" level="4" appearance="small" />',
                '<hgroup class="kern-hgroup"><h4 class="kern-heading-small">Titel</h4></hgroup>',
            ],
        ];
    }

    #[Test]
    #[DataProvider('markupProvider')]
    public function rendersTheKernMarkup(string $source, string $expected): void
    {
        $rendered = $this->renderSource($source);

        self::assertSame($expected, $rendered);
        self::assertNoStrayWhitespace($rendered);
    }
}
