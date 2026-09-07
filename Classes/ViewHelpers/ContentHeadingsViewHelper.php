<?php

declare(strict_types=1);

namespace BalatD\KernUx\ViewHelpers;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Collects the headings of the content elements on one page column.
 *
 * A ViewHelper rather than a data processor: Content Blocks only reads a per-block
 * setup.typoscript from version 2.3, which is the TYPO3 14 line - so on TYPO3 13
 * there is no place to put it except the site set, and putting one block's query
 * there would leak it into every page.
 *
 * Only elements that actually render a section heading are listed. A block whose
 * heading is hidden (header_layout = 100) would be a link to nothing, and one on level 1
 * is the page's own title - listing that in a table of contents *of that page* is an
 * entry pointing at the top of the page it is already on.
 */
final class ContentHeadingsViewHelper extends AbstractViewHelper
{
    // Injected rather than fetched from the container, the way FormFieldAttributes and
    // FormFieldRequired already do it. ViewHelpers here take constructor arguments, so
    // reaching for makeInstance() was both inconsistent and the reason this class could
    // not be tested without a container.
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('pageUid', 'int', 'Page whose content is scanned.', true);
        $this->registerArgument('languageUid', 'int', 'Language of the records.', false, 0);
        $this->registerArgument('colPos', 'int', 'Content column to scan.', false, 0);
        $this->registerArgument('excludeUid', 'int', 'Record to leave out - normally the table of contents itself.', false, 0);
    }

    /**
     * @return list<array{uid: int, header: string, level: int}>
     */
    public function render(): array
    {
        $pageUid = $this->intArgument('pageUid');
        if ($pageUid <= 0) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');

        // Default restrictions cover deleted, hidden, start/endtime and access groups,
        // so a table of contents never advertises a block the visitor cannot see.
        $rows = $queryBuilder
            ->select('uid', 'header', 'header_layout')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('colPos', $queryBuilder->createNamedParameter($this->intArgument('colPos'), ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($this->intArgument('languageUid'), ParameterType::INTEGER)),
                $queryBuilder->expr()->neq('header', $queryBuilder->createNamedParameter('')),
                // 100 is core's "hidden": a link to a block with no visible heading
                // would lead nowhere. 1 is the page's own main heading, which is the
                // title of the page a table of contents sits on, not a section of it.
                $queryBuilder->expr()->notIn(
                    'header_layout',
                    $queryBuilder->createNamedParameter(['1', '100'], \Doctrine\DBAL\ArrayParameterType::STRING),
                ),
            )
            ->orderBy('sorting')
            ->executeQuery()
            ->fetchAllAssociative();

        $excludeUid = $this->intArgument('excludeUid');
        $headings = [];
        foreach ($rows as $row) {
            // Database rows are untyped, so each column is checked rather than cast -
            // a surprise value here would otherwise become uid 0 or the string "Array".
            $uid = $this->toInt($row['uid'] ?? null);
            $rawHeader = $row['header'] ?? null;
            $header = is_string($rawHeader) ? trim($rawHeader) : '';

            if ($uid === 0 || $uid === $excludeUid || $header === '') {
                continue;
            }

            $layout = $this->toInt($row['header_layout'] ?? null);

            $headings[] = [
                'uid' => $uid,
                'header' => $header,
                // 0 is core's "block default"; an anchor list only needs a relative
                // depth, and 2 is what every block falls back to.
                'level' => $layout > 0 && $layout < 6 ? $layout : 2,
            ];
        }

        return $headings;
    }

    private function intArgument(string $name): int
    {
        return $this->toInt($this->arguments[$name] ?? null);
    }

    private function toInt(mixed $value): int
    {
        return is_int($value) || is_string($value) || is_float($value) ? (int)$value : 0;
    }
}
