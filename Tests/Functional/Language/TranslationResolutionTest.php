<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Language;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Proves the language files actually resolve, in both directions.
 *
 * LanguageCoverageTest reads the XLIFF from disk and can only say the keys are there.
 * This test asks TYPO3 itself, which is a different question: a label can be present
 * and still come back empty because of how the file is named, which language key TYPO3
 * treats as the default, or where it looks for the translation. That is what made a
 * German source language unusable on TYPO3 13 - the English frontend fell back to the
 * German <source> instead of finding a translation - so it is worth asserting rather
 * than assuming.
 */
final class TranslationResolutionTest extends FunctionalTestCase
{
    private const FE = 'LLL:EXT:kern_ux/Resources/Private/Language/locallang.xlf:';
    private const BE = 'LLL:EXT:kern_ux/Resources/Private/Language/locallang_be.xlf:';
    private const FORM = 'LLL:EXT:kern_ux/Resources/Private/Language/locallang_form.xlf:';
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function keys(): array
    {
        return [
            // Accessible names of landmarks and icon-only controls - invisible on the
            // page but the only thing a screen reader has to go on.
            'skip link'          => [self::FE . 'skipLink.main', 'Skip to content', 'Zum Inhalt springen'],
            'main navigation'    => [self::FE . 'header.mainNavigation', 'Main navigation', 'Hauptnavigation'],
            'menu toggle'        => [self::FE . 'header.showMenu', 'Show menu', 'Menü anzeigen'],
            'footer landmark'    => [self::FE . 'footer.label', 'Footer', 'Fußbereich'],
            'breadcrumb'         => [self::FE . 'breadcrumb.label', 'Breadcrumb', 'Brotkrumennavigation'],
            'dialog close'       => [self::FE . 'dialog.close', 'Close dialog', 'Dialog schließen'],
            'optional marker'    => [self::FE . 'label.optional', '- Optional', '- Optional'],
            'table of contents'  => [self::FE . 'toc.label', 'Table of contents', 'Inhaltsverzeichnis'],
            'caption language'   => [self::FE . 'mediaPlayer.captionsLanguage', 'en', 'de'],
            // Backend labels an editor reads.
            'backend layout'     => [self::BE . 'backendLayout.startpage', 'Home page', 'Startseite'],
            'content column'     => [self::BE . 'column.main', 'Content', 'Inhalt'],
            'preview divider'    => [self::BE . 'preview.divider', 'Divider', 'Trenner'],
            // The field label that first exposed the source-language defect.
            'date field day'     => [self::FORM . 'date.day', 'Day', 'Tag'],
        ];
    }

    #[Test]
    #[DataProvider('keys')]
    public function resolvesInEnglishAndGerman(string $key, string $english, string $german): void
    {
        $factory = $this->get(LanguageServiceFactory::class);
        self::assertInstanceOf(LanguageServiceFactory::class, $factory);

        self::assertSame($english, $factory->create('default')->sL($key), "English lookup of {$key}");
        self::assertSame($german, $factory->create('de')->sL($key), "German lookup of {$key}");
    }

    /**
     * Content-block field labels are resolved through the block's own labels.xlf, which
     * Content Blocks addresses by a generated path rather than one we write by hand -
     * so it is worth checking that path really lands on our file.
     *
     * The label sits in the content type's columnsOverrides, not in the base column:
     * the base keeps the bare field identifier as a fallback, which is why a missing
     * label shows the editor "aboveTheFold" instead of failing.
     */
    #[Test]
    public function resolvesContentBlockFieldLabels(): void
    {
        $factory = $this->get(LanguageServiceFactory::class);
        self::assertInstanceOf(LanguageServiceFactory::class, $factory);
        $de = $factory->create('de');
        $en = $factory->create('default');

        $expected = [
            ['kernux_accordion', 'tx_kernux_accordion_panels', 'Sections', 'Abschnitte'],
            ['kernux_progress', 'tx_kernux_progress_label', 'Label', 'Beschriftung'],
            ['kernux_media', 'tx_kernux_media_transcript', 'Transcript', 'Transkript'],
            ['kernux_image', 'tx_kernux_image_aboveTheFold', 'Load immediately', 'Sofort laden'],
            ['kernux_divider', 'tx_kernux_divider_semantic', 'Separates two topics', 'Trennt zwei Themen'],
            ['kernux_toc', 'tx_kernux_toc_label', 'Heading of the list', 'Überschrift der Liste'],
        ];

        foreach ($expected as [$cType, $column, $english, $german]) {
            $overrides = self::arrayAt('TCA', 'tt_content', 'types', $cType, 'columnsOverrides');
            self::assertArrayHasKey($column, $overrides, "{$column} has no override on {$cType}");

            $label = self::labelOf($overrides[$column] ?? null);
            self::assertStringStartsWith('LLL:', $label, "{$column} still carries a raw identifier: {$label}");

            self::assertSame($english, $en->sL($label), "English label of {$column}");
            self::assertSame($german, $de->sL($label), "German label of {$column}");
        }
    }

