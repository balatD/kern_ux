<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\ContentBlocks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Three blocks write a component's KERN markup themselves instead of calling it.
 *
 * The components are meant to be the single source of KERN markup, and mostly they are.
 * These three are the exceptions: description-list, downloads and button-group each
 * inline the same classes their molecule emits, for reasons that made sense per block
 * but leave the extension with two copies of one contract.
 *
 * Two copies drift. Nothing errors when they do - both sides render, both look roughly
 * right, and the block simply stops matching the component that the styleguide shows
 * and that the axe run checks. So this compares the class lists the two paths produce
 * and fails when they diverge, which is the cheapest way to keep a duplication honest
 * short of removing it.
 *
 * Removing it is the better fix and is deliberately not attempted here: the service
 * block's own comment explains why it does not reuse descriptionList, and rewriting
 * three templates is not something to do in the same change that first pins them.
 */
final class DuplicatedComponentMarkupTest extends AbstractContentBlockTestCase
{
    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: string}>
     */
    public static function duplicationProvider(): array
    {
        return [
            'description-list' => [
                'description-list',
                [
                    'uid' => 1,
                    'tx_kernux_descriptionlist_entries' => [
                        ['term' => 'Gebühr', 'definition' => '37,00 €'],
                    ],
                ],
                '<k:molecule.descriptionList items="{0: {key: \'Gebühr\', value: \'37,00 €\'}}" />',
            ],
            'button-group' => [
                'button-group',
                [
                    'uid' => 1,
                    'tx_kernux_buttongroup_buttons' => [
                        ['label' => 'Antrag starten', 'variant' => 'primary'],
                    ],
                ],
                '<k:molecule.buttonGroup items="{0: {label: \'Antrag starten\', variant: \'primary\', link: \'\'}}" />',
            ],
            'downloads' => [
                'downloads',
                [
                    'uid' => 1,
                    'tx_kernux_downloads_files' => [[
                        'publicUrl' => '/fileadmin/merkblatt.pdf',
                        'name' => 'merkblatt.pdf',
                        'title' => 'Merkblatt',
                        'extension' => 'pdf',
                        'size' => 1024,
                    ]],
                ],
                '<k:molecule.downloadList items="{0: {href: \'/fileadmin/merkblatt.pdf\', title: \'Merkblatt\','
                . ' format: \'PDF\', size: \'1 KB\'}}" />',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Test]
    #[DataProvider('duplicationProvider')]
    public function emitsTheSameKernClassesAsTheComponentItDuplicates(
        string $block,
        array $data,
        string $componentSource,
    ): void {
        $fromBlock = self::kernClassesOf($this->renderBlock($block, $data));
        $fromComponent = self::kernClassesOf($this->renderSource($componentSource));

        self::assertNotSame([], $fromComponent, 'The component rendered no KERN classes at all.');
        self::assertSame(
            $fromComponent,
            $fromBlock,
            "The {$block} block and the component it duplicates no longer emit the same "
            . 'KERN classes. Bring them back together, or make the block call the component.',
        );
    }

    /**
     * Every kern-* class in document order, which is what has to match.
     *
     * Only KERN's own classes: the block adds its section wrapper and heading, which the
     * component knows nothing about, and those are pinned elsewhere.
     *
     * @return list<string>
     */
    private static function kernClassesOf(string $markup): array
    {
        preg_match_all('/class="([^"]*)"/', $markup, $matches);

        $classes = [];
        foreach ($matches[1] as $attribute) {
            foreach (preg_split('/\s+/', trim($attribute)) ?: [] as $class) {
                if (str_starts_with($class, 'kern-')) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }
}
