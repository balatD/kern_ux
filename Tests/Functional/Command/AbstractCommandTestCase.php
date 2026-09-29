<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Command;

use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Base class for tests that assert on what a command printed.
 *
 * Symfony renders an error block as a padded, hard-wrapped box, and where it breaks a
 * line depends on how long the strings in it are - including absolute paths. So an
 * assertion on a phrase is really an assertion about the width of the checkout
 * directory: `was not created by this command` survived locally under
 * /var/www/kern_ux, and split across a line break under
 * /home/runner/work/kern_ux/kern_ux, failing all eight matrix legs on a message that
 * was word for word correct.
 *
 * assertDisplayContains() collapses whitespace on both sides first, so these tests
 * assert what the command said rather than how the console happened to fold it.
 */
abstract class AbstractCommandTestCase extends FunctionalTestCase
{
    protected static function assertDisplayContains(string $needle, CommandTester $tester): void
    {
        $display = (string)preg_replace('/\s+/', ' ', $tester->getDisplay());

        self::assertStringContainsString(
            (string)preg_replace('/\s+/', ' ', $needle),
            $display,
            "The command did not say this. What it printed, with whitespace collapsed:\n"
            . $display,
        );
    }
}
