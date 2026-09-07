<?php

declare(strict_types=1);

namespace BalatD\KernUx\Asset;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Downloads a pinned @kern-ux/native release and copies the parts we serve.
 *
 * Only the artefacts KERN's own documentation tells integrators to load are copied:
 * the full stylesheet and the Fira Sans and Noto Sans faces. The grid- and
 * utilities-only stylesheets are deliberately skipped - they are alternatives to
 * kern.min.css, not additions to it, and shipping all three invites loading them
 * together. KERN's one JavaScript file, the Kopfzeile web component, is skipped too:
 * Organism/Kopfzeile renders the CSS-only variant on purpose, so the 12.7 kB script
 * would sit in a web-served directory that nothing ever loads.
 *
 * The font CSS references its woff2 files relatively (`url("./fira-sans/…")`), so the
 * directory layout under dist/ has to survive the copy verbatim.
 *
 * This class reaches the network and then writes into a public directory, which makes
 * it the most security-sensitive code here. Four things constrain it:
 *
 *   - the registry host is pinned, and redirects are refused, so a hostile or
 *     compromised registry response cannot steer the download at an internal address;
 *   - the integrity algorithm is allowlisted, so such a response cannot downgrade the
 *     check to a broken hash;
 *   - every extracted path is asserted to stay inside the target, so a crafted archive
 *     entry cannot write outside it;
 *   - extraction happens in a staging directory that replaces the target only once it
 *     succeeded, so a failure leaves the previous install standing.
 */
