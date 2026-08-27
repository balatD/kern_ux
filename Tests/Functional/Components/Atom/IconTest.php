<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Atom;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

final class IconTest extends AbstractComponentTestCase
{
    #[Test]
    public function hidesDecorativeIconsFromAssistiveTechnology(): void
    {
        $rendered = $this->renderSource('<k:atom.icon name="search" />');

        self::assertSame(
            '<span class="kern-icon kern-icon--search" aria-hidden="true"></span>',
            $rendered,
        );
    }

    #[Test]
    public function exposesLabelledIconsAsImages(): void
    {
        // role="img" on top of the label: an aria-label on a bare span is ignored by
        // most screen readers, so without the role the icon stays silent.
        $rendered = $this->renderSource('<k:atom.icon name="info" label="Hinweis" />');

        self::assertSame(
            '<span class="kern-icon kern-icon--info" role="img" aria-label="Hinweis"></span>',
            $rendered,
        );
        self::assertStringNotContainsString('aria-hidden', $rendered);
    }

    #[Test]
    public function appliesSizeModifier(): void
    {
        $rendered = $this->renderSource('<k:atom.icon name="close" size="large" />');

        self::assertSame(
            '<span class="kern-icon kern-icon--close kern-icon--large" aria-hidden="true"></span>',
            $rendered,
        );
    }
}
