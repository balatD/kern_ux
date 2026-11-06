<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Configuration;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The site set has to win against fluid_styled_content without breaking it.
 *
 * FSC is part of every TYPO3 distribution, and on TYPO3 14 `typo3 setup` writes its sets
 * into the site before an integrator ever selects this one. Both claim lib.contentElement,
 * tt_content.shortcut and the menu_* objects, so their order decides what renders - and
 * the wrong order is not a clean loss. FSC's `=<` reference keeps the children this set
 * copied in, so a core menu looks for FSC's template name in this extension's template
 * folder, and the editor gets "Oops, an error occurred!" on an element the wizard offered.
 *
 * Rendered through a real frontend request rather than read from TypoScript: the defect
 * is in how two sets combine, and neither file shows it on its own.
 */
final class FluidStyledContentCoexistenceTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['fluid_styled_content', 'form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/FluidStyledContentCoexistence.csv');

        // FSC listed first, which is what `typo3 setup` writes on TYPO3 14.
        $siteDirectory = Environment::getConfigPath() . '/sites/main';
        GeneralUtility::mkdir_deep($siteDirectory);
        GeneralUtility::writeFile($siteDirectory . '/config.yaml', <<<'YAML'
            base: 'http://localhost/'
            rootPageId: 1
            dependencies:
              - typo3/fluid-styled-content
              - kern-ux/kern-ux
            languages:
              -
                title: Deutsch
                enabled: true
                languageId: 0
                base: /
                locale: de_DE.UTF-8
                navigationTitle: Deutsch
                flag: de
            YAML);
    }

    #[Test]
    public function aCoreMenuRendersTheKernListInsteadOfFluidStyledContentsTemplate(): void
    {
        $html = $this->renderPage('/');

        self::assertStringContainsString(
            '<nav aria-label="Seiten"><ul class="kern-list kern-list--bullet"><li><a href="/bestand" class="kern-link">Bestand</a></li></ul></nav>',
            $html,
        );
    }

    #[Test]
    public function fluidStyledContentKeepsRenderingTheCoreElementsTheWizardHides(): void
    {
        $html = $this->renderPage('/bestand');

        self::assertStringContainsString('frame-type-text', $html);
        self::assertStringContainsString('<p>Ein Text aus der Zeit vor KERN.</p>', $html);
    }

    #[Test]
    public function aPluginIsWrappedInTheKernShellRatherThanTheFluidStyledContentFrame(): void
    {
        $html = $this->renderPage('/kontakt');

        self::assertStringContainsString(
            '<div id="kern-content-3" class="kernt3-content"><h2 class="kern-heading-medium">Kontakt</h2><div class="kernt3-form"><form id="kern-ux-kontakt-3"',
            $html,
        );
        self::assertStringNotContainsString('frame-type-form_formframework', $html);
    }

    private function renderPage(string $path): string
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost' . $path));
        $html = (string)$response->getBody();

        self::assertSame(200, $response->getStatusCode(), mb_substr(strip_tags($html), 0, 2000));
        self::assertStringNotContainsString('Oops, an error occurred', $html);

        return $html;
    }
}
