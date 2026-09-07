<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Asset;

use BalatD\KernUx\Asset\KernAssetInstaller;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The installer reaches the network and then writes into a public directory, which
 * makes it the one class here where being wrong is a security problem rather than a
 * rendering problem - and it had no test at all.
 *
 * Everything is driven through a stubbed RequestFactory, so no test touches the
 * network. The archives are built in the test, including one that a well-behaved npm
 * would never publish.
 */
final class KernAssetInstallerTest extends FunctionalTestCase
{
    private const VERSION = '2.7.2';
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    /**
     * An isolated target under the test instance.
     *
     * Emphatically not the extension's real Resources/Public/Vendor/KernUx/: the
     * installer starts by deleting its target, and in a functional test EXT: paths
     * resolve to the checked-out extension - so pointing these tests at the default
     * replaced the actual installed distribution with a six-byte fixture.
     */
    private string $assetTarget;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assetTarget = $this->instancePath . '/typo3temp/var/tests/kern-assets-' . bin2hex(random_bytes(4)) . '/';
    }

    #[Test]
    public function installsOnlyTheArtefactsWeServe(): void
    {
        $archive = $this->tarball([
            'package/dist/kern.min.css' => 'body{color:red}',
            'package/dist/kern.css' => 'body{color:red}',
            'package/dist/fonts/fira-sans.css' => '@font-face{}',
            'package/dist/fonts/fira-sans/FiraSans-Book.woff2' => 'woff2',
            // Present in the real distribution and deliberately not served: the SCSS
            // sources of the faces, and the Kopfzeile web component, which
            // Organism/Kopfzeile replaces with a CSS-only variant on purpose.
            'package/dist/fonts/fira-sans/fira-sans.scss' => '// source',
            'package/dist/js/kern-kopfzeile.js' => 'customElements.define()',
            // Alternatives to kern.min.css rather than additions to it.
            'package/dist/kern-grid.min.css' => '.grid{}',
            'package/package.json' => '{}',
        ]);

        $result = $this->installer($archive)->install(self::VERSION, true);

        self::assertSame(4, $result->fileCount);
        self::assertSame(
            [
                'fonts/fira-sans.css',
                'fonts/fira-sans/FiraSans-Book.woff2',
                'kern.css',
                'kern.min.css',
            ],
            $this->installedFiles(),
        );
    }

    #[Test]
    public function doesNotWriteOutsideTheTargetDirectory(): void
    {
        $escapee = 'package/dist/fonts/' . str_repeat('../', 8) . 'escaped.css';
        $archive = $this->tarball([
            'package/dist/kern.min.css' => 'body{}',
            $escapee => 'PWNED',
        ]);

        $this->installer($archive)->install(self::VERSION, true);

        $target = $this->target();
        self::assertSame(['kern.min.css'], $this->installedFiles());
        // Wherever "outside" would have landed, it must not exist. Phar happens to drop
        // upper-directory entries while iterating, so today this passes before the
        // installer's own containment assert is even reached - which is the point of
        // pinning it: swap the extractor and this test is what notices.
        self::assertFileDoesNotExist(dirname($target, 2) . '/escaped.css');
        self::assertFileDoesNotExist(dirname($target, 6) . '/escaped.css');
    }

    #[Test]
    public function refusesATarballHostedAnywhereButTheRegistry(): void
    {
        $archive = $this->tarball(['package/dist/kern.min.css' => 'body{}']);
        $installer = $this->installer($archive, tarballUrl: 'https://192.168.0.1/evil.tgz');

        $this->expectExceptionCode(1756131011);
        $installer->install(self::VERSION, true);
    }

    #[Test]
    public function refusesAPlainHttpTarball(): void
    {
        $archive = $this->tarball(['package/dist/kern.min.css' => 'body{}']);
        $installer = $this->installer($archive, tarballUrl: 'http://registry.npmjs.org/x.tgz');

        $this->expectExceptionCode(1756131011);
        $installer->install(self::VERSION, true);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function weakAlgorithmProvider(): array
    {
        return ['md5' => ['md5'], 'sha1' => ['sha1'], 'crc32' => ['crc32'], 'unknown' => ['not-a-hash']];
    }

    #[Test]
    #[DataProvider('weakAlgorithmProvider')]
    public function refusesAnIntegrityAlgorithmItDoesNotTrust(string $algorithm): void
    {
        $archive = $this->tarball(['package/dist/kern.min.css' => 'body{}']);
        $installer = $this->installer($archive, algorithm: $algorithm);

        // An unknown algorithm additionally has to fail here rather than inside hash(),
        // which throws a ValueError that would escape the installer's exception contract.
        $this->expectExceptionCode(1756131012);
        $installer->install(self::VERSION, true);
    }

    #[Test]
    public function refusesAnArchiveThatDoesNotMatchItsIntegrityHash(): void
    {
        $archive = $this->tarball(['package/dist/kern.min.css' => 'body{}']);
        $installer = $this->installer($archive, integrity: 'sha512-' . base64_encode(str_repeat("\0", 64)));

        $this->expectExceptionCode(1756131006);
        $installer->install(self::VERSION, true);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badVersionProvider(): array
    {
        return [
            'path traversal' => ['../../etc/passwd'],
            'query string' => ['2.7.2?x=1'],
            'empty-ish' => ['latest'],
            'url' => ['https://example.com/'],
        ];
    }

    #[Test]
    #[DataProvider('badVersionProvider')]
    public function refusesAVersionThatIsNotAVersion(string $version): void
    {
        $archive = $this->tarball(['package/dist/kern.min.css' => 'body{}']);

        $this->expectExceptionCode(1756131010);
        $this->installer($archive)->install($version, true);
    }

    #[Test]
    public function leavesThePreviousInstallStandingWhenTheArchiveIsUseless(): void
    {
        $good = $this->tarball(['package/dist/kern.min.css' => 'body{color:red}']);
        $this->installer($good)->install(self::VERSION, true);
        self::assertSame(['kern.min.css'], $this->installedFiles());

        $useless = $this->tarball(['package/README.md' => '# nothing we want']);

        try {
            $this->installer($useless)->install(self::VERSION, true);
            self::fail('Expected the installer to reject an archive with no wanted files.');
        } catch (\RuntimeException $exception) {
            self::assertSame(1756131007, $exception->getCode());
        }

        // Extraction used to happen straight into the target, so a failure left the site
        // with no stylesheet at all.
        self::assertSame(['kern.min.css'], $this->installedFiles());
        self::assertStringContainsString('red', (string)file_get_contents($this->target() . 'kern.min.css'));
    }

    #[Test]
    public function leavesNoStagingDirectoryOrTemporaryArchiveBehind(): void
    {
        $archive = $this->tarball(['package/dist/kern.min.css' => 'body{}']);
        $this->installer($archive)->install(self::VERSION, true);

        $vendor = dirname($this->target());
        self::assertSame([], glob($vendor . '.incoming-*') ?: []);
        self::assertSame([], glob(dirname($vendor) . '/KernUx.incoming-*') ?: []);

        // tempnam() creates a file, and the old code then appended .tgz to its path -
        // so the archive went to a sibling and the created file was never removed.
        $transient = \TYPO3\CMS\Core\Core\Environment::getVarPath() . '/transient/kern-ux-assets';
        self::assertSame([], glob($transient . '/*') ?: []);
    }

    #[Test]
    public function skipsTheDownloadWhenThePinnedVersionIsAlreadyInstalled(): void
    {
        $archive = $this->tarball(['package/dist/kern.min.css' => 'body{}']);
        $this->installer($archive)->install(self::VERSION, true);

        $requestFactory = $this->createMock(RequestFactory::class);
        $requestFactory->expects($this->never())->method('request');
        $result = (new KernAssetInstaller($requestFactory, $this->assetTarget))->install(self::VERSION);

        self::assertTrue($result->skipped);
        self::assertSame(0, $result->fileCount);
    }

    private function target(): string
    {
        $target = GeneralUtility::getFileAbsFileName($this->assetTarget);
        self::assertNotSame('', $target);

        return rtrim($target, '/') . '/';
    }

    /**
     * @return list<string>
     */
    private function installedFiles(): array
    {
        $target = $this->target();
        if (!is_dir($target)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $files[] = substr($file->getPathname(), strlen($target));
            }
        }
        sort($files);

        return $files;
    }

    private function installer(
        string $archive,
        string $tarballUrl = 'https://registry.npmjs.org/@kern-ux/native/-/native-2.7.2.tgz',
        string $algorithm = 'sha512',
        ?string $integrity = null,
    ): KernAssetInstaller {
        $body = (string)file_get_contents($archive);
        if ($integrity === null) {
            $integrity = in_array($algorithm, hash_algos(), true)
                ? $algorithm . '-' . base64_encode(hash($algorithm, $body, true))
                : $algorithm . '-' . base64_encode('unknown');
        }

        $requestFactory = self::createStub(RequestFactory::class);
        $requestFactory->method('request')->willReturnCallback(
            function (string $uri, string $method = 'GET', array $options = []) use ($tarballUrl, $integrity, $body): ResponseInterface {
                // Every request the installer makes has to refuse redirects; a test that
                // did not check this would pass just as well without the option.
                self::assertFalse($options['allow_redirects'] ?? true, 'Requests must not follow redirects.');

                if (str_ends_with($uri, '.tgz')) {
                    return $this->response($body);
                }

                return $this->response((string)json_encode([
                    'dist' => ['tarball' => $tarballUrl, 'integrity' => $integrity],
                ]));
            },
        );

        return new KernAssetInstaller($requestFactory, $this->assetTarget);
    }

    private function response(string $body): ResponseInterface
    {
        $response = new Response('php://temp', 200);
        $response->getBody()->write($body);
        $response->getBody()->rewind();

        return $response;
    }

    /**
     * Writes a gzipped tar with the given entries and returns its path.
     *
     * Hand-rolled rather than built with PharData, because PharData refuses to create
     * an entry whose name contains an upper-directory reference - which is precisely
     * one of the archives this test needs.
     *
     * @param array<string, string> $entries
     */
    private function tarball(array $entries): string
    {
        $tar = '';
        foreach ($entries as $name => $contents) {
            $tar .= $this->tarHeader($name, strlen($contents));
            $tar .= $contents . str_repeat("\0", (512 - strlen($contents) % 512) % 512);
        }
        $tar .= str_repeat("\0", 1024);

        $path = $this->instancePath . '/typo3temp/var/tests/kern-' . bin2hex(random_bytes(6)) . '.tgz';
        GeneralUtility::mkdir_deep(dirname($path));
        GeneralUtility::writeFile($path, (string)gzencode($tar), true);

        return $path;
    }

    private function tarHeader(string $name, int $size): string
    {
        $header = str_pad(substr($name, 0, 100), 100, "\0")
            . str_pad('0000644', 8, "\0")
            . str_pad('0000000', 8, "\0")
            . str_pad('0000000', 8, "\0")
            . str_pad(decoct($size), 11, '0', STR_PAD_LEFT) . "\0"
            . str_pad(decoct(time()), 11, '0', STR_PAD_LEFT) . "\0"
            . str_repeat(' ', 8)
            . '0'
            . str_repeat("\0", 100)
            . "ustar\0" . '00'
            . str_repeat("\0", 32 + 32 + 8 + 8 + 155 + 12);

        $checksum = 0;
        for ($i = 0; $i < 512; ++$i) {
            $checksum += ord($header[$i]);
        }

        return substr_replace(
            $header,
            str_pad(decoct($checksum), 6, '0', STR_PAD_LEFT) . "\0 ",
            148,
            8,
        );
    }
}
