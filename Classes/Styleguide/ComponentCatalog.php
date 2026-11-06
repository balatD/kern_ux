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

    private const KERN_STYLESHEET = 'EXT:kern_ux/Resources/Public/Vendor/KernUx/kern.css';

    /**
     * Every place this extension may spell a KERN class.
     *
     * Wider than the component tree on purpose: some KERN families are reached by the
     * ext:form theme or by TypoScript rather than by a component, and those count as
     * covered. Resources/Public/Vendor is not listed, so KERN's own files never answer
     * the question about themselves.
     */
    private const SOURCE_ROOTS = [
        'EXT:kern_ux/Resources/Private/',
        'EXT:kern_ux/Resources/Public/Css/',
        'EXT:kern_ux/Resources/Public/JavaScript/',
        'EXT:kern_ux/ContentBlocks/',
        'EXT:kern_ux/Configuration/',
    ];

    /**
     * kern.css regions that define no component.
     *
     * Hand-maintained, and the only such list here: KERN ships colours, sizes, the icon
     * font, the grid and the spacing utilities from the same stylesheet as its
     * components, and a utility class is not something this extension could "implement".
     * Note `icons` (the icon font) and `icon` (the component) are different regions.
     */
    private const FOUNDATION_REGIONS = [
        'mixins', 'icons', 'colors', 'font', 'sizes', 'spacing',
        'variables', 'themes', 'layers', 'grid', 'flex-grid-system',
    ];

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
     * Whether the fetched KERN distribution is on disk.
     *
     * It is deliberately not committed (EUPL-1.2, and it ships fonts without their OFL
     * texts), so anything reading it has to cope with its absence.
     */
    public function kernStylesheetAvailable(): bool
    {
        $path = GeneralUtility::getFileAbsFileName(self::KERN_STYLESHEET);

        return $path !== '' && is_file($path);
    }

    /**
     * KERN component families that nothing in this extension reaches.
     *
     * The gallery answers "which of our components are undocumented"; this answers the
     * other direction - "what does KERN ship that we never implemented" - which was
     * previously only answerable by reading the stylesheet by hand.
     *
     * Empty when the distribution is absent, so a caller can distinguish "nothing is
     * missing" from "could not tell" via kernStylesheetAvailable().
     *
     * @return list<string>
     */
    public function uncoveredKernFamilies(): array
    {
        if (!$this->kernStylesheetAvailable()) {
            return [];
        }

        $haystack = $this->sourceHaystack();

        // Classes built by interpolation never appear whole in a template:
        // atom.heading writes `kern-heading-{appearance}`, so the families
        // kern-heading-large, -medium and -x-large exist only at render time. Without
        // this, seven families read as uncovered.
        preg_match_all('/kern-[a-z0-9-]*-(?=\{)/', $haystack, $matches);
        $interpolated = array_unique($matches[0]);

        $uncovered = [];
        foreach ($this->kernComponentFamilies() as $family) {
            // The trailing guard is what keeps kern-table from being "found" inside
            // kern-table-responsive; the optional group is what lets a reference to
            // kern-summary-group__header cover the kern-summary-group family.
            $pattern = '/' . preg_quote($family, '/') . '(?:(?:__|--)[A-Za-z0-9-]+)?(?![A-Za-z0-9_-])/';
            if (preg_match($pattern, $haystack) === 1) {
                continue;
            }

            foreach ($interpolated as $prefix) {
                if (str_starts_with($family, $prefix)) {
                    continue 2;
                }
            }

            $uncovered[] = $family;
        }

        return $uncovered;
    }

    /**
     * Every `kern-*` block family KERN defines in a component region.
     *
     * kern.css embeds its SCSS source headers, so the regions are authoritative rather
     * than guessed. A family is everything before the first `__` or `--`, which is how
     * KERN spells element and modifier.
     *
     * @return list<string>
     */
    private function kernComponentFamilies(): array
    {
        $path = GeneralUtility::getFileAbsFileName(self::KERN_STYLESHEET);
        $css = $path === '' ? false : file_get_contents($path);
        if ($css === false) {
            return [];
        }

        $regions = preg_split('/@file _([a-z0-9-]+)\.scss/', $css, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($regions === false) {
            return [];
        }

        $families = [];
        for ($i = 1; $i < count($regions); $i += 2) {
            if (in_array($regions[$i], self::FOUNDATION_REGIONS, true)) {
                continue;
            }

            preg_match_all('/\.(kern-[A-Za-z0-9_-]+)/', $regions[$i + 1] ?? '', $matches);
            foreach ($matches[1] as $class) {
                $family = preg_split('/__|--/', $class);
                if ($family !== false && $family[0] !== '') {
                    $families[$family[0]] = true;
                }
            }
        }

        $found = array_keys($families);
        sort($found);

        return $found;
    }

    /**
     * Every source file that could name a KERN class, concatenated.
     */
    private function sourceHaystack(): string
    {
        $haystack = '';
        foreach (self::SOURCE_ROOTS as $root) {
            $absolute = GeneralUtility::getFileAbsFileName($root);
            if ($absolute === '' || !is_dir($absolute)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absolute, \FilesystemIterator::SKIP_DOTS),
            );

            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $contents = file_get_contents($file->getPathname());
                if ($contents !== false) {
                    $haystack .= $contents . "\n";
                }
            }
        }

        return $haystack;
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
