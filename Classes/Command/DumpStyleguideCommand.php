<?php

declare(strict_types=1);

namespace BalatD\KernUx\Command;

use BalatD\KernUx\Styleguide\StyleguideRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Writes the component gallery to a self-contained directory.
 *
 * Exists so the accessibility tests can run against the real gallery without a web
 * server or a database: axe needs the stylesheets to resolve in order to judge
 * contrast and visibility, and a static directory with relative asset paths works
 * straight from file:// in CI.
 */
final class DumpStyleguideCommand extends Command
{
    /**
     * TYPO3 publishes extension assets under /_assets/<32 hex>/. The dump rewrites
     * that prefix to a relative path so the document works outside a web root.
     */
    private const PUBLISHED_ASSET_PREFIX = '#/_assets/[0-9a-f]{32}/#';

    public function __construct(private readonly StyleguideRenderer $renderer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'target',
            't',
            InputOption::VALUE_REQUIRED,
            'Directory to write index.html and assets/ into.',
            'var/kern-ux-styleguide',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $target = $input->getOption('target');
        $target = rtrim(is_string($target) && $target !== '' ? $target : 'var/kern-ux-styleguide', '/');

        $publicPath = GeneralUtility::getFileAbsFileName('EXT:kern_ux/Resources/Public/');
        if ($publicPath === '' || !is_dir($publicPath)) {
            $io->error('Could not resolve EXT:kern_ux/Resources/Public/.');

            return Command::FAILURE;
        }
        if (!is_file($publicPath . 'Vendor/KernUx/kern.min.css')) {
            $io->error('KERN assets are missing. Run kern-ux:assets:install first.');

            return Command::FAILURE;
        }

        $markup = (string)preg_replace(
            self::PUBLISHED_ASSET_PREFIX,
            'assets/',
            $this->renderer->render(),
        );

        GeneralUtility::rmdir($target, true);
        GeneralUtility::mkdir_deep($target . '/assets');
        $this->copyTree($publicPath, $target . '/assets');
        GeneralUtility::writeFile($target . '/index.html', $markup, true);

        $io->success(sprintf('Gallery written to %s/index.html', $target));

        return Command::SUCCESS;
    }

    private function copyTree(string $source, string $destination): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source));
            if ($item->isDir()) {
                GeneralUtility::mkdir_deep($destination . '/' . $relative);
                continue;
            }
            copy($item->getPathname(), $destination . '/' . $relative);
        }
    }
}
