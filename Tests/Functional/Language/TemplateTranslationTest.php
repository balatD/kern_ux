<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Language;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Every translation key a template asks for has to resolve.
 *
 * A key that does not resolve produces an empty string, not an error - so a mistyped key
 * or a key in the wrong file looks exactly like a label nobody filled in. That has now
 * happened three times in this extension: the German source language on TYPO3 13, the
 * missing content-block field labels, and every backend preview at once, because
 * f:translate with `extensionName` looks in locallang.xlf while those keys live in
 * locallang_be.xlf. None of them failed anything.
 *
 * So this walks the templates, collects the keys they actually use, and resolves each
 * one. Keys assembled at runtime are counted and reported rather than checked: the test
 * cannot know what they will contain, and a silent skip would hide them.
 */
final class TemplateTranslationTest extends FunctionalTestCase
{
    private const DEFAULT_FILE = 'LLL:EXT:kern_ux/Resources/Private/Language/locallang.xlf:';
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    /**
     * @return array<string, array{string, string}>
     */
    public static function keys(): array
    {
        $root = dirname(__DIR__, 3);
        $patterns = [
            '/ContentBlocks/ContentElements/*/templates/*.html',
            '/Resources/Private/Components/*/*/*.html',
            '/Resources/Private/PageView/*/*.html',
            '/Resources/Private/Partials/*/*/*/*.html',
            '/Resources/Private/Templates/*/*.html',
        ];

        $cases = [];
        foreach ($patterns as $pattern) {
            foreach (glob($root . $pattern) ?: [] as $file) {
                $source = (string)file_get_contents($file);
                $relative = substr($file, strlen($root) + 1);

                // Tag syntax and inline syntax, with or without extensionName.
                preg_match_all('/key=(["\'])(?P<key>[^"\']+)\1/', $source, $tagMatches);
                preg_match_all('/key:\s*(["\'])(?P<key>[^"\']+)\1/', $source, $inlineMatches);

                foreach ([...$tagMatches['key'], ...$inlineMatches['key']] as $key) {
                    $cases[$relative . ' :: ' . $key] = [$key, $relative];
                }
            }
        }
        ksort($cases);

        return $cases;
    }

    #[Test]
    #[DataProvider('keys')]
    public function resolvesToSomething(string $key, string $file): void
    {
        if (str_contains($key, '{')) {
            // Assembled at runtime. The file it lives in is still checkable, and getting
            // that wrong is the mistake that actually happens.
            self::assertStringStartsWith(
                'LLL:EXT:kern_ux/Resources/Private/Language/',
                $key,
                "{$file} builds a key at runtime without naming the file it is in. "
                . 'f:translate with extensionName only ever looks in locallang.xlf, so a '
                . 'key from any other file has to be addressed by its full path.',
            );

            return;
        }

        $factory = $this->get(LanguageServiceFactory::class);
        self::assertInstanceOf(LanguageServiceFactory::class, $factory);

        // A bare key means "extensionName", which TYPO3 resolves against locallang.xlf.
        $path = str_starts_with($key, 'LLL:') ? $key : self::DEFAULT_FILE . $key;

        self::assertNotSame(
            '',
            $factory->create('default')->sL($path),
            "{$file} asks for '{$key}', which resolves to nothing. Either the key is "
            . 'missing, or it lives in a file other than the one being addressed.',
        );
    }
}
