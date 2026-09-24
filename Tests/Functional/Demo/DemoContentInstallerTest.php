<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Demo;

use BalatD\KernUx\Demo\DemoContentInstaller;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Installing the demo page tree, which is also a conformance test for every block.
 *
 * The fixture places one element of every content type through the DataHandler, so a
 * config.yaml that TCA rejects - a bad field type, a collection whose child table never
 * got generated, a required field with no value - shows up here as a type missing from
 * the database rather than as a blank element somebody finds in the backend weeks
 * later. Nothing else in the suite writes a record of every block.
 *
 * Two tests, each asserting several things, and that is deliberate. A full install is a
 * DataHandler run over the whole tree plus the generated demo files, and it costs
 * several seconds; one assertion per test would multiply that cost by five for no extra
 * coverage. So one test covers everything a single install can show, and one covers the
 * lifecycle, which is the only thing that genuinely needs more than one.
 */
final class DemoContentInstallerTest extends FunctionalTestCase
{
    private const ROOT_PAGE = 1;
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // The testing framework creates the fileadmin directory but no storage record,
        // and without a default storage the generated demo images cannot be written.
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DefaultStorage.csv');
    }

    private function installer(): DemoContentInstaller
    {
        $installer = $this->get(DemoContentInstaller::class);
        self::assertInstanceOf(DemoContentInstaller::class, $installer);

        return $installer;
    }

    private static function countRows(string $table, string $where = '1=1'): int
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable($table);

        $count = $connection->executeQuery("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchOne();
        self::assertIsNumeric($count);

        return (int)$count;
    }

    #[Test]
    public function installsEveryBlockWithoutTheDataHandlerRefusingAnyOfThem(): void
    {
        $backendUserBefore = $GLOBALS['BE_USER'] ?? null;

        $created = $this->installer()->install(self::ROOT_PAGE);

        self::assertNotSame([], $created, 'The installer reported creating nothing at all.');
        self::assertGreaterThan(0, self::countRows('pages', 'deleted = 0 AND uid <> ' . self::ROOT_PAGE));
        self::assertGreaterThan(0, self::countRows('tt_content', 'deleted = 0'));

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');

        // A block the DataHandler refuses never reaches the database, so its content
        // type simply goes missing from this list - which is what makes one install a
        // check on all 24 config.yaml files at once.
        $types = $connection
            ->executeQuery('SELECT DISTINCT CType FROM tt_content WHERE deleted = 0')
            ->fetchFirstColumn();
        foreach ($types as $type) {
            self::assertIsString($type);
            self::assertStringStartsWith('kernux_', $type);
        }
        self::assertGreaterThanOrEqual(
            20,
            count($types),
            'The demo used to place one element of nearly every block; it now covers far fewer.',
        );

        // The fixture links between its own pages by key, because the uids only exist
        // once the tree has been written. A surviving placeholder is a dead link.
        $unresolved = $connection
            ->executeQuery("SELECT COUNT(*) FROM tt_content WHERE bodytext LIKE '%{page:%'")
            ->fetchOne();
        self::assertIsNumeric($unresolved);
        self::assertSame(0, (int)$unresolved, 'A {page:...} placeholder was never substituted.');

        // This is a shared service writing to global state: leaving its own user behind
        // would silently change who every later DataHandler run acts as.
        self::assertSame($backendUserBefore, $GLOBALS['BE_USER'] ?? null);
    }

    #[Test]
    public function survivesBeingInstalledRemovedAndInstalledAgain(): void
    {
        $installer = $this->installer();

        $installer->install(self::ROOT_PAGE);
        $afterFirstInstall = self::countRows('pages', 'deleted = 0');

        $installer->remove(self::ROOT_PAGE);

        // The demo has several top-level pages, and a cleanup that stops after the first
        // leaves the rest behind to collide with the next install.
        self::assertLessThan($afterFirstInstall, self::countRows('pages', 'deleted = 0'));
        self::assertSame(
            0,
            self::countRows('tt_content', 'deleted = 0'),
            'Content survived the removal of the pages it lived on.',
        );

        $installer->install(self::ROOT_PAGE);
        self::assertSame($afterFirstInstall, self::countRows('pages', 'deleted = 0'));
    }
}
