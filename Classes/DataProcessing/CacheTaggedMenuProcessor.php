<?php

declare(strict_types=1);

namespace BalatD\KernUx\DataProcessing;

use TYPO3\CMS\Core\Cache\CacheDataCollector;
use TYPO3\CMS\Core\Cache\CacheTag;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;
use TYPO3\CMS\Frontend\DataProcessing\MenuProcessor;

/**
 * Core's menu processor, plus a cache tag for every page the menu read.
 *
 * TYPO3 tags a cached page with the page it renders - `pageId_<uid>` for the page, its
 * contentPid and its localized uid, in TypoScriptFrontendController. Nothing tags the
 * pages a *menu* reads. Neither MenuProcessor nor AbstractMenuContentObject registers a
 * single cache tag (checked against 13.4.35), so a menu built from somewhere else in the
 * tree is baked into the cached page and stays there.
 *
 * For this extension that is not a corner case: the site set builds five menus on every
 * page, three of them `special = directory` pointing at root pages configured in the
 * site settings, and the main navigation runs two levels with expandAll. Renaming a
 * footer page, hiding it, or adding one below it changes what every page on the site
 * should show - and changes nothing at all, until each of those pages happens to be
 * flushed for some other reason. The symptom is a footer that is right on the page the
 * editor was on and stale everywhere else, which reads as a caching bug with no cause.
 *
 * So this wraps the core processor and tags what it read. A tag per page rather than one
 * blanket tag for the menu: editing one footer page should flush the pages that show it,
 * not every page on the site.
 *
 * There is a second, broader answer: the `frontend.cache.autoTagging` feature toggle makes
 * PageLinkBuilder tag every page that gets linked to, menus included. It is off by default
 * in 13.4, it is a project-wide decision with effects far beyond menus, and an extension
 * has no business switching it on for somebody else - so this does not depend on it. When
 * a project does enable it the two overlap: autoTagging writes `pages_<uid>`, this writes
 * `pageId_<uid>`, DataHandler clears both, and the duplication costs a few rows.
 */
final class CacheTaggedMenuProcessor implements DataProcessorInterface
{
    public function __construct(
        private readonly MenuProcessor $menuProcessor,
    ) {}

    /**
     * @param array<string, mixed> $contentObjectConfiguration
     * @param array<string, mixed> $processorConfiguration
     * @param array<string, mixed> $processedData
     *
     * @return array<string, mixed>
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData,
    ): array {
        // MenuProcessor::process() is declared as a bare `array`, so the shape has to be
        // restated on the way back out.
        /** @var array<string, mixed> $processedData */
        $processedData = $this->menuProcessor->process(
            $cObj,
            $contentObjectConfiguration,
            $processorConfiguration,
            $processedData,
        );

        $target = $processorConfiguration['as'] ?? 'menu';
        $menu = is_string($target) ? ($processedData[$target] ?? null) : null;
        if (!is_array($menu)) {
            return $processedData;
        }

        $collector = $cObj->getRequest()->getAttribute('frontend.cache.collector');
        if (!$collector instanceof CacheDataCollector) {
            return $processedData;
        }

        foreach (self::pageUidsOf($menu) as $uid) {
            $collector->addCacheTags(new CacheTag('pageId_' . $uid));
        }

        return $processedData;
    }

    /**
     * Every page uid in the menu, at any depth.
     *
     * Each item carries the page record under `data`, and its sub-pages under
     * `children` - the shape MenuProcessor documents and the templates already read.
     *
     * @param array<array-key, mixed> $menu
     *
     * @return list<int>
     */
    private static function pageUidsOf(array $menu): array
    {
        $uids = [];
        foreach ($menu as $item) {
            if (!is_array($item)) {
                continue;
            }

            $data = $item['data'] ?? null;
            $uid = is_array($data) ? ($data['uid'] ?? null) : null;
            if (is_int($uid) || (is_string($uid) && $uid !== '')) {
                $uids[] = (int)$uid;
            }

            $children = $item['children'] ?? null;
            if (is_array($children)) {
                foreach (self::pageUidsOf($children) as $childUid) {
                    $uids[] = $childUid;
                }
            }
        }

        return array_values(array_unique($uids));
    }
}
