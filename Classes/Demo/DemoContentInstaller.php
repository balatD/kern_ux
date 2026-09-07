<?php

declare(strict_types=1);

namespace BalatD\KernUx\Demo;

use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Installs the demo page tree and its content.
 *
 * Everything goes through the DataHandler rather than straight into the database. That
 * costs speed and buys correctness: slugs, sorting, reference indexes and the inline
 * relations of a Content Blocks collection are all things the DataHandler maintains, and
 * a fixture written past it describes records the backend would not have accepted. It
 * also means the demo is a genuine test of the content blocks - a field the TCA rejects
 * fails here instead of looking fine until an editor opens the form.
 *
 * Two passes, because content needs page uids that only exist once the pages are saved:
 * pages first, then content with {page:<key>} placeholders resolved.
 */
final class DemoContentInstaller
{
    /**
     * Backend layouts defined in page TSconfig are addressed with this prefix, and
     * PAGEVIEW picks the template from the name behind it.
     */
    private const LAYOUT_PREFIX = 'pagets__';

    /**
     * colPos is fixed project-wide and mirrors the backend layouts.
     */
    private const COLUMNS = ['main' => 0, 'hero' => 1, 'aside' => 2, 'teaser' => 3];

    /** @var array<string, int> */
    private array $pageUids = [];

    /** @var array<string, int> */
    private array $fileUids = [];

    /**
     * Pages with the uid they were given, flattened in creation order. Filled by the
     * first pass so the second does not have to walk the tree again.
     *
     * @var list<array<string, mixed>>
     */
    private array $created = [];

    private int $newIdCounter = 0;

    public function __construct(private readonly DemoAssetFactory $assetFactory) {}

    /**
     * Removes a previously installed demo tree, so the command can be run again.
     * Recognised by the root page's slug rather than by a marker field: the fixture owns
     * that slug, and nothing else in a site should be using it.
     *
     * @return int Number of page trees removed.
     */
    public function remove(int $rootPageId): int
    {
        $this->initializeBackendUser();

        $slug = '/' . trim($this->rootSlug(), '/');
        $uids = $this->findPagesBySlug($rootPageId, $slug);

        foreach ($uids as $uid) {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start([], ['pages' => [$uid => ['delete' => 1]]]);
            $dataHandler->process_cmdmap();
        }

        return count($uids);
    }

    /**
     * @return array{pages: int, records: int, files: int, keys: array<string, int>}
     */
    public function install(int $rootPageId): array
    {
        $this->pageUids = [];
        $this->fileUids = [];
        $this->created = [];
        $this->newIdCounter = 0;

        $fixture = $this->fixture();
        $previousBackendUser = $this->initializeBackendUser();

        // $GLOBALS['BE_USER'] is global state, and this class is a shared service, so
        // whatever it puts there has to come back off again - otherwise one call to
        // install() silently changes who every later DataHandler run in the same process
        // acts as. Harmless for the one-shot CLI command this was written for, wrong for
        // anything else that calls it.
        try {
            $this->fileUids = $this->assetFactory->create($this->mapOfMaps($this->definitions($fixture, 'files')));
            $pages = $this->createPages($this->pageList($fixture), $rootPageId);
            $records = $this->createContent();
        } finally {
            $this->restoreBackendUser($previousBackendUser);
        }

        return [
            'pages' => $pages,
            'records' => $records,
            'files' => count($this->fileUids),
            'keys' => $this->pageUids,
        ];
    }

