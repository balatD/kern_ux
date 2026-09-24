<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Molecule;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The navigation list, which is the one component that calls itself.
 *
 * Recursion is why this needs pinning. The sub-level has to be a different class from
 * the top level or the flyout CSS matches the wrong list, and the recursion has to keep
 * descending rather than stopping at the level the template happens to be written for.
 *
 * The other half is aria-current, which is the only thing telling a screen reader user
 * which page they are on. Marking too much is as wrong as marking nothing: two current
 * pages in one menu is simply false.
 */
final class NavigationListTest extends AbstractComponentTestCase
{
    private const THREE_LEVELS = '<k:molecule.navigationList items="{0: {title: \'Amt\', link: \'/amt\','
        . ' children: {0: {title: \'Team\', link: \'/amt/team\','
        . ' children: {0: {title: \'Leitung\', link: \'/amt/team/leitung\'}}}}}}" />';

    #[Test]
    public function marksTheTopLevelAndTheSubLevelDifferently(): void
    {
        $rendered = $this->renderSource(self::THREE_LEVELS);

        // kernt3-nav is the bar; kernt3-nav__sub is the panel that flies out of it. One
        // class for both would style the flyout as a second navigation bar.
        self::assertStringContainsString('<ul class="kernt3-nav">', $rendered);
        self::assertStringContainsString('<ul class="kernt3-nav__sub">', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function keepsDescendingBeyondTheSecondLevel(): void
    {
        $rendered = $this->renderSource(self::THREE_LEVELS);

        // The third level is where a loop written for two levels stops silently.
        self::assertStringContainsString('Leitung', $rendered);
        self::assertSame(2, substr_count($rendered, '<ul class="kernt3-nav__sub">'));
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function stylesALinkBelowTheTopLevelAsASublink(): void
    {
        $rendered = $this->renderSource(self::THREE_LEVELS);

        self::assertStringContainsString('kernt3-nav__link" href="/amt"', $rendered);
        self::assertStringContainsString('kernt3-nav__sublink" href="/amt/team"', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function marksOnlyTheCurrentPage(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.navigationList items="{0: {title: \'Start\', link: \'/\', current: 1},'
            . ' 1: {title: \'Amt\', link: \'/amt\'}}" />',
        );

        self::assertSame(1, substr_count($rendered, 'aria-current="page"'));
        self::assertStringContainsString('href="/" aria-current="page"', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function rendersEveryBranchRatherThanOnlyTheOpenOne(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.navigationList items="{0: {title: \'Amt\', link: \'/amt\','
            . ' children: {0: {title: \'Team\', link: \'/amt/team\'}}},'
            . ' 1: {title: \'Rat\', link: \'/rat\', current: 1}}" />',
        );

        // The flyout is opened by CSS and JS on hover and focus, so every branch's
        // children have to already be in the document - not just the active one's.
        self::assertStringContainsString('Team', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function takesTheVisitedStateOffNavigationLinks(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.navigationList items="{0: {title: \'Amt\', link: \'/amt\'}}" />',
        );

        // A menu whose entries change colour once visited reads as a list of links the
        // visitor has used up rather than as the structure of the site.
        self::assertStringContainsString('kern-link--no-visited-state', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }
}
