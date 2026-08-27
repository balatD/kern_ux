<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Atom;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ButtonTest extends AbstractComponentTestCase
{
    #[Test]
    public function rendersPrimaryButtonWithLabelWrappedInKernLabel(): void
    {
        $rendered = $this->renderSource('<k:atom.button>Speichern</k:atom.button>');

        self::assertSame(
            '<button type="button" class="kern-btn kern-btn--primary">'
            . '<span class="kern-label">Speichern</span>'
            . '</button>',
            $rendered,
        );
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function defaultsToTypeButtonSoItDoesNotSubmitASurroundingForm(): void
    {
        $rendered = $this->renderSource('<k:atom.button>X</k:atom.button>');

        self::assertStringContainsString('type="button"', $rendered);
    }

    #[Test]
    public function rendersTrailingIconAfterTheLabelByDefault(): void
    {
        $rendered = ($this->renderSource(
            '<k:atom.button variant="secondary" icon="arrow-forward">Weiter</k:atom.button>',
        ));

        self::assertSame(
            '<button type="button" class="kern-btn kern-btn--secondary">'
            . '<span class="kern-label">Weiter</span>'
            . '<span class="kern-icon kern-icon--arrow-forward" aria-hidden="true"></span>'
            . '</button>',
            $rendered,
        );
    }

    #[Test]
    public function iconOnlyPlacesIconFirstAndKeepsAnAccessibleLabel(): void
    {
        $rendered = ($this->renderSource(
            '<k:atom.button variant="tertiary" icon="edit" iconOnly="{true}">Bearbeiten</k:atom.button>',
        ));

        // WCAG 1.1.1: the label must survive as screen-reader text, never be dropped.
        self::assertSame(
            '<button type="button" class="kern-btn kern-btn--tertiary">'
            . '<span class="kern-icon kern-icon--edit" aria-hidden="true"></span>'
            . '<span class="kern-label kern-sr-only">Bearbeiten</span>'
            . '</button>',
            $rendered,
        );
    }

    #[Test]
    public function decorativeIconIsHiddenFromAssistiveTechnology(): void
    {
        $rendered = $this->renderSource('<k:atom.button icon="search">Suchen</k:atom.button>');

        self::assertStringContainsString('aria-hidden="true"', $rendered);
    }

    #[Test]
    public function disabledUsesAriaDisabledAndNeverTheNativeAttribute(): void
    {
        $rendered = $this->renderSource('<k:atom.button disabled="{true}">Absenden</k:atom.button>');

        // KERN forbids the native attribute: it makes the control unreachable by
        // keyboard and invisible to screen readers.
        self::assertStringContainsString('aria-disabled="true"', $rendered);
        self::assertStringNotContainsString(' disabled', $rendered);
    }

    #[Test]
    public function appliesSizeAndBlockModifiers(): void
    {
        $rendered = $this->renderSource(
            '<k:atom.button size="x-small" block="{true}">X</k:atom.button>',
        );

        self::assertStringContainsString(
            'class="kern-btn kern-btn--primary kern-btn--x-small kern-btn--block"',
            $rendered,
        );
    }

    #[Test]
    public function omitsOptionalAttributesWhenUnset(): void
    {
        $rendered = $this->renderSource('<k:atom.button>X</k:atom.button>');

        self::assertStringNotContainsString('id=""', $rendered);
        self::assertStringNotContainsString('name=""', $rendered);
        self::assertStringNotContainsString('value=""', $rendered);
    }
}