final readonly class KernAssetInstaller
{
    /**
     * The version this extension's components are written and tested against.
     * Bumping it is a deliberate act: KERN changes component markup between minors.
     */
    public const PINNED_VERSION = '2.7.2';

    private const REGISTRY_HOST = 'registry.npmjs.org';

    private const REGISTRY = 'https://' . self::REGISTRY_HOST . '/@kern-ux/native';

    /**
     * Where the distribution is served from, and the default for $target below.
     */
    public const TARGET = 'EXT:kern_ux/Resources/Public/Vendor/KernUx/';

    /**
     * Integrity algorithms this installer accepts from the registry.
     *
     * npm states the algorithm in the `integrity` value, so it arrives over the network
     * like everything else in that response. Without an allowlist a response naming
     * `md5` would be honoured, and the SHA-512 check this extension documents would
     * quietly become no check at all.
     *
     * @var list<string>
     */
    private const ALLOWED_INTEGRITY_ALGORITHMS = ['sha512', 'sha384', 'sha256'];

    /**
     * Paths inside the npm tarball, relative to its `package/` root.
     *
     * @var list<string>
     */
    private const WANTED_PREFIXES = [
        'dist/kern.min.css',
        'dist/kern.css',
        'dist/fonts/',
    ];

    /**
     * Extensions that may be written into the public directory.
     *
     * `dist/fonts/` is taken wholesale to keep the relative url() references intact,
     * and it also carries the SCSS sources the faces were built from. Those have no
     * business being web-served.
     *
     * @var list<string>
     */
    private const ALLOWED_EXTENSIONS = ['css', 'woff2', 'woff'];

    /**
     * The target is a constructor argument rather than only a constant so that this
     * class can be exercised without writing into the extension it ships in. Its own
     * first step is a recursive delete, so a test that used the real path would replace
     * a developer's - or CI's - installed KERN distribution with whatever fixture it
     * happened to be feeding in.
     */
    public function __construct(
        private RequestFactory $requestFactory,
        private string $target = self::TARGET,
    ) {}

    public function install(string $version, bool $force = false): InstallResult
    {
        $this->assertVersion($version);

        $target = GeneralUtility::getFileAbsFileName($this->target);
        if ($target === '') {
            throw new \RuntimeException('Could not resolve ' . $this->target, 1756131001);
        }
        $target = rtrim(GeneralUtility::fixWindowsFilePath($target), '/');

        $stamp = $target . '.kern-version';
        if (!$force && is_file($stamp) && trim((string)file_get_contents($stamp)) === $version) {
            return new InstallResult($version, $target . '/', 0, true);
        }

        $release = $this->fetchReleaseMetadata($version);
        $archive = $this->downloadArchive($release['tarball'], $release['integrity']);

        // Staged, then swapped. Extracting straight into the target meant deleting the
        // previous install first, so anything that went wrong afterwards - an archive
        // without the expected files, a full disk - left the site with no stylesheet at
        // all and no version stamp to show for it.
        $staging = $target . '.incoming-' . bin2hex(random_bytes(6));

        try {
            GeneralUtility::mkdir_deep($staging);
            $count = $this->extract($archive, $staging);

            GeneralUtility::rmdir($target, true);
            if (!rename($staging, $target)) {
                throw new \RuntimeException(sprintf('Could not move %s into place.', $staging), 1756131009);
            }
        } finally {
            @unlink($archive);
            if (is_dir($staging)) {
                GeneralUtility::rmdir($staging, true);
            }
        }

        GeneralUtility::writeFile($stamp, $version . "\n", true);

        return new InstallResult($version, $target . '/', $count);
    }

    /**
     * The version reaches the registry URL, so it may not be an arbitrary string.
     */
    private function assertVersion(string $version): void
    {
        if (preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version) !== 1) {
            throw new \RuntimeException('Not a valid @kern-ux/native version: ' . $version, 1756131010);
        }
    }

    /**
     * @return array{tarball: string, integrity: string}
     */
    private function fetchReleaseMetadata(string $version): array
    {
        $url = self::REGISTRY . '/' . rawurlencode($version);
        $response = $this->requestFactory->request($url, 'GET', $this->requestOptions([
            'headers' => ['Accept' => 'application/json'],
        ]));

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

        $this->assertRegistryUrl($tarball);

        return ['tarball' => $tarball, 'integrity' => $integrity];
    }

    /**
     * The tarball URL comes out of the registry response, so it is as trustworthy as
     * that response - which is to say, not enough to hand to an HTTP client unchecked.
     * Pinning host and scheme keeps a hostile or tampered response from turning an
     * install-time command into a request against something on the local network.
     */
    private function assertRegistryUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || ($parts['host'] ?? '') !== self::REGISTRY_HOST
        ) {
            throw new \RuntimeException(
                sprintf('Refusing to download from outside https://%s: %s', self::REGISTRY_HOST, $url),
                1756131011,
            );
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function requestOptions(array $options = []): array
    {
        return $options + [
            // Redirects are refused rather than followed: with the host pinned above,
            // a redirect is the one remaining way to move the request elsewhere. npm
            // serves registry metadata and tarballs directly, so this costs nothing -
            // and if that ever changes, it fails loudly instead of silently widening.
            'allow_redirects' => false,
            'timeout' => 60,
        ];
    }

    /**
     * Verifies npm's Subresource Integrity hash. Without this the pinned version
     * would only be a hint, and a compromised or truncated download would be copied
     * straight into a public asset directory.
     */
    private function downloadArchive(string $url, string $integrity): string
    {
        $response = $this->requestFactory->request($url, 'GET', $this->requestOptions());
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Download failed (HTTP %d): %s', $response->getStatusCode(), $url), 1756131004);
        }
        $body = (string)$response->getBody();

        [$algorithm, $expected] = array_pad(explode('-', $integrity, 2), 2, '');
        $algorithm = strtolower($algorithm);
        if ($algorithm === '' || $expected === '') {
            throw new \RuntimeException('Malformed integrity value: ' . $integrity, 1756131005);
        }
        if (!in_array($algorithm, self::ALLOWED_INTEGRITY_ALGORITHMS, true)) {
            // Also the reason this is checked before hash(): an unknown algorithm makes
            // hash() throw a ValueError, which would escape the exception contract every
            // other failure here keeps.
            throw new \RuntimeException(
                sprintf(
                    'Refusing integrity algorithm "%s"; expected one of %s.',
                    $algorithm,
                    implode(', ', self::ALLOWED_INTEGRITY_ALGORITHMS),
                ),
                1756131012,
            );
        }

        $actual = base64_encode(hash($algorithm, $body, true));
        if (!hash_equals($expected, $actual)) {
            throw new \RuntimeException(
                sprintf('Integrity check failed for %s (expected %s-%s, got %s-%s).', $url, $algorithm, $expected, $algorithm, $actual),
                1756131006,
            );
        }

        // Written under var/transient with a random name rather than through tempnam().
        // PharData detects the format from the file extension, so the archive needs to
        // end in .tgz - and appending that to a tempnam() path wrote the archive to a
        // *sibling* of the file tempnam() had created, leaking an empty file on every
        // single run.
        $directory = Environment::getVarPath() . '/transient/kern-ux-assets';
        GeneralUtility::mkdir_deep($directory);
        $file = $directory . '/' . bin2hex(random_bytes(8)) . '.tgz';
        GeneralUtility::writeFile($file, $body, true);

        return $file;
    }

    private function extract(string $archive, string $target): int
    {
        $phar = new \PharData($archive);
        $count = 0;
        $prefix = rtrim($target, '/') . '/';

        /** @var \PharFileInfo $file */
        foreach (new \RecursiveIteratorIterator($phar) as $file) {
            $relative = $this->relativePath($file->getPathname());
            if ($relative === null || !$this->isWanted($relative)) {
                continue;
            }

            $destination = $prefix . substr($relative, strlen('dist/'));

            // Containment, asserted rather than assumed.
            //
            // Not because the current extractor lets anything through: PHP's Phar
            // extension refuses entry names containing an upper-directory reference,
            // and silently drops them while iterating a tarball that carries one. So
            // "dist/fonts/../../../x" never reaches this line today - which is worth
            // knowing, because it means this assert is not what stands between the
            // archive and the filesystem.
            //
            // It is here for the day the extractor changes. isWanted() only proves an
            // entry starts with dist/fonts/, GeneralUtility::writeFile() performs no
            // path validation of its own, and the integrity check is no help either:
            // `integrity` and `tarball` come from the same registry response, so
            // whoever controls one controls both. A tar reader without Phar's habit
            // would hand a traversal straight through.
            $canonical = PathUtility::getCanonicalPath($destination);
            if (!str_starts_with($canonical, $prefix)) {
                throw new \RuntimeException(
                    sprintf('Archive entry would be written outside the target: %s', $relative),
                    1756131013,
                );
            }

            GeneralUtility::mkdir_deep(dirname($canonical));
            GeneralUtility::writeFile($canonical, (string)file_get_contents($file->getPathname()), true);
            ++$count;
        }

        if ($count === 0) {
            throw new \RuntimeException('Archive contained none of the expected dist/ files.', 1756131007);
        }

        return $count;
    }

    /**
     * The entry path relative to the tarball's `package/` root, or null if it is not
     * under one. npm tarballs put everything below `package/`; an entry that is not
     * there is not something this installer knows how to place.
     */
    private function relativePath(string $pathname): ?string
    {
        $normalised = str_replace('\\', '/', $pathname);
        $position = strpos($normalised, '/package/');
        if ($position === false) {
            return null;
        }

        $relative = substr($normalised, $position + strlen('/package/'));

        return $relative === '' ? null : $relative;
    }

    private function isWanted(string $relative): bool
    {
        if (!in_array(strtolower(pathinfo($relative, PATHINFO_EXTENSION)), self::ALLOWED_EXTENSIONS, true)) {
            return false;
        }

        foreach (self::WANTED_PREFIXES as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
