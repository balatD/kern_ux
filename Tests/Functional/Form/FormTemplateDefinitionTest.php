<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Form;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Form\Domain\Factory\ArrayFormFactory;
use TYPO3\CMS\Form\Mvc\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The two form definitions this extension ships as templates.
 *
 * A form definition is data, not code: nothing loads it until an editor picks it in the
 * plugin, and a wrong element type or an unknown validator then surfaces as an exception
 * on a live page. The form editor will not even list a definition it cannot parse, so
 * the failure mode is a template that is simply absent with no explanation.
 *
 * The label check is the other half. These definitions carry English text inline and
 * their real labels through a translation file - the same rule the rest of the extension
 * follows, and for the same reason: TYPO3 13 treats `en` as the default key and reads
 * <source> directly, so a German label written into the YAML would turn an English page
 * German. A missing key fails silently the other way, showing the English placeholder to
 * a German visitor, which is why every one is asserted here rather than spot-checked.
 */
final class FormTemplateDefinitionTest extends FunctionalTestCase
{
    private const EXT_ROOT = __DIR__ . '/../../..';

    private const TRANSLATION_FILE = self::EXT_ROOT . '/Resources/Private/Language/locallang_formtemplates.xlf';
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function formDefinitions(): array
    {
        $files = glob(self::EXT_ROOT . '/Configuration/Form/Forms/*.form.yaml') ?: [];
        self::assertNotSame([], $files, 'No form templates found, so this test proves nothing.');

        $cases = [];
        foreach ($files as $file) {
            $cases[basename($file)] = [$file];
        }

        return $cases;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // ext:form resolves its prototype through Extbase's ConfigurationManager, which
        // reads the request from $GLOBALS and throws without one.
        $site = new Site('kern', 1, [
            'base' => 'https://example.com/',
            'languages' => [
                ['languageId' => 0, 'title' => 'English', 'locale' => 'en_US.UTF-8', 'base' => '/'],
            ],
        ]);

        $typoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $typoScript->setSetupTree(new RootNode());
        $typoScript->setSetupArray(self::formTypoScript());
        $typoScript->setConfigTree(new RootNode());
        $typoScript->setConfigArray([]);

        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.com/', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getDefaultLanguage())
            ->withAttribute('frontend.typoscript', $typoScript)
            ->withAttribute('frontend.user', new FrontendUserAuthentication())
            ->withAttribute('extbase', new ExtbaseRequestParameters());
    }

    #[Test]
    #[DataProvider('formDefinitions')]
    public function buildsIntoAFormTheStandardPrototypeAccepts(string $file): void
    {
        $definition = Yaml::parseFile($file);
        self::assertIsArray($definition);

        $factory = $this->get(ArrayFormFactory::class);
        self::assertInstanceOf(ArrayFormFactory::class, $factory);

        // Throws on an unknown element type, an unknown validator or a malformed
        // renderable tree - which is the whole class of defect a YAML file can carry.
        $form = $factory->build($definition, 'standard');

        self::assertNotSame('', $form->getIdentifier());
        self::assertNotSame([], $form->getPages(), 'A form with no page renders nothing.');
    }

    #[Test]
    #[DataProvider('formDefinitions')]
    public function everyLabelItDeclaresHasATranslationKey(string $file): void
    {
        $definition = Yaml::parseFile($file);
        self::assertIsArray($definition);

        $identifier = $definition['identifier'] ?? null;
        self::assertIsString($identifier);
        self::assertNotSame('', $identifier);

        $keys = self::translationKeys();
        $missing = [];
        foreach (self::labelledElements($definition) as $elementIdentifier => $properties) {
            foreach ($properties as $property) {
                $key = $identifier . '.element.' . $elementIdentifier . '.properties.' . $property;
                if (!isset($keys[$key])) {
                    $missing[] = $key;
                }
            }
        }
        sort($missing);

        self::assertSame(
            [],
            $missing,
            'These labels have no entry in locallang_formtemplates.xlf, so a German visitor '
            . "sees the English placeholder written into the YAML:\n  " . implode("\n  ", $missing),
        );
    }

    /**
     * Shipping the files is not the same as offering them.
     *
     * ext:form reads form definitions only from paths listed under
     * `persistenceManager.allowedExtensionPaths`, and that key sits at the root of the
     * form configuration rather than inside `prototypes`. Put it one level off and
     * nothing complains: the YAML still merges, the form editor simply never mentions
     * the templates. So this asserts the path survives the merge, which is the part that
     * can silently go wrong.
     */
    #[Test]
    public function registersItsFormDirectoryWithThePersistenceManager(): void
    {
        $configurationManager = $this->get(ConfigurationManagerInterface::class);
        self::assertInstanceOf(ConfigurationManagerInterface::class, $configurationManager);

        $settings = $configurationManager->getYamlConfiguration(self::formYamlSettings(), false);
        $paths = self::at($settings, ['persistenceManager', 'allowedExtensionPaths']);

        self::assertIsArray($paths);
        self::assertContains(
            'EXT:kern_ux/Configuration/Form/Forms/',
            array_values($paths),
            'The form templates are not on an allowed extension path, so the form editor '
            . 'will not list them.',
        );
    }

