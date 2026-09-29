<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\ContentBlocks;

use PHPUnit\Framework\Attributes\Test;

/**
 * The table of contents, which is the one block that reads the page around it.
 *
 * It is also the one place where the anchor contract closes: every other block emits
 * `id="kern-content-<uid>"` from its own kux:uniqueId call, and this block builds hrefs
 * pointing at those ids from a second, independent call. Nothing but convention keeps
 * the two spellings the same, and if they drift every entry in every table of contents
 * becomes a link that goes nowhere - with no error anywhere.
 */
final class TocBlockTest extends AbstractContentBlockTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TocContent.csv');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function render(array $overrides = []): string
    {
        return $this->renderBlock('toc', array_merge([
            'uid' => 6,
            'pid' => 10,
            'sys_language_uid' => 0,
            'header' => 'Inhalt',
            'header_layout' => 2,
        ], $overrides));
    }

    #[Test]
    public function linksAtTheAnchorEveryOtherBlockEmits(): void
    {
        $rendered = $this->render();

        // The other half of this contract is asserted in ContentBlockContractTest, which
        // pins that a block with uid 42 renders id="kern-content-42".
        self::assertStringContainsString('href="#kern-content-1"', $rendered);
        self::assertStringContainsString('href="#kern-content-2"', $rendered);
    }

    #[Test]
    public function listsOnlyTheHeadingsAVisitorCanActuallyReach(): void
    {
        $rendered = $this->render();

        self::assertStringContainsString('Voraussetzungen', $rendered);
        self::assertStringContainsString('Gebühren', $rendered);
        // Hidden heading, page title, hidden record, deleted record.
        self::assertStringNotContainsString('Verstecktes', $rendered);
        self::assertStringNotContainsString('Seitentitel', $rendered);
        self::assertStringNotContainsString('Nur Redaktion', $rendered);
        self::assertStringNotContainsString('Gelöscht', $rendered);
    }

    #[Test]
    public function doesNotLinkToItself(): void
    {
        self::assertStringNotContainsString('href="#kern-content-6"', $this->render());
    }

    #[Test]
    public function namesTheNavigationLandmarkSoItCanBeToldApartFromTheMenu(): void
    {
        $rendered = $this->render(['tx_kernux_toc_label' => 'Auf dieser Seite']);

        // A page carries several nav landmarks, and a screen reader lists them by name.
        self::assertStringContainsString('<nav aria-label="Auf dieser Seite">', $rendered);
    }

    #[Test]
    public function fallsBackToATranslatedLabelWhenTheEditorGivesNone(): void
    {
        $rendered = $this->render();

        self::assertMatchesRegularExpression('/<nav aria-label="[^"]+">/', $rendered);
        self::assertStringNotContainsString('<nav aria-label="">', $rendered);
    }

    #[Test]
    public function rendersNoNavigationAtAllOnAPageWithNoHeadings(): void
    {
        // An empty table of contents is worse than none: it is a landmark a screen
        // reader announces and then finds nothing in.
        $rendered = $this->render(['pid' => 999]);

        self::assertStringNotContainsString('<nav', $rendered);
    }
}
