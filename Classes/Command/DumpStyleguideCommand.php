<?php

declare(strict_types=1);

namespace BalatD\KernUx\Command;

use BalatD\KernUx\Styleguide\StyleguideRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

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

    /**
     * Dropped into every directory this command creates.
     *
     * The dump starts by deleting its target, so it has to be able to tell a directory
     * it made from one that belongs to somebody. Containment inside the project is not
     * enough on its own: `--target=Classes` is inside the project too.
     */
    private const MARKER = '.kern-ux-styleguide';

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

        $rawTarget = $input->getOption('target');
        $rawTarget = is_string($rawTarget) && $rawTarget !== '' ? $rawTarget : 'var/kern-ux-styleguide';

        try {
            $target = $this->resolveTarget($rawTarget);
        } catch (\RuntimeException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

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
        GeneralUtility::writeFile($target . '/' . self::MARKER, "EXT:kern_ux styleguide dump\n", true);
        $this->copyTree($publicPath, $target . '/assets');
        GeneralUtility::writeFile($target . '/index.html', $markup, true);

        $io->success(sprintf('Gallery written to %s/index.html', $target));

        return Command::SUCCESS;
    }

    /**
     * Turns --target into an absolute path this command is allowed to delete.
     *
     * Three things had to change here. The path is resolved against the project root
     * rather than the shell's working directory, so the documented default actually
     * means the project's own var/ wherever the command is run from. It has to stay
     * inside the project. And an existing directory is only reusable if this command
     * made it - the first step of the dump is `rmdir($target, true)`, a recursive
     * delete of whatever it is handed, and GeneralUtility::rmdir() does no allowlisting
     * of its own.
     *
     * @throws \RuntimeException when the path is outside the project or not ours
     */
    private function resolveTarget(string $target): string
    {
        $projectPath = rtrim(GeneralUtility::fixWindowsFilePath(Environment::getProjectPath()), '/');

        if (!PathUtility::isAbsolutePath($target)) {
            $target = $projectPath . '/' . $target;
        }
        $target = rtrim(PathUtility::getCanonicalPath(GeneralUtility::fixWindowsFilePath($target)), '/');

        if ($target === $projectPath || !str_starts_with($target . '/', $projectPath . '/')) {
            throw new \RuntimeException(
                sprintf('--target must be a directory inside %s, got: %s', $projectPath, $target),
                1756131101,
            );
        }

        if (is_dir($target) && !is_file($target . '/' . self::MARKER)) {
            throw new \RuntimeException(
                sprintf(
                    '%s already exists and was not created by this command, so it will not be deleted. '
                    . 'Choose another --target or remove it yourself.',
                    $target,
                ),
                1756131102,
            );
        }

        if (is_file($target)) {
            throw new \RuntimeException(sprintf('--target is a file, not a directory: %s', $target), 1756131103);
        }

        return $target;
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
            // Resources/Public/ContentBlocks/ is generated: Content Blocks fills it with
            // symlinks into each block's assets/ directory, and a block that has since
            // been renamed or dropped leaves a dangling one behind. That is local build
            // residue rather than a broken dump, so it is skipped - but a copy that
            // fails for any other reason is not, because a half-copied dump shows up
            // much later as an opaque "the KERN stylesheet did not load" from the axe
            // run.
            if (!file_exists($item->getPathname())) {
                continue;
            }
            if (!copy($item->getPathname(), $destination . '/' . $relative)) {
                throw new \RuntimeException(
                    sprintf('Could not copy %s into the dump.', $item->getPathname()),
                    1756131104,
                );
            }
        }
    }
}