    /**
     * Every content block must have all of its own field labels wired through the
     * language file. A raw identifier here is the exact symptom of a forgotten label.
     */
    #[Test]
    public function noContentBlockFieldFallsBackToItsIdentifier(): void
    {
        $raw = [];
        foreach (self::arrayAt('TCA', 'tt_content', 'types') as $cType => $type) {
            $cType = (string)$cType;
            if (!str_starts_with($cType, 'kernux_') || !is_array($type)) {
                continue;
            }
            $overrides = $type['columnsOverrides'] ?? [];
            if (!is_array($overrides)) {
                continue;
            }
            foreach ($overrides as $column => $definition) {
                $column = (string)$column;
                if (!str_starts_with($column, 'tx_kernux_')) {
                    continue;
                }
                $label = self::labelOf($definition);
                if (!self::isTranslated($label)) {
                    $raw[] = "{$cType}.{$column} = '{$label}'";
                }
            }
        }

        self::assertSame([], $raw, 'These fields show the editor a raw identifier: ' . implode(', ', $raw));
    }

    /**
     * Collection children live in their own table rather than in tt_content, so they
     * need checking separately - and they are the majority of the fields an editor
     * actually types into.
     */
    #[Test]
    public function collectionChildFieldsAreLabelledToo(): void
    {
        $raw = [];
        $checked = 0;

        foreach (self::arrayAt('TCA') as $table => $definition) {
            $table = (string)$table;
            if (!str_starts_with($table, 'tx_kernux_') || !is_array($definition)) {
                continue;
            }
            $columns = $definition['columns'] ?? [];
            if (!is_array($columns)) {
                continue;
            }
            foreach ($columns as $column => $columnDefinition) {
                $label = self::labelOf($columnDefinition);
                if (self::isTranslated($label)) {
                    continue;
                }
                $raw[] = $table . '.' . (string)$column . " = '{$label}'";
            }
            ++$checked;
        }

        self::assertGreaterThan(0, $checked, 'No collection tables found - has the naming changed?');
        self::assertSame([], $raw, 'These collection fields show a raw identifier: ' . implode(', ', $raw));

        // The rule above is a "nothing is broken" check and would also pass on an empty
        // label, so one child field is asserted positively to keep it from going vacuous.
        $factory = $this->get(LanguageServiceFactory::class);
        self::assertInstanceOf(LanguageServiceFactory::class, $factory);
        $columns = self::arrayAt('TCA', 'tx_kernux_accordion_panels', 'columns');
        $label = self::labelOf($columns['title'] ?? null);

        self::assertStringStartsWith('LLL:', $label, 'The accordion panel title lost its label reference');
        self::assertSame('Heading', $factory->create('default')->sL($label));
        self::assertSame('Überschrift', $factory->create('de')->sL($label));
    }

    /**
     * Validator messages go through translateErrorMessage(), which looks in
     * locallang.xlf unless it is given a full path - so these keys are addressed
     * explicitly and that has to keep working.
     */
    #[Test]
    public function resolvesFormValidationMessages(): void
    {
        $factory = $this->get(LanguageServiceFactory::class);
        self::assertInstanceOf(LanguageServiceFactory::class, $factory);

        $key = self::FORM . 'validation.error.kernDate.empty';

        self::assertNotSame('', $factory->create('default')->sL($key), "{$key} does not resolve");
        self::assertNotSame('', $factory->create('de')->sL($key));
    }

    /**
     * A label that carries no colon is a bare field identifier, not a reference into a
     * language file. Both reference forms qualify: our own "LLL:EXT:...:key" and the
     * shorthand TYPO3 14 uses for core's own columns, "core.db.general:enabled".
     */
    private static function isTranslated(string $label): bool
    {
        return $label === '' || str_contains($label, ':');
    }

    /**
     * $GLOBALS is untyped, so every step into the TCA has to be proven rather than
     * assumed - which also turns a renamed key into a clear failure instead of a
     * silent empty result.
     *
     * @return array<array-key, mixed>
     */
    private static function arrayAt(string ...$path): array
    {
        $value = $GLOBALS;
        $walked = [];

        foreach ($path as $key) {
            self::assertIsArray($value, 'Not an array: $GLOBALS[' . implode('][', $walked) . ']');
            self::assertArrayHasKey($key, $value, 'Missing: $GLOBALS[' . implode('][', [...$walked, $key]) . ']');
            $value = $value[$key];
            $walked[] = $key;
        }

        self::assertIsArray($value, 'Not an array: $GLOBALS[' . implode('][', $walked) . ']');

        return $value;
    }

    private static function labelOf(mixed $definition): string
    {
        if (!is_array($definition)) {
            return '';
        }
        $label = $definition['label'] ?? null;

        return is_string($label) ? $label : '';
    }
}
