<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Pins the viewport meta tag the site set puts on every frontend page.
 *
 * The PageView templates render a body, not a head, so `page.meta` in the site set is
 * the only seam that reaches the `<head>` of all four page templates at once - and it
 * is a seam a project can override without replacing the set. Without the directive a
 * phone keeps its ~980px desktop layout viewport, which sits just below KERN's own
 * 992px breakpoint: the small layout is rendered wide and scaled down, so the page can
 * only be zoomed and panned, never reflowed, and WCAG 1.4.10 fails. The existing axe
 * run cannot see this, because Playwright sets the browser viewport directly and the
 * layout viewport follows it with or without a meta tag - so this markup is a CONTRACT
 * and this test is the only guard.
 *
 * A unit test on purpose: it reads the files straight from disk, so it needs neither a
 * database nor a TYPO3 bootstrap and runs the same way under both supported majors.
 */
final class SiteSetSetupTest extends UnitTestCase
{
    private const EXT_ROOT = __DIR__ . '/../../..';

    #[Test]
    public function pageMetaViewportIsDeclaredInsideThePageObject(): void
    {
        self::assertSame('width=device-width, initial-scale=1', self::declaredViewport());
    }

    #[Test]
    public function pageMetaViewportAllowsZooming(): void
    {
        $viewport = self::declaredViewport();

        self::assertStringNotContainsString(
            'maximum-scale',
            $viewport,
            'A maximum-scale caps the zoom a low-vision user needs and fails WCAG 1.4.4',
        );
        self::assertStringNotContainsString(
            'user-scalable',
            $viewport,
            'user-scalable=no forbids zooming outright and fails WCAG 1.4.4',
        );
    }

    #[Test]
    public function styleguideAndPageRenderingAgreeOnTheViewport(): void
    {
        $file = self::EXT_ROOT . '/Resources/Private/Styleguide/Styleguide.html';
        $contents = (string)file_get_contents($file);

        $matched = preg_match('/<meta\s+name="viewport"\s+content="([^"]+)"/', $contents, $matches);
        if ($matched !== 1) {
            self::fail(
                "No viewport meta tag in {$file}: the standalone gallery must show components at "
                . 'the same layout viewport the real pages use',
            );
        }

        self::assertSame(
            $matches[1],
            self::declaredViewport(),
            'The gallery and the site set declare different viewports, so a component can look '
            . 'right in the gallery and wrong on a page',
        );
    }

    /**
     * The viewport the site set declares, proven to sit inside the `page` object.
     *
     * The indentation is load-bearing, not cosmetic: a top-level `meta.viewport` is valid
     * TypoScript that sets nothing on the `page` object, so matching it would let this test
     * pass while a phone still got the desktop layout viewport. Hence a required leading
     * indent plus an offset check against `page = PAGE`.
     */
    private static function declaredViewport(): string
    {
        $file = self::EXT_ROOT . '/Configuration/Sets/KernUx/setup.typoscript';
        $contents = (string)file_get_contents($file);

        $matched = preg_match(
            '/^[ \t]+meta\.viewport[ \t]*=[ \t]*(.+?)[ \t]*$/m',
            $contents,
            $matches,
            PREG_OFFSET_CAPTURE,
        );
        if ($matched !== 1) {
            self::fail(
                "No indented meta.viewport in {$file}: the directive must sit indented inside "
                . 'the "page {" block, because only there does it reach a rendered page',
            );
        }

        $pageObject = strpos($contents, 'page = PAGE');
        if ($pageObject === false) {
            self::fail("No \"page = PAGE\" in {$file}, so there is no page object to declare meta on");
        }

        self::assertGreaterThan(
            $pageObject,
            $matches[0][1],
            "meta.viewport sits before \"page = PAGE\" in {$file}, so it belongs to some other "
            . 'object and never reaches a page',
        );

        return $matches[1][0];
    }
}