    /**
     * One DataHandler run per page, because each needs the uid of the page before it.
     *
     * A new record goes to the *top* of its level when pid is a page id, so passing the
     * page id for every sibling would produce the fixture order reversed. The convention
     * for "after this record" is a negative pid carrying the previous record's uid, and
     * that is what keeps the tree in the order it is written in.
     *
     * @param list<array<string, mixed>> $pages
     */
    private function createPages(array $pages, int $parentId, string $parentSlug = ''): int
    {
        $created = 0;
        $previousSibling = 0;

        foreach ($pages as $page) {
            $newId = $this->newId('page');
            // Composed from the parent's slug rather than taken verbatim: an explicit
            // slug is used as given, so without this every demo page would end up
            // directly under the site root.
            $slug = $parentSlug . '/' . trim($this->string($page, 'slug'), '/');
            $record = [
                'pid' => $previousSibling > 0 ? -$previousSibling : $parentId,
                'title' => $this->string($page, 'title'),
                'slug' => $slug,
                'doktype' => 1,
                'hidden' => 0,
            ];

            // A page whose children feed the footer or the service menu has no business
            // in the main navigation itself.
            if (($page['navHide'] ?? false) === true) {
                $record['nav_hide'] = 1;
            }

            $navIcon = $this->string($page, 'navIcon');
            if ($navIcon !== '') {
                $record['tx_kernux_nav_icon'] = $navIcon;
            }

            $layout = $this->string($page, 'layout');
            if ($layout !== '') {
                $record['backend_layout'] = self::LAYOUT_PREFIX . $layout;
                // Inherited by subpages that set nothing themselves, which is what keeps
                // the navigation subtrees from falling back to core's default template.
                $record['backend_layout_next_level'] = self::LAYOUT_PREFIX . $layout;
            }

            $uid = $this->persist(['pages' => [$newId => $record]], $newId, 'pages');
            $previousSibling = $uid;
            ++$created;

            $key = $this->string($page, 'key');
            if ($key !== '') {
                $this->pageUids[$key] = $uid;
            }
            // Remembered so the second pass finds the page it belongs to without
            // repeating the tree walk.
            $page['__uid'] = $uid;
            $this->created[] = $page;

            $created += $this->createPages($this->childList($page), $uid, $slug);
        }

        return $created;
    }

    private function createContent(): int
    {
        $created = 0;

        foreach ($this->created as $page) {
            $uid = $page['__uid'] ?? null;
            if (!is_int($uid)) {
                continue;
            }
            $content = $page['content'] ?? null;
            if (!is_array($content)) {
                continue;
            }

            foreach ($content as $column => $items) {
                $colPos = self::COLUMNS[(string)$column] ?? 0;
                if (!is_array($items)) {
                    continue;
                }
                $previous = 0;
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    /** @var array<string, mixed> $item */
                    $previous = $this->createContentElement($item, $uid, $colPos, $previous);
                    ++$created;
                }
            }
        }

