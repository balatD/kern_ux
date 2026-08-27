<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Unit\Components;

use BalatD\KernUx\Components\ComponentCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Pins the tag-name to file-path mapping.
 *
 * The mapping comes from Fluid, not from us, but the whole component tree is laid
 * out according to it - so if Fluid ever changes it, that has to fail here rather
 * than surface as components silently resolving to nothing.
 */
final class ComponentCollectionTest extends UnitTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function componentNameProvider(): array
    {
        return [
            'atom' => ['atom.button', 'Atom/Button/Button'],
            'nested molecule' => ['molecule.form.row', 'Molecule/Form/Row/Row'],
            'organism' => ['organism.header', 'Organism/Header/Header'],
            'deeply nested' => ['organism.header.navigation', 'Organism/Header/Navigation/Navigation'],
            'no group' => ['button', 'Button/Button'],
        ];
    }

    #[Test]
    #[DataProvider('componentNameProvider')]
    public function resolvesTagNameToFolderPerComponentPath(string $tagName, string $expected): void
    {
        self::assertSame($expected, (new ComponentCollection())->resolveTemplateName($tagName));
    }
}
