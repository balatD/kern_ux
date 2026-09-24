<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Styleguide;

use BalatD\KernUx\Styleguide\ComponentCatalog;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Keeps the component gallery honest.
 *
 * The gallery is the living documentation and the accessibility-test target, so a
 * component that exists but is not shown there is invisible to both. This test is
 * what makes adding a component without an example a build failure rather than a
 * silent omission.
 */
final class ComponentCatalogTest extends FunctionalTestCase
{
    /**
     * KERN component families this extension knowingly does not reach.
     *
     * Asserted in both directions, like rendersNothing below: a family that becomes
     * covered has to leave this list, or the list quietly turns into somewhere to hide
     * a regression instead of a record of decisions.
     */
    private const KNOWN_GAPS = [
        // KERN's own stylesheet says so, in a banner above the region: "Dropdown ist
        // noch in der Entwicklungsphase und nicht teil des Desingn System
        // CSS-Frameworks!!!". Implementing it would freeze a k: tag name and its
        // arguments against markup upstream has not frozen.
        'kern-dropdown',
        // A label that collapses to icon-only below 576px. That is an argument on
        // atom.button, not a component of its own, and nothing has asked for it yet.
        'kern-sr-only-mobile',
        // No form field can carry a unit yet. Being closed as a form affordance.
        'kern-input-group-text',
    ];

    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    private function catalog(): ComponentCatalog
    {
        $catalog = $this->get(ComponentCatalog::class);
        self::assertInstanceOf(ComponentCatalog::class, $catalog);

        return $catalog;
    }

    #[Test]
    public function everyComponentAppearsInTheGallery(): void
    {
        $undocumented = $this->catalog()->undocumentedComponents();

        self::assertSame(
            [],
            $undocumented,
            'These components have no entry in Configuration/Styleguide/Examples.yaml: '
            . implode(', ', $undocumented),
        );
    }

    #[Test]
    public function discoversTheComponentTreeOnBothFluidMajors(): void
    {
        // Discovery is file-system based on purpose: Fluid's own
        // getAvailableComponents() only exists from Fluid 5, so it is unavailable on
        // TYPO3 13 and cannot back a catalogue that has to work on both.
        $components = $this->catalog()->discoverComponents();

        self::assertContains('atom.button', $components);
        self::assertContains('molecule.breadcrumb', $components);
        self::assertContains('organism.header', $components);
        self::assertGreaterThanOrEqual(18, count($components));
    }

    #[Test]
    public function ignoresFilesThatAreNotComponents(): void
    {
        // Fluid resolves a component at Group/Name/Name.html, so anything else in the
        // tree - a partial, a stray file - must not be reported as a component.
        foreach ($this->catalog()->discoverComponents() as $name) {
            self::assertMatchesRegularExpression('/^[a-z][A-Za-z0-9]*(\.[a-z][A-Za-z0-9]*)+$/', $name);
        }
    }

    #[Test]
    public function everyKernComponentFamilyIsReachable(): void
    {
        $catalog = $this->catalog();

        if (!$catalog->kernStylesheetAvailable()) {
            // The distribution is fetched, not committed, and the test matrix never
            // fetches it. Failing here would redden every leg for a file that is
            // absent by design, and the only fix would be to weaken the assertion.
            self::markTestSkipped(
                'The KERN distribution is not installed - run kern-ux:assets:install.',
            );
        }

        $uncovered = $catalog->uncoveredKernFamilies();

        $unexpected = array_values(array_diff($uncovered, self::KNOWN_GAPS));
        self::assertSame(
            [],
            $unexpected,
            'KERN ships these component families and nothing here reaches them: '
            . implode(', ', $unexpected),
        );

        $closed = array_values(array_diff(self::KNOWN_GAPS, $uncovered));
        self::assertSame(
            [],
            $closed,
            'These families are covered now and must leave KNOWN_GAPS: ' . implode(', ', $closed),
        );
    }

    #[Test]
    public function everyExampleRendersToNonEmptyMarkup(): void
    {
        $examples = $this->catalog()->examples();

        self::assertNotEmpty($examples);
        foreach ($examples as $example) {
            $where = sprintf('Example "%s" of %s', $example->label, $example->component);

            if ($example->rendersNothing) {
                // The flag has to stay honest in both directions, otherwise it would
                // become a way to hide a broken example.
                self::assertSame('', trim($example->markup), $where . ' is flagged rendersNothing but rendered markup.');
                continue;
            }

            self::assertNotSame('', trim($example->markup), $where . ' rendered nothing.');
        }
    }
}
