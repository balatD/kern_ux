<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Organism;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

final class FooterTest extends AbstractComponentTestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function content(): array
    {
        return [
            'columns' => [
                [
                    'title' => 'Verwaltung',
                    'children' => [['title' => 'Öffnungszeiten', 'link' => '/oeffnungszeiten']],
                ],
            ],
            'meta' => [
                ['title' => 'Impressum', 'link' => '/impressum'],
                ['title' => 'Datenschutz', 'link' => '/datenschutz'],
                ['title' => 'Barrierefreiheit', 'link' => '/barrierefreiheit'],
            ],
        ];
    }

    #[Test]
    public function rendersALabelledFooterLandmark(): void
    {
        $rendered = $this->renderSource(
            '<k:organism.footer columns="{columns}" metaNavigation="{meta}" />',
            self::content(),
        );

        self::assertStringContainsString('<footer class="kernt3-footer" aria-label="Footer">', $rendered);
    }

    #[Test]
    public function keepsItsInlinePaddingDespiteKernsGridContainerRule(): void
    {
        // KERN zeroes a kern-container's padding as soon as the container holds a
        // kern-grid - and the link columns are one. From 576px up the container's own
        // max-width still leaves an inset, so the footer looked right on a desktop and
        // sat flush against both edges of a phone. kernt3-footer__inner is what puts the
        // padding back, and it has to be on the same element as kern-container to tie
        // KERN's :has() rule on specificity.
        $rendered = $this->renderSource(
            '<k:organism.footer columns="{columns}" />',
            self::content(),
        );

        self::assertStringContainsString('<div class="kern-container kernt3-footer__inner">', $rendered);
        self::assertStringContainsString('class="kern-grid', $rendered);
    }

    #[Test]
    public function labelsEachLinkColumnAsItsOwnNavLandmark(): void
    {
        $rendered = $this->renderSource(
            '<k:organism.footer columns="{columns}" />',
            self::content(),
        );

        self::assertStringContainsString('<nav aria-label="Verwaltung">', $rendered);
    }

    #[Test]
    public function columnHeadingsAreRealHeadings(): void
    {
        // A footer column title has to sit in the heading outline, not be a styled div.
        $rendered = $this->renderSource(
            '<k:organism.footer columns="{columns}" />',
            self::content(),
        );

        self::assertStringContainsString('<h2 class="kern-title kern-title--small">Verwaltung</h2>', $rendered);
    }

    #[Test]
    public function keepsLegalLinksInTheirOwnLandmark(): void
    {
        // Impressum, Datenschutz and Barrierefreiheitserklärung are legally required
        // on public sector sites and should be findable as a group.
        $rendered = $this->renderSource(
            '<k:organism.footer metaNavigation="{meta}" />',
            self::content(),
        );

        self::assertStringContainsString('aria-label="Legal information"', $rendered);
        self::assertStringContainsString('Barrierefreiheit', $rendered);
    }

    #[Test]
    public function rendersNothingAtAllWhenEmpty(): void
    {
        // An empty footer landmark is announced as a region with no content, which is
        // worse than having no landmark.
        self::assertSame('', $this->renderSource('<k:organism.footer />'));
    }
}
