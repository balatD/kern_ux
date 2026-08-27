<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Organism;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

final class KopfzeileTest extends AbstractComponentTestCase
{
    #[Test]
    public function rendersTheCssOnlyVariantWithoutAnyScript(): void
    {
        // The CSS variant on purpose: a mandated header bar must not depend on a
        // 12.7 kB web component being loaded.
        $rendered = $this->renderSource('<k:organism.kopfzeile />');

        self::assertStringContainsString('<div class="kern-kopfzeile">', $rendered);
        self::assertStringNotContainsString('<kern-kopfzeile', $rendered);
        self::assertStringNotContainsString('<script', $rendered);
    }

    #[Test]
    public function hidesTheFlagFromAssistiveTechnology(): void
    {
        // The flag repeats what the adjacent label already says.
        $rendered = $this->renderSource('<k:organism.kopfzeile />');

        self::assertStringContainsString('class="kern-kopfzeile__flagge" aria-hidden="true"', $rendered);
    }

    #[Test]
    public function usesTheOfficialWordingByDefault(): void
    {
        $rendered = $this->renderSource('<k:organism.kopfzeile />');

        self::assertStringContainsString(
            'Offizielle Website – Bundesrepublik Deutschland',
            $rendered,
        );
    }

    #[Test]
    public function switchesToAFullWidthContainerOnRequest(): void
    {
        $rendered = $this->renderSource('<k:organism.kopfzeile fluid="{true}" />');

        self::assertStringContainsString('kern-container-fluid', $rendered);
    }
}
