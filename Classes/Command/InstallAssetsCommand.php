<?php

declare(strict_types=1);

namespace BalatD\KernUx\Command;

use BalatD\KernUx\Asset\KernAssetInstaller;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fetches the KERN UX distribution into the extension's public assets.
 *
 * KERN is not vendored into this repository. Its code is EUPL-1.2 and it ships its
 * font binaries without the OFL licence texts they require, so redistributing it
 * inside a GPL extension would mean taking on someone else's licensing debt. The
 * assets are fetched at install time instead, from a pinned, integrity-checked
 * release - and never from a CDN at runtime, because public-sector sites generally
 * cannot make third-party requests.
 */
final class InstallAssetsCommand extends Command
{
    public function __construct(private readonly KernAssetInstaller $installer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'kern-version',
            null,
            InputOption::VALUE_REQUIRED,
            'Version of @kern-ux/native to install.',
            KernAssetInstaller::PINNED_VERSION,
        );
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Re-download even if the pinned version is already present.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $version = $input->getOption('kern-version');
        $version = is_string($version) && $version !== ''
            ? $version
            : KernAssetInstaller::PINNED_VERSION;

        try {
            $result = $this->installer->install($version, (bool)$input->getOption('force'));
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($result->skipped) {
            $io->success(sprintf('KERN %s already installed in %s', $version, $result->targetPath));

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Installed KERN %s (%d files) into %s',
            $version,
            $result->fileCount,
            $result->targetPath,
        ));
        $io->note('KERN UX is licensed EUPL-1.2. Fira Sans and Noto Sans are OFL-1.1. See THIRD-PARTY.md.');

        return Command::SUCCESS;
    }
}
