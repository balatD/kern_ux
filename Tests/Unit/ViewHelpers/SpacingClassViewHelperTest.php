<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Unit\ViewHelpers;

use BalatD\KernUx\ViewHelpers\SpacingClassViewHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The one place core's spacing vocabulary meets KERN's.
 */
final class SpacingClassViewHelperTest extends UnitTestCase
{
    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function mappingProvider(): array
    {
        return [
            'before only' => [['before' => 'large'], 'kern-mt-lg'],
            'after only' => [['after' => 'small'], 'kern-mb-sm'],
            'both' => [['before' => 'extra-large', 'after' => 'extra-small'], 'kern-mt-xl kern-mb-xs'],
            'medium' => [['before' => 'medium'], 'kern-mt-md'],
            // Core's empty value means "leave spacing to the component's own CSS".
            'core default is no class' => [['before' => '', 'after' => ''], ''],
            'nothing given' => [[], ''],
            // A value core does not define must never become a class that does not exist.
            'unknown value' => [['before' => 'huge'], ''],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[Test]
    #[DataProvider('mappingProvider')]
    public function mapsToKernMarginUtilities(array $arguments, string $expected): void
    {
        $subject = new SpacingClassViewHelper();
        $subject->setArguments($arguments);

        self::assertSame($expected, $subject->render());
    }
}