    /**
     * The `plugin.tx_form.settings` array the configuration manager needs to find the
     * YAML files at all.
     *
     * Empty on TYPO3 14, where this extension's form configuration is a form set and is
     * discovered on its own. On 13 form sets do not exist and the same file is reached
     * only through `yamlConfigurations` - so passing nothing there yields core's defaults
     * and none of ours, which is a test that proves the opposite of what it claims.
     *
     * @return array<string, mixed>
     */
    private static function formYamlSettings(): array
    {
        if ((new Typo3Version())->getMajorVersion() >= 14) {
            return [];
        }

        return [
            'yamlConfigurations' => [
                '10' => 'EXT:form/Configuration/Yaml/FormSetup.yaml',
                '1756140000' => 'EXT:kern_ux/Configuration/Form/KernUx/config.yaml',
            ],
        ];
    }

    /**
     * Walks a key path through nested arrays, returning null the moment it does not hold.
     *
     * @param list<string> $path
     */
    private static function at(mixed $value, array $path): mixed
    {
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    #[Test]
    public function pointsAtATranslationFileThatExists(): void
    {
        foreach (array_column(self::formDefinitions(), 0) as $file) {
            $definition = Yaml::parseFile($file);
            self::assertIsArray($definition);

            $options = $definition['renderingOptions'] ?? null;
            $translation = is_array($options) ? ($options['translation'] ?? null) : null;
            $files = is_array($translation) ? ($translation['translationFiles'] ?? null) : null;

            self::assertIsArray($files, basename($file) . ' declares no translation file.');
            self::assertNotSame([], $files, basename($file) . ' declares no translation file.');

            foreach ($files as $reference) {
                self::assertIsString($reference);
                $path = str_replace('EXT:kern_ux/', self::EXT_ROOT . '/', $reference);
                self::assertFileExists($path, basename($file) . ' points at a missing translation file.');
            }
        }
    }

    /**
     * On TYPO3 13 the form set is registered through yamlConfigurations, which is read
     * from the frontend TypoScript - so without this the standard prototype does not
     * exist and nothing can be built. On 14 the same file is a form set and is
     * discovered without any TypoScript at all.
     *
     * @return array<string, mixed>
     */
    private static function formTypoScript(): array
    {
        if ((new Typo3Version())->getMajorVersion() >= 14) {
            return [];
        }

        return [
            'plugin.' => [
                'tx_form.' => [
                    'settings.' => [
                        'yamlConfigurations.' => [
                            '10' => 'EXT:form/Configuration/Yaml/FormSetup.yaml',
                            '1756140000' => 'EXT:kern_ux/Configuration/Form/KernUx/config.yaml',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Element identifier => the properties that carry visible text.
     *
     * @param array<array-key, mixed> $definition
     *
     * @return array<string, list<string>>
     */
    private static function labelledElements(array $definition): array
    {
        $found = [];

        $walk = static function (array $node) use (&$walk, &$found): void {
            $identifier = $node['identifier'] ?? null;
            $properties = is_array($node['properties'] ?? null) ? $node['properties'] : [];

            if (is_string($identifier) && $identifier !== '') {
                $carriers = [];
                if (($node['label'] ?? '') !== '') {
                    $carriers[] = 'label';
                }
                if (($properties['elementDescription'] ?? '') !== '') {
                    $carriers[] = 'elementDescription';
                }
                $options = $properties['options'] ?? null;
                if (is_array($options)) {
                    foreach (array_keys($options) as $option) {
                        $carriers[] = 'options.' . $option;
                    }
                }
                if ($carriers !== []) {
                    $found[$identifier] = $carriers;
                }
            }

            $children = $node['renderables'] ?? null;
            if (is_array($children)) {
                foreach ($children as $child) {
                    if (is_array($child)) {
                        $walk($child);
                    }
                }
            }
        };

        $pages = $definition['renderables'] ?? null;
        if (is_array($pages)) {
            foreach ($pages as $page) {
                if (is_array($page)) {
                    $walk($page);
                }
            }
        }

        return $found;
    }

    /**
     * @return array<string, true>
     */
    private static function translationKeys(): array
    {
        $xml = simplexml_load_file(self::TRANSLATION_FILE);
        self::assertNotFalse($xml, 'The form template translation file is not readable.');

        $keys = [];
        foreach ($xml->file->body->{'trans-unit'} ?? [] as $unit) {
            $keys[(string)$unit['id']] = true;
        }

        return $keys;
    }
}