        return $created;
    }

    /**
     * @param array<string, mixed> $item
     * @param int $previous uid of the element this one follows, 0 for the first in its column
     * @return int uid of the created element
     */
    private function createContentElement(array $item, int $pageUid, int $colPos, int $previous): int
    {
        $newId = $this->newId('content');
        $record = [
            'pid' => $previous > 0 ? -$previous : $pageUid,
            'CType' => $this->string($item, 'type'),
            'colPos' => $colPos,
            'header' => $this->string($item, 'header'),
        ];

        $headerLayout = $item['header_layout'] ?? null;
        if (is_int($headerLayout)) {
            $record['header_layout'] = $headerLayout;
        }

        // Placed first on purpose: DataHandler walks the tables in the order they appear
        // (pages aside), so a child table listed before tt_content would be written while
        // the parent is still a NEW id - and its relation would end up pointing at 0.
        $data = ['tt_content' => []];

        foreach ($this->definitions($item, 'fields') as $field => $value) {
            $record[(string)$field] = is_string($value) ? $this->resolvePlaceholders($value) : $value;
        }

        // A page reference is stored as a plain uid, not as a typolink.
        foreach ($this->definitions($item, 'refs') as $field => $pageKey) {
            if (!is_string($pageKey)) {
                continue;
            }
            $referenced = $this->pageUids[$pageKey] ?? null;
            if (is_int($referenced)) {
                $record[(string)$field] = $referenced;
            }
        }

        foreach ($this->definitions($item, 'collections') as $field => $rows) {
            if (!is_array($rows)) {
                continue;
            }
            $childIds = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $childId = $this->newId('child');
                $childIds[] = $childId;
                /** @var array<string, mixed> $row */
                // The collection's child table is named exactly like the field.
                $data[(string)$field][$childId] = $this->collectionRow($row, $pageUid);
            }
            $record[(string)$field] = implode(',', $childIds);
        }

        foreach ($this->definitions($item, 'files') as $field => $references) {
            if (!is_array($references)) {
                continue;
            }
            $referenceIds = [];
            foreach ($references as $reference) {
                if (!is_array($reference)) {
                    continue;
                }
                /** @var array<string, mixed> $reference */
                $fileUid = $this->fileUids[$this->string($reference, 'key')] ?? null;
                if (!is_int($fileUid)) {
                    continue;
                }
                $referenceId = $this->newId('reference');
                $referenceIds[] = $referenceId;
                // No uid_foreign and no sorting_foreign: both are passthrough fields, so a
                // NEW id written there is not substituted and would be stored as 0.
                // DataHandler fills them itself from the parent's relation list.
                $data['sys_file_reference'][$referenceId] = [
                    'pid' => $pageUid,
                    'uid_local' => $fileUid,
                    'tablenames' => 'tt_content',
                    'fieldname' => (string)$field,
                    'description' => $this->string($reference, 'description'),
                ];
            }
            $record[(string)$field] = implode(',', $referenceIds);
        }

        $data['tt_content'] = [$newId => $record];

        return $this->persist($data, $newId, 'tt_content');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function collectionRow(array $row, int $pageUid): array
    {
        // The parent pointer is deliberately not set here: it is a passthrough field, so a
        // NEW id would be stored as 0. DataHandler resolves it from the list of ids in the
        // parent's own field once both records exist.
        $record = ['pid' => $pageUid];

        foreach ($row as $field => $value) {
            $field = (string)$field;
            // A card's image is given as a file key and becomes a reference, not a value.
            if ($field === 'image' && is_string($value)) {
                continue;
            }
            $record[$field] = is_string($value) ? $this->resolvePlaceholders($value) : $value;
        }

        return $record;
    }

    /**
     * {page:<key>} becomes a typolink to that page. Written as a placeholder because a
     * fixture cannot know uids, and hard-coding them would break on every reinstall.
     */
    private function resolvePlaceholders(string $value): string
    {
        return (string)preg_replace_callback(
            '/\{page:([A-Za-z0-9_-]+)\}/',
            function (array $matches): string {
                $uid = $this->pageUids[$matches[1]] ?? null;

                return is_int($uid) ? 't3://page?uid=' . $uid : '';
            },
            $value,
        );
    }

    /**
     * @param array<string, array<string, mixed>> $data
     */
    private function persist(array $data, string $newId, string $table): int
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($data, []);
        $dataHandler->process_datamap();

        if ($dataHandler->errorLog !== []) {
            // Encoded as a whole rather than joined entry by entry: DataHandler's error
            // log is annotated differently on the two supported majors, so anything that
            // assumes an entry's type fails static analysis on one of them.
            throw new \RuntimeException(
                'The DataHandler refused a demo record in ' . $table . ': '
                . (string)json_encode($dataHandler->errorLog),
                1756200010,
            );
        }

        $uid = $dataHandler->substNEWwithIDs[$newId] ?? null;
        if (!is_numeric($uid)) {
            throw new \RuntimeException('The DataHandler created no record in ' . $table . '.', 1756200011);
        }

        return (int)$uid;
    }

    /**
     * A placeholder id for a record that does not exist yet.
     *
     * Underscore-free on purpose, and that is not cosmetic: when DataHandler resolves the
     * relations it treats an underscore inside a NEW id as the "table_uid" notation of a
     * group field, splits the id there and derives a table name from the left-hand part.
     * An id like "NEW_kernux_child_5" therefore resolves to uid 5 in a table called
     * "NEW_kernux_child", and every relation silently ends up as 0.
     */
    private function newId(string $prefix): string
    {
        return 'NEWkernux' . ucfirst($prefix) . ++$this->newIdCounter;
    }

    /**
     * The DataHandler checks permissions against a backend user and attributes the
     * records to it, and on the command line there is none. A hand-made stand-in is not
     * enough: DataHandler consults the resolved group data, so a user object without it
     * is refused with "Attempt to modify table without permission".
     *
     * CommandLineUserAuthentication is TYPO3's own answer to this - it authenticates the
     * "_cli_" user, creating it once if it does not exist yet, and resolves its
     * permissions properly.
     */
    /**
     * @return array{replaced: bool, previous: mixed} what restoreBackendUser() needs
     */
    private function initializeBackendUser(): array
    {
        $existing = $GLOBALS['BE_USER'] ?? null;
        // Merely being a BackendUserAuthentication is not enough: the CLI bootstrap
        // leaves an unauthenticated one in place, and DataHandler then refuses every
        // write because the resolved permissions are empty.
        if ($existing instanceof BackendUserAuthentication && ($existing->user['uid'] ?? 0) > 0) {
            return ['replaced' => false, 'previous' => $existing];
        }

        $backendUser = GeneralUtility::makeInstance(CommandLineUserAuthentication::class);
        $backendUser->authenticate();
        $GLOBALS['BE_USER'] = $backendUser;

        return ['replaced' => true, 'previous' => $existing];
    }

    /**
     * @param array{replaced: bool, previous: mixed} $state
     */
    private function restoreBackendUser(array $state): void
    {
        if (!$state['replaced']) {
            return;
        }

        if ($state['previous'] === null) {
            unset($GLOBALS['BE_USER']);

            return;
        }

        $GLOBALS['BE_USER'] = $state['previous'];
    }

    private function rootSlug(): string
    {
        $pages = $this->pageList($this->fixture());
        $first = $pages[0] ?? [];

        return $this->string($first, 'slug');
    }

    /**
     * @return list<int>
     */
    private function findPagesBySlug(int $rootPageId, string $slug): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('pages');
        $rows = $connection->select(['uid'], 'pages', ['pid' => $rootPageId, 'slug' => $slug, 'deleted' => 0])
            ->fetchAllAssociative();

        $uids = [];
        foreach ($rows as $row) {
            $uid = $row['uid'] ?? null;
            if (is_numeric($uid)) {
                $uids[] = (int)$uid;
            }
        }

        return $uids;
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(): array
    {
        $path = 'EXT:kern_ux/Resources/Private/Demo/DemoContent.yaml';
        $absolute = GeneralUtility::getFileAbsFileName($path);
        if ($absolute === '' || !is_file($absolute)) {
            throw new \RuntimeException('Demo fixture not found at ' . $path . '.', 1756200012);
        }

        $parsed = Yaml::parseFile($absolute);
        if (!is_array($parsed)) {
            throw new \RuntimeException('Demo fixture is not a YAML mapping.', 1756200013);
        }
        /** @var array<string, mixed> $parsed */
        return $parsed;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, array<string, mixed>>
     */
    private function mapOfMaps(array $source): array
    {
        $maps = [];
        foreach ($source as $key => $value) {
            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $maps[(string)$key] = $value;
            }
        }

        return $maps;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    private function definitions(array $source, string $key): array
    {
        $value = $source[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }
        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $source
     * @return list<array<string, mixed>>
     */
    private function pageList(array $source): array
    {
        return $this->listOfMaps($source['pages'] ?? null);
    }

    /**
     * @param array<string, mixed> $page
     * @return list<array<string, mixed>>
     */
    private function childList(array $page): array
    {
        return $this->listOfMaps($page['children'] ?? null);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listOfMaps(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $list = [];
        foreach ($value as $entry) {
            if (is_array($entry)) {
                /** @var array<string, mixed> $entry */
                $list[] = $entry;
            }
        }

        return $list;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function string(array $source, string $key): string
    {
        $value = $source[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
