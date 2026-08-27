<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Organism;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The header follows KERN's documented grid *pattern* - so every class in it has to
 * be a KERN utility. These tests exist to keep it that way.
 */
final class HeaderTest extends AbstractComponentTestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function navigation(): array
    {
        return [
            'main' => [
                ['title' => 'Leistungen', 'link' => '/leistungen', 'current' => true],
                ['title' => 'Aktuelles', 'link' => '/aktuelles', 'current' => false],
            ],
            'help' => [
                ['title' => 'Leichte Sprache', 'link' => '/leichte-sprache'],
            ],
        ];
    }

    #[Test]
    public function labelsBothNavLandmarksDistinctly(): void
    {
        $rendered = $this->renderSource(
            '<k:organism.header mainNavigation="{main}" helpNavigation="{help}" />',
            self::navigation(),
        );

        self::assertStringContainsString('aria-label="Main navigation"', $rendered);
        self::assertStringContainsString('aria-label="Service navigation"', $rendered);
    }

    #[Test]
    public function marksTheCurrentPage(): void
    {
        $rendered = $this->renderSource(
            '<k:organism.header mainNavigation="{main}" />',
            self::navigation(),
        );

        self::assertStringContainsString('href="/leistungen" aria-current="page"', $rendered);
        self::assertStringContainsString('href="/aktuelles"', $rendered);
        self::assertSame(1, substr_count($rendered, 'aria-current="page"'));
    }

    #[Test]
    public function bothNavigationsShareOneCollapsiblePanelWithServiceLinksLast(): void
    {
        // The mobile menu is one panel, and document order is what puts the service
        // links under the main navigation in it. Keeping them in the top bar - which is
        // where they belong on desktop - put them beside the burger button on a phone,
        // and no amount of CSS moves an element out of a flex row it is not a child of.
        $rendered = $this->renderSource(
            '<k:organism.header mainNavigation="{main}" helpNavigation="{help}" />',
            self::navigation(),
        );

        self::assertMatchesRegularExpression(
            '#<div class="kernt3-header__panel">\s*<nav class="kernt3-header__main[^>]*>.*'
            . '<nav class="kernt3-header__service[^>]*>.*</div>#s',
            $rendered,
        );
    }

    #[Test]
    public function omitsEmptyNavigationsEntirely(): void
    {
        // An empty nav landmark is noise for screen reader users.
        $rendered = $this->renderSource('<k:organism.header siteTitle="Musterstadt" />');

        self::assertStringNotContainsString('<nav', $rendered);
    }

    #[Test]
    public function wiresTheMobileToggleForAssistiveTechnology(): void
    {
        $rendered = $this->renderSource('<k:organism.header siteTitle="Musterstadt" />');

        self::assertStringContainsString('data-kernt3-toggle', $rendered);
        self::assertStringContainsString('aria-expanded="false"', $rendered);
        self::assertStringContainsString(
            'aria-controls="kernt3-main-navigation kernt3-help-navigation"',
            $rendered,
        );
    }

    #[Test]
    public function iconOnlyTogglesKeepAnAccessibleName(): void
    {
        $rendered = $this->renderSource('<k:organism.header siteTitle="Musterstadt" />');

        self::assertStringContainsString('<span class="kern-label kern-sr-only">Show menu</span>', $rendered);
    }

    #[Test]
    public function omitsSearchAndItsToggleWhenNoSearchUrlIsGiven(): void
    {
        $rendered = $this->renderSource('<k:organism.header siteTitle="Musterstadt" />');

        self::assertStringNotContainsString('role="search"', $rendered);
        self::assertStringNotContainsString('kernt3-search-trigger', $rendered);
    }

    #[Test]
    public function searchFormIsLabelledWithoutAnInvalidAutocompleteToken(): void
    {
        $rendered = $this->renderSource(
            '<k:organism.header siteTitle="Musterstadt" searchUrl="/suche" />',
        );

        self::assertStringContainsString('role="search"', $rendered);
        self::assertStringContainsString('<label for="kernt3-search-input" class="kern-sr-only">', $rendered);
        // "search" is not a valid autofill token and WCAG 1.3.5 does not apply to a
        // site search box. KERN's own example gets this wrong; axe catches it.
        self::assertStringNotContainsString('autocomplete=', $rendered);
    }

    #[Test]
    public function fallsBackToTheSiteTitleWhenNoLogoIsConfigured(): void
    {
        // The Bildwortmarke may not be shipped, so a logo-less header must still work.
        $rendered = $this->renderSource('<k:organism.header siteTitle="Musterstadt" />');

        self::assertStringContainsString('Musterstadt', $rendered);
        self::assertStringNotContainsString('<img', $rendered);
    }

    #[Test]
    public function homeLinkHasAnAccessibleName(): void
    {
        $rendered = $this->renderSource('<k:organism.header siteTitle="Musterstadt" />');

        self::assertStringContainsString('aria-label="To the home page"', $rendered);
    }
}
