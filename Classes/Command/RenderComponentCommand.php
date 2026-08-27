<?php

declare(strict_types=1);

namespace BalatD\KernUx\Command;

use BalatD\KernUx\Components\ComponentCollection;
use BalatD\KernUx\Rendering\FluidSourceRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Prints rendered KERN component markup.
 *
 * Used to diff component output between TYPO3 majors without a browser or a
 * database - Fluid 4.6 (v13) and Fluid 5.3 (v14) must agree byte for byte.
 */
final class RenderComponentCommand extends Command
{
    public function __construct(private readonly FluidSourceRenderer $renderer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'source',
            InputArgument::OPTIONAL,
            'Fluid source to render. Omit to render the built-in smoke-test snippet.',
        );
        $this->addOption(
            'xmlns',
            null,
            InputOption::VALUE_NONE,
            'Declare the collection namespace inline instead of relying on the global registration.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $argument = $input->getArgument('source');
        $source = is_string($argument) && $argument !== '' ? $argument : $this->smokeTestSource();

        if ($input->getOption('xmlns')) {
            $source = sprintf(
                '<html xmlns:k="http://typo3.org/ns/%s" data-namespace-typo3-fluid="true">%s</html>',
                str_replace('\\', '/', ComponentCollection::class),
                $source,
            );
        }

        $output->writeln(trim($this->renderer->render($source)));

        return Command::SUCCESS;
    }

    private function smokeTestSource(): string
    {
        return implode('', [
            '<k:atom.button>Speichern</k:atom.button>',
            '<k:atom.button variant="secondary" icon="arrow-forward">Weiter</k:atom.button>',
            '<k:atom.button variant="tertiary" icon="edit" iconOnly="{true}">Bearbeiten</k:atom.button>',
            '<k:atom.button type="submit" size="x-small" block="{true}" disabled="{true}">Absenden</k:atom.button>',
        ]);
    }
}
