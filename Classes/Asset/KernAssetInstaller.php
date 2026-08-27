<?php

declare(strict_types=1);

namespace BalatD\KernUx\Asset;

use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Downloads a pinned @kern-ux/native release and copies the parts we serve.
 *
 * Only the artefacts KERN's own documentation tells integrators to load are copied:
 * the full stylesheet, the Fira Sans and Noto Sans faces, and the single JavaScript
 * file KERN ships (the Kopfzeile web component). The grid- and utilities-only
 * stylesheets are deliberately skipped - they are alternatives to kern.min.css, not
 * additions to it, and shipping all three invites loading them together.
 *
 * The font CSS references its woff2 files relatively (`url("./fira-sans/…")`), so the
 * directory layout under dist/ has to survive the copy verbatim.
 */
final readonly class KernAssetInstaller
{
    /**
     * The version this extension's components are written and tested against.
     * Bumping it is a deliberate act: KERN changes component markup between minors.
     */
    public const PINNED_VERSION = '2.7.2';

    private const REGISTRY = 'https://registry.npmjs.org/@kern-ux/native';

    private const TARGET = 'EXT:kern_ux/Resources/Public/Vendor/KernUx/';

    /**
     * Paths inside the npm tarball, relative to its `package/` root.
     *
     * @var list<string>
     */
    private const WANTED_PREFIXES = [
        'dist/kern.min.css',
        'dist/kern.css',
        'dist/fonts/',
        'dist/js/',
    ];

    public function __construct(private RequestFactory $requestFactory) {}

    public function install(string $version, bool $force = false): InstallResult
    {
        $target = GeneralUtility::getFileAbsFileName(self::TARGET);
        if ($target === '') {
            throw new \RuntimeException('Could not resolve ' . self::TARGET, 1756131001);
        }

        $stamp = $target . '.kern-version';
        if (!$force && is_file($stamp) && trim((string)file_get_contents($stamp)) === $version) {
            return new InstallResult($version, $target, 0, true);
        }

        $release = $this->fetchReleaseMetadata($version);
        $archive = $this->downloadArchive($release['tarball'], $release['integrity']);

        try {
            GeneralUtility::rmdir($target, true);
            GeneralUtility::mkdir_deep($target);
            $count = $this->extract($archive, $target);
        } finally {
            @unlink($archive);
        }

        GeneralUtility::writeFile($stamp, $version . "\n", true);

        return new InstallResult($version, $target, $count);
    }

    /**
     * @return array{tarball: string, integrity: string}
     */
    private function fetchReleaseMetadata(string $version): array
    {
        $url = self::REGISTRY . '/' . rawurlencode($version);
        $response = $this->requestFactory->request($url, 'GET', ['headers' => ['Accept' => 'application/json']]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                sprintf('@kern-ux/native %s not found in the npm registry (HTTP %d).', $version, $response->getStatusCode()),
                1756131002,
            );
        }

        $data = json_decode((string)$response->getBody(), true);
        $dist = is_array($data) ? ($data['dist'] ?? null) : null;
        if (!is_array($dist)) {
            throw new \RuntimeException('npm registry response has no dist section.', 1756131003);
        }

        $tarball = $dist['tarball'] ?? null;
        $integrity = $dist['integrity'] ?? null;
        if (!is_string($tarball) || !is_string($integrity)) {
            throw new \RuntimeException('npm registry response is missing dist.tarball or dist.integrity.', 1756131008);
        }

        return ['tarball' => $tarball, 'integrity' => $integrity];
    }

    /**
     * Verifies npm's Subresource Integrity hash. Without this the pinned version
     * would only be a hint, and a compromised or truncated download would be copied
     * straight into a public asset directory.
     */
    private function downloadArchive(string $url, string $integrity): string
    {
        $response = $this->requestFactory->request($url, 'GET');
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Download failed (HTTP %d): %s', $response->getStatusCode(), $url), 1756131004);
        }
        $body = (string)$response->getBody();

        [$algorithm, $expected] = array_pad(explode('-', $integrity, 2), 2, '');
        if ($algorithm === '' || $expected === '') {
            throw new \RuntimeException('Malformed integrity value: ' . $integrity, 1756131005);
        }
        $actual = base64_encode(hash($algorithm, $body, true));
        if (!hash_equals($expected, $actual)) {
            throw new \RuntimeException(
                sprintf('Integrity check failed for %s (expected %s-%s, got %s-%s).', $url, $algorithm, $expected, $algorithm, $actual),
                1756131006,
            );
        }

        $file = tempnam(sys_get_temp_dir(), 'kern-ux-') . '.tgz';
        GeneralUtility::writeFile($file, $body, true);

        return $file;
    }

    private function extract(string $archive, string $target): int
    {
        $phar = new \PharData($archive);
        $count = 0;

        /** @var \PharFileInfo $file */
        foreach (new \RecursiveIteratorIterator($phar) as $file) {
            // Tarball entries are prefixed with "package/".
            $relative = preg_replace('#^.*?/package/#', '', str_replace('\\', '/', $file->getPathname()));
            if (!is_string($relative) || !$this->isWanted($relative)) {
                continue;
            }

            $destination = $target . substr($relative, strlen('dist/'));
            GeneralUtility::mkdir_deep(dirname($destination));
            GeneralUtility::writeFile($destination, (string)file_get_contents($file->getPathname()), true);
            ++$count;
        }

        if ($count === 0) {
            throw new \RuntimeException('Archive contained none of the expected dist/ files.', 1756131007);
        }

        return $count;
    }

    private function isWanted(string $relative): bool
    {
        foreach (self::WANTED_PREFIXES as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
