<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Every command class is actually registered, and under the name the docs promise.
 *
 * A command is wired up in Services.yaml, not by existing, so adding one and forgetting
 * the entry produces a class nobody can run and no error anywhere. That matters more
 * here than it usually would: these four *are* the documented first-run path, and two
 * of them are what the accessibility job in CI calls before it can test anything.
 *
 * A unit test on purpose: it reads the files straight from disk, so it needs neither a
 * database nor a TYPO3 bootstrap and runs the same way under both supported majors.
 */
final class CommandRegistrationTest extends UnitTestCase
{
    private const EXT_ROOT = __DIR__ . '/../../..';

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function services(): array
    {
        $parsed = Yaml::parseFile(self::EXT_ROOT . '/Configuration/Services.yaml');
        self::assertIsArray($parsed);
        $services = $parsed['services'] ?? null;
        self::assertIsArray($services, 'Services.yaml declares no services.');

        $registered = [];
        foreach ($services as $name => $definition) {
            if (!is_string($name) || !is_array($definition)) {
                continue;
            }

            $entry = [];
            foreach ($definition as $key => $value) {
                if (is_string($key)) {
                    $entry[$key] = $value;
                }
            }
            $registered[$name] = $entry;
        }

        return $registered;
    }

    #[Test]
    public function everyCommandClassIsRegisteredAsAConsoleCommand(): void
    {
        $onDisk = glob(self::EXT_ROOT . '/Classes/Command/*.php') ?: [];
        self::assertNotSame([], $onDisk, 'No command classes were found at all.');

        $services = self::services();
        foreach ($onDisk as $file) {
            $class = 'BalatD\\KernUx\\Command\\' . basename($file, '.php');
            self::assertArrayHasKey(
                $class,
                $services,
                "{$class} exists but Services.yaml does not register it, so nobody can run it.",
            );

            $tags = $services[$class]['tags'] ?? null;
            self::assertIsArray($tags);
            $names = array_map(
                static fn(mixed $tag): mixed => is_array($tag) ? ($tag['name'] ?? null) : null,
                $tags,
            );
            self::assertContains('console.command', $names, "{$class} is registered but not as a command.");
        }
    }

    #[Test]
    public function everyCommandIsNamespacedUnderKernUx(): void
    {
        foreach (self::services() as $class => $definition) {
            $tags = $definition['tags'] ?? [];
            if (!is_array($tags)) {
                continue;
            }

            foreach ($tags as $tag) {
                if (!is_array($tag) || ($tag['name'] ?? null) !== 'console.command') {
                    continue;
                }

                $command = $tag['command'] ?? null;
                self::assertIsString($command);
                // The prefix is what keeps these out of the way of core's own commands
                // and what the documentation tells integrators to type.
                self::assertStringStartsWith('kern-ux:', $command, "{$class} claims the command name {$command}.");
            }
        }
    }
}
