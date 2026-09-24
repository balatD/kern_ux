<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Command;

use BalatD\KernUx\Command\RenderComponentCommand;
use BalatD\KernUx\Rendering\FluidSourceRenderer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The markup-inspection command.
 *
 * Small, but it is the tool the conventions are checked with: rendering the same
 * component under TYPO3 13 and 14 and diffing the two is how a Fluid 4 against Fluid 5
 * difference gets caught, and that only works if the command itself renders the same
 * way on both. Its --xmlns option exists for exactly that comparison, so it is asserted
 * rather than assumed to still work.
 */
final class RenderComponentCommandTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    private function tester(): CommandTester
    {
        $renderer = $this->get(FluidSourceRenderer::class);
        self::assertInstanceOf(FluidSourceRenderer::class, $renderer);

        return new CommandTester(new RenderComponentCommand($renderer));
    }

    #[Test]
    public function rendersTheSourceItIsGiven(): void
    {
        $tester = $this->tester();
        $tester->execute(['source' => '<k:atom.badge variant="success">Erledigt</k:atom.badge>']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString(
            '<span class="kern-badge kern-badge--success"><span class="kern-label">Erledigt</span></span>',
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function rendersABuiltInSnippetWhenGivenNothing(): void
    {
        $tester = $this->tester();
        $tester->execute([]);

        // Running it bare is the quickest check that the component layer resolves at
        // all, so it has to produce markup rather than an empty line.
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('kern-btn', $tester->getDisplay());
    }

    #[Test]
    public function resolvesComponentsWithTheNamespaceDeclaredInline(): void
    {
        $tester = $this->tester();
        $tester->execute(
            ['source' => '<k:atom.badge>Status</k:atom.badge>', '--xmlns' => true],
        );

        // Without the global registration - which is what --xmlns simulates - a project
        // that declares the namespace itself must still resolve the same components.
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('kern-badge', $tester->getDisplay());
    }
}
