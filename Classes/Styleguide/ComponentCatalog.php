<?php

declare(strict_types=1);

namespace BalatD\KernUx\Styleguide;

use BalatD\KernUx\Rendering\FluidSourceRenderer;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Builds the component gallery and reports what it does not cover.
 *
 * Components are discovered from the file system rather than through Fluid's
 * getAvailableComponents(): that method arrived with Fluid 5, so it is unavailable on
 * TYPO3 13, and this catalogue has to behave the same on both.
 */
final readonly class ComponentCatalog
{
    private const COMPONENT_ROOT = 'EXT:kern_ux/Resources/Private/Components/';

    private const EXAMPLES = 'EXT:kern_ux/Configuration/Styleguide/Examples.yaml';

    public function __construct(private FluidSourceRenderer $renderer) {}

    /**
     * Every component template on disk, as its Fluid tag name (e.g. "atom.button").
     *
     * Mirrors Fluid's own resolution rule: a component lives at
     * Group/Name/Name.html, so a directory whose file name does not repeat the
     * directory name is not a component.
     *
     * @return list<string>
     */
    public function discoverComponents(): array
    {
        $root = GeneralUtility::getFileAbsFileName(self::COMPONENT_ROOT);
        if ($root === '' || !is_dir($root)) {
            return [];
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'html') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root));
            $segments = explode('/', trim(str_replace('\\', '/', $relative), '/'));
            if (count($segments) < 2) {
                continue;
            }
            $fileName = array_pop($segments);
            if ($fileName !== end($segments) . '.html') {
                continue;
            }
            $found[] = implode('.', array_map(lcfirst(...), $segments));
        }

        sort($found);

        return $found;
    }

    /**
     * @return list<ComponentExample>
     */
    public function examples(): array
    {
        $examples = [];
        foreach ($this->exampleDefinitions() as $component => $cases) {
            foreach ($cases as $case) {
                $source = is_string($case['source'] ?? null) ? $case['source'] : '';
                if ($source === '') {
                    continue;
                }
                $examples[] = new ComponentExample(
                    component: $component,
                    label: is_string($case['label'] ?? null) ? $case['label'] : '',
                    source: $source,
                    markup: $this->renderer->render($source),
                    note: is_string($case['note'] ?? null) ? $case['note'] : '',
                    rendersNothing: ($case['rendersNothing'] ?? false) === true,
                );
            }
        }

        return $examples;
    }

    /**
     * Components with no example at all.
     *
     * A gallery that silently omits components is worse than no gallery: it reads as
     * "this is everything". The styleguide therefore names its own gaps, and a test
     * asserts the list is empty.
     *
     * @return list<string>
     */
    public function undocumentedComponents(): array
    {
        $documented = array_keys($this->exampleDefinitions());

        return array_values(array_diff($this->discoverComponents(), $documented));
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function exampleDefinitions(): array
    {
        $path = GeneralUtility::getFileAbsFileName(self::EXAMPLES);
        if ($path === '' || !is_file($path)) {
            return [];
        }

        $parsed = Yaml::parseFile($path);
        if (!is_array($parsed) || !isset($parsed['components']) || !is_array($parsed['components'])) {
            return [];
        }

        $definitions = [];
        foreach ($parsed['components'] as $component => $cases) {
            if (!is_string($component) || !is_array($cases)) {
                continue;
            }
            $normalised = [];
            foreach ($cases as $case) {
                if (!is_array($case)) {
                    continue;
                }
                // YAML can hand back numeric keys; only named ones mean anything here.
                $entry = [];
                foreach ($case as $key => $value) {
                    if (is_string($key)) {
                        $entry[$key] = $value;
                    }
                }
                $normalised[] = $entry;
            }
            $definitions[$component] = $normalised;
        }

        return $definitions;
    }
}
