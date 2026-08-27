<?php

declare(strict_types=1);

namespace BalatD\KernUx\Command;

use BalatD\KernUx\Demo\DemoContentInstaller;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Installs a demo page tree so the extension can actually be looked at.
 *
 * Four example pages and one page per content block. Both are needed for different
 * reasons: the example pages show how elements behave together - heading order, spacing,
 * landmark structure - while a page per element is what makes a single element
 * inspectable, and what turns a template that no longer parses into something visible
 * rather than something an editor discovers later.
 *
 * Content is in German because that is the language of the administrations KERN is for,
 * and because the German translations are otherwise only exercised by tests.
 */
final class InstallDemoCommand extends Command
{
    public function __construct(private readonly DemoContentInstaller $installer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'site',
            null,
            InputOption::VALUE_REQUIRED,
            'Identifier of the site to install into. Defaults to the only site, if there is exactly one.',
        );
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Delete a demo tree installed earlier before installing. Without this a second '
            . 'run would add a duplicate tree next to the first.',
        );
        $this->addOption(
            'configure-navigation',
            null,
            InputOption::VALUE_NONE,
            'Also point the site settings at the created navigation pages. Without this the '
            . 'service, footer and legal menus stay switched off and the page frame cannot be '
            . 'seen complete.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $site = $this->resolveSite($input);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->section('Installing demo content into site "' . $site->getIdentifier() . '"');

        if ($input->getOption('force') === true) {
            try {
                $removed = $this->installer->remove($site->getRootPageId());
            } catch (\Throwable $e) {
                $io->error($e->getMessage());

                return Command::FAILURE;
            }
            if ($removed > 0) {
                $io->text('Removed ' . $removed . ' demo tree(s) installed earlier.');
            }
        }

        try {
            $result = $this->installer->install($site->getRootPageId());
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->definitionList(
            ['Pages' => (string)$result['pages']],
            ['Content elements' => (string)$result['records']],
            ['Generated files' => (string)$result['files']],
        );

        if ($input->getOption('configure-navigation') === true) {
            $written = $this->configureNavigation($site->getIdentifier(), $result['keys']);
            if ($written === []) {
                $io->warning('The navigation pages were not found, so the site settings were left alone.');
            } else {
                $io->text('Site settings updated: ' . implode(', ', array_keys($written)));
            }
        } else {
            $io->text([
                'The service, footer and legal menus are still switched off. Either re-run with',
                '--configure-navigation, or set these in the site settings yourself:',
            ]);
            $io->listing([
                'kernUx.navigation.helpRootPage = ' . ($result['keys']['serviceRoot'] ?? '?'),
                'kernUx.navigation.footerRootPage = ' . ($result['keys']['footerRoot'] ?? '?'),
                'kernUx.navigation.metaRootPage = ' . ($result['keys']['legalRoot'] ?? '?'),
            ]);
        }

        $io->success('Demo content installed. Flush the frontend caches before looking at it.');

        return Command::SUCCESS;
    }

    /**
     * Written into the site's settings.yaml rather than set through TypoScript: these are
     * site settings, and a demo that needed a TypoScript override to look complete would
     * be documenting the wrong thing.
     *
     * Written as flat dotted keys, which is the form TYPO3's own site settings editor
     * produces. That matters: a nested block written next to existing flat keys leaves
     * the same setting in the file twice, and the stale one wins - which silently emptied
     * all three menus once, because they still pointed at pages a reinstall had deleted.
     * Any earlier spelling of these three keys is removed first, in both forms.
     *
     * @param array<string, int> $keys
     * @return array<string, int>
     */
    private function configureNavigation(string $identifier, array $keys): array
    {
        $mapping = [
            'helpRootPage' => 'serviceRoot',
            'footerRootPage' => 'footerRoot',
            'metaRootPage' => 'legalRoot',
        ];

        $written = [];
        foreach ($mapping as $setting => $key) {
            if (isset($keys[$key])) {
                $written[$setting] = $keys[$key];
            }
        }
        if ($written === []) {
            return [];
        }

        $file = $this->settingsFile($identifier);
        $settings = [];
        if (is_file($file)) {
            $parsed = Yaml::parseFile($file);
            if (is_array($parsed)) {
                /** @var array<string, mixed> $parsed */
                $settings = $parsed;
            }
        }

        foreach (array_keys($written) as $setting) {
            unset($settings['kernUx.navigation.' . $setting]);
            if (is_array($settings['kernUx'] ?? null)) {
                $nested = $settings['kernUx'];
                if (is_array($nested['navigation'] ?? null)) {
                    unset($nested['navigation'][$setting]);
                    if ($nested['navigation'] === []) {
                        unset($nested['navigation']);
                    }
                }
                if ($nested === []) {
                    unset($settings['kernUx']);
                } else {
                    $settings['kernUx'] = $nested;
                }
            }
        }

        foreach ($written as $setting => $uid) {
            $settings['kernUx.navigation.' . $setting] = $uid;
        }

        // inline: 0 keeps every level expanded, so nothing turns into inline YAML that a
        // person then has to edit around.
        $bytes = file_put_contents($file, Yaml::dump($settings, 8, 4));
        if ($bytes === false) {
            throw new \RuntimeException('Cannot write ' . $file, 1756200020);
        }

        return $written;
    }

    private function settingsFile(string $identifier): string
    {
        return \TYPO3\CMS\Core\Core\Environment::getConfigPath() . '/sites/' . $identifier . '/settings.yaml';
    }

    private function resolveSite(InputInterface $input): \TYPO3\CMS\Core\Site\Entity\Site
    {
        $finder = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(SiteFinder::class);
        $requested = $input->getOption('site');

        if (is_string($requested) && $requested !== '') {
            return $finder->getSiteByIdentifier($requested);
        }

        $sites = $finder->getAllSites();
        if (count($sites) === 1) {
            return array_values($sites)[0];
        }

        throw new \RuntimeException(
            'There are ' . count($sites) . ' sites, so --site is required. Available: '
            . implode(', ', array_keys($sites)),
            1756200021,
        );
    }
}
