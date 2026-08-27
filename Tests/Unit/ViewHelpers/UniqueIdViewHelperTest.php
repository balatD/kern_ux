<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Unit\ViewHelpers;

use BalatD\KernUx\ViewHelpers\UniqueIdViewHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class UniqueIdViewHelperTest extends UnitTestCase
{
    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function idProvider(): array
    {
        return [
            'prefix and uid' => [['prefix' => 'accordion', 'uid' => 42], 'kern-accordion-42'],
            'with suffix' => [['prefix' => 'form', 'uid' => 7, 'suffix' => 'error'], 'kern-form-7-error'],
            'lowercased' => [['prefix' => 'TaskList', 'uid' => 1], 'kern-tasklist-1'],
            'non-alphanumerics collapse' => [['prefix' => 'card grid', 'uid' => 3], 'kern-card-grid-3'],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[Test]
    #[DataProvider('idProvider')]
    public function buildsDeterministicIds(array $arguments, string $expected): void
    {
        // Deterministic, not random: a cached page and a fresh render have to produce
        // the same document, and an anchor pointing at the id must keep working.
        $subject = new UniqueIdViewHelper();
        $subject->setArguments($arguments);

        self::assertSame($expected, $subject->render());
        self::assertSame($expected, $subject->render());
    }
}
