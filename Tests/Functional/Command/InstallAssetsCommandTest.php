<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Command;

use BalatD\KernUx\Asset\KernAssetInstaller;
use BalatD\KernUx\Command\InstallAssetsCommand;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * What the command adds on top of the installer: the two things an integrator sees.
 *
 * The installer itself is covered thoroughly by KernAssetInstallerTest. What is only
 * here is the reporting, and it matters because this command is the documented first
 * step of every installation and every deployment. A failure that exits 0 leaves a
 * deployment "green" with no stylesheet, and a re-run that silently redownloads instead
 * of saying "already installed" is how the step gets dropped from a deployment for
 * being slow.
 *
 * No network: the installer is constructed with a stubbed RequestFactory and an
 * isolated target under the test instance, never the extension's own public directory.
 */
final class InstallAssetsCommandTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    private string $assetTarget;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assetTarget = $this->instancePath . '/typo3temp/var/tests/kern-cmd-' . bin2hex(random_bytes(4)) . '/';
    }

    #[Test]
    public function reportsAFailedDownloadAsAFailure(): void
    {
        $requestFactory = self::createStub(RequestFactory::class);
        $requestFactory->method('request')->willThrowException(
            new \RuntimeException('Could not resolve host: registry.npmjs.org', 1756131201),
        );

        $tester = new CommandTester(new InstallAssetsCommand(
            new KernAssetInstaller($requestFactory, $this->assetTarget),
        ));
        $tester->execute([]);

        // This is what an integrator behind a proxy sees on first run, and it has to be
        // a non-zero exit or a deployment carries on without any KERN CSS at all.
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Could not resolve host', $tester->getDisplay());
    }

    #[Test]
    public function saysNothingWasDoneWhenThePinnedVersionIsAlreadyThere(): void
    {
        $archive = $this->tarball();
        $body = (string)file_get_contents($archive);
        $requestFactory = self::createStub(RequestFactory::class);
        $requestFactory->method('request')->willReturnCallback(
            function (string $uri) use ($body): ResponseInterface {
                if (str_ends_with($uri, '.tgz')) {
                    return $this->response($body);
                }

                return $this->response((string)json_encode([
                    'dist' => [
                        'tarball' => 'https://registry.npmjs.org/@kern-ux/native/-/native-'
                            . KernAssetInstaller::PINNED_VERSION . '.tgz',
                        'integrity' => 'sha512-' . base64_encode(hash('sha512', $body, true)),
                    ],
                ]));
            },
        );

        $command = new InstallAssetsCommand(new KernAssetInstaller($requestFactory, $this->assetTarget));

        $first = new CommandTester($command);
        $first->execute([]);
        self::assertSame(0, $first->getStatusCode());
        self::assertStringContainsString('Installed KERN', $first->getDisplay());

        $second = new CommandTester($command);
        $second->execute([]);
        self::assertSame(0, $second->getStatusCode());
        // Re-running must be cheap and must say so; a deployment runs this every time.
        self::assertStringContainsString('already installed', $second->getDisplay());
    }

    private function tarball(): string
    {
        $files = [
            'package/dist/kern.min.css' => 'body{color:red}',
            'package/dist/kern.css' => 'body{color:red}',
        ];

        $directory = $this->instancePath . '/typo3temp/var/tests';
        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }

        $tar = $directory . '/kern-cmd-' . bin2hex(random_bytes(4)) . '.tar';
        $archive = new \PharData($tar);
        foreach ($files as $path => $contents) {
            $archive->addFromString($path, $contents);
        }
        $archive->compress(\Phar::GZ);
        unlink($tar);

        return $tar . '.gz';
    }

    private function response(string $body): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write($body);
        $response->getBody()->rewind();

        return $response;
    }
}
