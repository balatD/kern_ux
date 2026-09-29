<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Demo;

use BalatD\KernUx\Demo\DemoAssetFactory;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The demo files, which are drawn rather than shipped.
 *
 * KERN's own media is licensed and this extension ships no binaries, so the demo
 * generates its own image, audio, caption and document files from bytes. Each one has
 * to be a genuine file of its type: a PNG the image processor can scale, a WebVTT the
 * media player can load as a caption track. A file that is merely named .png is not
 * rejected anywhere - it fails later, in image processing or in the browser, far from
 * the thing that produced it.
 */
final class DemoAssetFactoryTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DefaultStorage.csv');
    }

    private function factory(): DemoAssetFactory
    {
        $factory = $this->get(DemoAssetFactory::class);
        self::assertInstanceOf(DemoAssetFactory::class, $factory);

        return $factory;
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     *
     * @return array<string, string>
     */
    private function contentsOf(array $definitions): array
    {
        $uids = $this->factory()->create($definitions);
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

        $contents = [];
        foreach ($uids as $key => $uid) {
            $contents[$key] = $resourceFactory->getFileObject($uid)->getContents();
        }

        return $contents;
    }

    #[Test]
    #[RequiresPhpExtension('gd')]
    public function drawsARealPng(): void
    {
        $contents = $this->contentsOf(['rathaus' => ['type' => 'image', 'title' => 'Rathaus']]);

        // The eight-byte PNG signature. Without it the image processor cannot scale the
        // file, and every demo page that uses it renders a broken image.
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $contents['rathaus'] ?? '');
    }

    #[Test]
    public function writesAWavWithItsRiffHeader(): void
    {
        $contents = $this->contentsOf(['rede' => ['type' => 'audio', 'title' => 'Rede']]);
        $audio = $contents['rede'] ?? '';

        self::assertStringStartsWith('RIFF', $audio);
        self::assertStringContainsString('WAVE', $audio);
    }

    #[Test]
    public function writesCaptionsAsWebvtt(): void
    {
        $contents = $this->contentsOf(['untertitel' => ['type' => 'captions', 'title' => 'Untertitel']]);

        // The first line has to be exactly this or the browser refuses the track and
        // the media player silently loses its captions.
        self::assertStringStartsWith('WEBVTT', $contents['untertitel'] ?? '');
    }

    #[Test]
    public function writesADocumentAsAPdf(): void
    {
        $contents = $this->contentsOf(['merkblatt' => ['type' => 'pdf', 'title' => 'Merkblatt']]);

        self::assertStringStartsWith('%PDF-', substr($contents['merkblatt'] ?? '', 0, 16));
    }

    #[Test]
    public function keepsTheTitleWhereTheTemplatesReadIt(): void
    {
        $uids = $this->factory()->create([
            'rathaus' => ['type' => 'image', 'title' => 'Das Rathaus', 'alternative' => 'Ein Rathaus von vorn'],
        ]);

        $file = GeneralUtility::makeInstance(ResourceFactory::class)->getFileObject($uids['rathaus']);

        // Title and alt live in the file metadata, not on the file, and that is where
        // the templates read them - a demo that skipped this shows empty alt text.
        self::assertSame('Das Rathaus', $file->getProperty('title'));
        self::assertSame('Ein Rathaus von vorn', $file->getProperty('alternative'));
    }
}
