<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Command;

use BalatD\KernUx\Command\DumpStyleguideCommand;
use BalatD\KernUx\Styleguide\StyleguideRenderer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The guards on --target, and deliberately nothing else.
 *
 * This command begins by recursively deleting whatever --target names, because a dump
 * has to start from an empty directory and GeneralUtility::rmdir() allowlists nothing.
 * That makes the three refusals in resolveTarget() the whole safety story: a path
 * outside the project, a directory this command did not create, and a file. Each one
 * returns before anything is deleted, so each one is cheap to test and expensive to
 * lose.
 *
 * The happy path is not tested here, and that is a decision rather than an omission. It
 * needs Resources/Public/Vendor/KernUx/kern.min.css, which is fetched at install time
 * and absent in the test matrix; faking it would mean writing into the extension's own
 * public directory, which is precisely the trap KernAssetInstallerTest documents. A
 * test asserting "fails because the assets are missing" would pass in CI and fail on a
 * developer machine that has run the installer, which is worse than no test. The dump
 * is exercised for real by the accessibility job, which installs the assets first.
 */
final class DumpStyleguideCommandTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    private function tester(): CommandTester
    {
        $renderer = $this->get(StyleguideRenderer::class);
        self::assertInstanceOf(StyleguideRenderer::class, $renderer);

        return new CommandTester(new DumpStyleguideCommand($renderer));
    }

    #[Test]
    public function refusesATargetOutsideTheProject(): void
    {
        $tester = $this->tester();
        $tester->execute(['--target' => '/tmp/kern-ux-somewhere-else']);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('must be a directory inside', $tester->getDisplay());
    }

    #[Test]
    public function refusesTheProjectRootItself(): void
    {
        $tester = $this->tester();
        $tester->execute(['--target' => Environment::getProjectPath()]);

        // Without this the command would delete the whole project.
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('must be a directory inside', $tester->getDisplay());
    }

    #[Test]
    public function refusesADirectoryItDidNotCreate(): void
    {
        $existing = Environment::getProjectPath() . '/var/not-a-dump';
        mkdir($existing, 0o777, true);

        try {
            $tester = $this->tester();
            $tester->execute(['--target' => $existing]);

            // The marker file is the only thing separating "our scratch directory" from
            // somebody's source tree, so an unmarked directory is never deleted.
            self::assertSame(1, $tester->getStatusCode());
            self::assertStringContainsString('was not created by this command', $tester->getDisplay());
        } finally {
            rmdir($existing);
        }
    }

    #[Test]
    public function refusesAFile(): void
    {
        $file = Environment::getProjectPath() . '/var/not-a-directory.txt';
        file_put_contents($file, 'x');

        try {
            $tester = $this->tester();
            $tester->execute(['--target' => $file]);

            self::assertSame(1, $tester->getStatusCode());
            self::assertStringContainsString('not a directory', $tester->getDisplay());
        } finally {
            unlink($file);
        }
    }
}
