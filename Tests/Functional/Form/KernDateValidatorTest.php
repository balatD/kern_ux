<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Form;

use BalatD\KernUx\Form\Validation\KernDateValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The three-field date is the one place in the form layer with real logic, and the
 * failure modes are deliberately distinguished: "31.02." and "17..2027" are different
 * mistakes and deserve different messages.
 *
 * Assertions are on error *codes*, not messages: the messages are translations and
 * would make this test a test of the XLIFF file.
 */
final class KernDateValidatorTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    /**
     * @return array<string, array{0: mixed, 1: array<string, mixed>, 2: ?int}>
     */
    public static function dateProvider(): array
    {
        return [
            'valid date' => [['day' => '17', 'month' => '3', 'year' => '2027'], [], null],
            'valid with leading zeros' => [['day' => '07', 'month' => '03', 'year' => '2027'], [], null],
            'leap day in a leap year' => [['day' => '29', 'month' => '2', 'year' => '2028'], [], null],

            // 31 is a fine day and 2 a fine month - just not together. This is exactly
            // what a per-field validator cannot see.
            'impossible day for the month' => [['day' => '31', 'month' => '2', 'year' => '2027'], [], 1756150004],
            'leap day in a non-leap year' => [['day' => '29', 'month' => '2', 'year' => '2027'], [], 1756150004],
            'month out of range' => [['day' => '1', 'month' => '13', 'year' => '2027'], [], 1756150004],

            // Half-typed is not the same as wrong: saying "that date does not exist"
            // while somebody is still filling it in is unhelpful.
            'missing month' => [['day' => '17', 'month' => '', 'year' => '2027'], [], 1756150002],
            'only a year' => [['day' => '', 'month' => '', 'year' => '2027'], [], 1756150002],

            'non-numeric' => [['day' => 'ab', 'month' => '3', 'year' => '2027'], [], 1756150003],

            'empty and optional' => [['day' => '', 'month' => '', 'year' => ''], [], null],
            'empty and required' => [['day' => '', 'month' => '', 'year' => ''], ['required' => true], 1756150001],

            'not an array at all' => ['nonsense', ['required' => true], 1756150001],

            'before the earliest allowed' => [
                ['day' => '1', 'month' => '1', 'year' => '2020'],
                ['earliest' => '2026-01-01'],
                1756150006,
            ],
            'after the latest allowed' => [
                ['day' => '1', 'month' => '1', 'year' => '2030'],
                ['latest' => '2027-12-31'],
                1756150007,
            ],
            'inside the allowed range' => [
                ['day' => '1', 'month' => '6', 'year' => '2027'],
                ['earliest' => '2026-01-01', 'latest' => '2027-12-31'],
                null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Test]
    #[DataProvider('dateProvider')]
    public function reportsTheRightFailureMode(mixed $value, array $options, ?int $expectedCode): void
    {
        $validator = new KernDateValidator();
        $validator->setOptions($options);

        $result = $validator->validate($value);

        if ($expectedCode === null) {
            self::assertFalse($result->hasErrors(), 'Expected the date to be accepted.');

            return;
        }

        self::assertTrue($result->hasErrors(), 'Expected the date to be rejected.');
        $error = $result->getFirstError();
        self::assertInstanceOf(\TYPO3\CMS\Extbase\Error\Error::class, $error);
        self::assertSame($expectedCode, $error->getCode());
    }

    #[Test]
    public function reportsOneErrorPerDateRatherThanOnePerField(): void
    {
        // One message for one mistake: three complaints about a single wrong date is
        // noise, and none of them would say what is actually wrong.
        $validator = new KernDateValidator();
        $validator->setOptions([]);

        $result = $validator->validate(['day' => '31', 'month' => '2', 'year' => '2027']);

        self::assertCount(1, $result->getErrors());
    }
}
