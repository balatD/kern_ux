<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Molecule;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SkipLinkTest extends AbstractComponentTestCase
{
    #[Test]
    public function rendersARealAnchorSoItStaysFocusable(): void
    {
        // BITV requires a bypass mechanism and KERN provides none. It has to be a
        // focusable link - anything hidden with display:none is unreachable.
        $rendered = $this->renderSource('<k:molecule.skipLink>Zum Inhalt</k:molecule.skipLink>');

        self::assertSame('<a class="kernt3-skip-link" href="#main">Zum Inhalt</a>', $rendered);
    }

    #[Test]
    public function targetsTheGivenElement(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.skipLink target="navigation">Zur Navigation</k:molecule.skipLink>',
        );

        self::assertStringContainsString('href="#navigation"', $rendered);
    }
}
