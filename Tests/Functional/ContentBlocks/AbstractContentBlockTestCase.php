<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\ContentBlocks;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;

/**
 * Base class for tests that render a Content Block's real frontend template.
 *
 * What such a test can cover is bounded, and the boundary is worth stating once here
 * rather than rediscovering it per block. Measured against the actual harness:
 *
 *   - A rich-text field reaches f:format.html with a parseFuncTSPath, which calls
 *     ContentObjectRenderer::setRequest() - non-nullable, so it fatals without a
 *     frontend request.
 *   - A Link field reaches typolink, which throws "PSR-7 request is missing in
 *     ContentObjectRenderer".
 *   - An image needs a real FAL FileReference, and there is no storage fixture here.
 *   - The sitemap block is one f:cObject and cannot render at all.
 *
 * So fixtures leave bodytext, Link and image fields empty on purpose. That is not a
 * half-finished fixture - completing one trades a test that runs for a test that
 * fatals, and the mapping between {data} and the components, which is the part each
 * block actually owns, renders perfectly well without them.
 *
 * File and DateTime fields are a happy exception: the templates read them as plain
 * property paths, so an ordinary PHP array satisfies them and the media and downloads
 * blocks are fully reachable.
 */
abstract class AbstractContentBlockTestCase extends AbstractComponentTestCase
{
    /**
     * @param array<string, mixed> $data
     */
    protected function renderBlock(string $block, array $data): string
    {
        $template = dirname(__DIR__, 3) . '/ContentBlocks/ContentElements/' . $block . '/templates/frontend.html';
        self::assertFileExists($template, "The {$block} block has no frontend template.");

        return $this->renderSource((string)file_get_contents($template), ['data' => $data]);
    }
}
