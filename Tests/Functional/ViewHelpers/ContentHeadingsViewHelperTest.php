<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\ViewHelpers;

use BalatD\KernUx\ViewHelpers\ContentHeadingsViewHelper;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The query behind the table of contents.
 *
 * This is the only database query in the extension, and every one of its filters is a
 * promise to a visitor rather than a detail: a table of contents that lists a hidden
 * element advertises something nobody can read, one that lists the block whose heading
 * the editor switched off links to nothing, and one that lists the page's own h1 sends
 * the reader to where they already are.
 *
 * None of that is visible from the rendered markup of a page that happens to have no
 * such records, which is why it is tested here against rows that do.
 */
final class ContentHeadingsViewHelperTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../ContentBlocks/Fixtures/TocContent.csv');
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<array{uid: int, header: string, level: int}>
     */
    private function collect(array $arguments = []): array
    {
        $subject = new ContentHeadingsViewHelper(GeneralUtility::makeInstance(ConnectionPool::class));
        $subject->setArguments($arguments + ['pageUid' => 10]);

        return $subject->render();
    }

    /**
     * @param list<array{uid: int, header: string, level: int}> $headings
     *
     * @return list<string>
     */
    private static function headersOf(array $headings): array
    {
        return array_map(static fn(array $heading): string => $heading['header'], $headings);
    }

    #[Test]
    public function listsTheHeadingsOfThePageInEditingOrder(): void
    {
        self::assertSame(
            ['Voraussetzungen', 'Gebühren', 'Inhalt'],
            self::headersOf($this->collect()),
        );
    }

    #[Test]
    public function leavesOutABlockWhoseHeadingTheEditorHid(): void
    {
        // header_layout 100 renders no heading at all, so an entry for it would be an
        // anchor pointing at an element with nothing to anchor to.
        self::assertNotContains('Verstecktes', self::headersOf($this->collect()));
    }

    #[Test]
    public function leavesOutThePagesOwnMainHeading(): void
    {
        // Level 1 is the page title. A table of contents *of this page* listing it is an
        // entry that sends the reader to the top of the page they are already on.
        self::assertNotContains('Seitentitel', self::headersOf($this->collect()));
    }

    #[Test]
    public function neverAdvertisesABlockTheVisitorCannotSee(): void
    {
        $headers = self::headersOf($this->collect());

        // The default restrictions carry this, which is exactly why it is worth a test:
        // dropping them would look like a harmless simplification of the query.
        self::assertNotContains('Nur Redaktion', $headers);
        self::assertNotContains('Gelöscht', $headers);
    }

    #[Test]
    public function staysWithinItsOwnColumnLanguageAndPage(): void
    {
        $headers = self::headersOf($this->collect());

        self::assertNotContains('In der Seitenspalte', $headers);
        self::assertNotContains('Andere Sprache', $headers);
        self::assertNotContains('Andere Seite', $headers);

        self::assertSame(['In der Seitenspalte'], self::headersOf($this->collect(['colPos' => 1])));
        self::assertSame(['Andere Sprache'], self::headersOf($this->collect(['languageUid' => 1])));
    }

    #[Test]
    public function leavesOutTheTableOfContentsItself(): void
    {
        // Without this the list's first entry is a link to the list.
        self::assertSame(
            ['Voraussetzungen', 'Gebühren'],
            self::headersOf($this->collect(['excludeUid' => 6])),
        );
    }

    #[Test]
    public function reportsTheLevelTheHeadingWillActuallyRenderAt(): void
    {
        $headings = $this->collect();

        // 0 means "the block decides", and every block decides on 2.
        self::assertSame(['uid' => 1, 'header' => 'Voraussetzungen', 'level' => 2], $headings[0]);
        self::assertSame(['uid' => 2, 'header' => 'Gebühren', 'level' => 3], $headings[1]);
    }

    #[Test]
    public function returnsNothingForAPageThatDoesNotExist(): void
    {
        $subject = new ContentHeadingsViewHelper(GeneralUtility::makeInstance(ConnectionPool::class));
        $subject->setArguments(['pageUid' => 0]);

        self::assertSame([], $subject->render());
    }
}
