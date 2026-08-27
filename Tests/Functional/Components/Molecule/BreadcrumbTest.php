<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Molecule;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * KERN ships no breadcrumb, so this markup is ours. It carries the kernt3- prefix so
 * a future KERN breadcrumb cannot collide with it.
 */
final class BreadcrumbTest extends AbstractComponentTestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function trail(): array
    {
        return [
            'items' => [
                ['title' => 'Start', 'link' => '/'],
                ['title' => 'Leistungen', 'link' => '/leistungen'],
                ['title' => 'Personalausweis', 'link' => '/leistungen/ausweis'],
            ],
        ];
    }

    #[Test]
    public function rendersALabelledNavLandmarkAroundAnOrderedList(): void
    {
        // Labelled because a page has several nav landmarks; ordered because the
        // trail has a direction.
        $rendered = $this->renderSource('<k:molecule.breadcrumb items="{items}" />', self::trail());

        self::assertStringContainsString('<nav class="kernt3-breadcrumb" aria-label="Breadcrumb">', $rendered);
        self::assertStringContainsString('<ol class="kern-list">', $rendered);
    }

    #[Test]
    public function marksTheLastItemAsCurrentAndDoesNotLinkIt(): void
    {
        $rendered = $this->renderSource('<k:molecule.breadcrumb items="{items}" />', self::trail());

        self::assertStringContainsString(
            '<span class="kernt3-breadcrumb__current" aria-current="page">Personalausweis</span>',
            $rendered,
        );
        self::assertStringNotContainsString('href="/leistungen/ausweis"', $rendered);
    }

    #[Test]
    public function linksEveryItemButTheLast(): void
    {
        $rendered = $this->renderSource('<k:molecule.breadcrumb items="{items}" />', self::trail());

        self::assertStringContainsString('href="/"', $rendered);
        self::assertStringContainsString('href="/leistungen"', $rendered);
    }

    #[Test]
    public function putsNoSeparatorCharacterInTheDom(): void
    {
        // Separators are drawn in CSS: a chevron in the markup gets announced between
        // every step by some screen readers.
        $rendered = $this->renderSource('<k:molecule.breadcrumb items="{items}" />', self::trail());

        foreach (['&gt;', '›', '/</', '&raquo;'] as $separator) {
            self::assertStringNotContainsString($separator, $rendered);
        }
    }

    #[Test]
    public function acceptsACustomLandmarkLabel(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.breadcrumb items="{items}" label="Sie sind hier" />',
            self::trail(),
        );

        self::assertStringContainsString('aria-label="Sie sind hier"', $rendered);
    }
}
