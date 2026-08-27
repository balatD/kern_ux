<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Atom;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

final class LinkTest extends AbstractComponentTestCase
{
    #[Test]
    public function rendersPlainKernLink(): void
    {
        $rendered = $this->renderSource('<k:atom.link href="/kontakt">Kontakt</k:atom.link>');

        self::assertSame('<a class="kern-link" href="/kontakt">Kontakt</a>', $rendered);
    }

    #[Test]
    public function leadsWithTheIconByDefaultAndGluesItToTheLabel(): void
    {
        // No whitespace between icon and label: the gap is KERN's flex gap, not a
        // text node. A stray space here would be inconsistent across components.
        $rendered = $this->renderSource('<k:atom.link href="/k" icon="mail">Kontakt</k:atom.link>');

        self::assertSame(
            '<a class="kern-link" href="/k">'
            . '<span class="kern-icon kern-icon--mail" aria-hidden="true"></span>'
            . 'Kontakt</a>',
            $rendered,
        );
    }

    #[Test]
    public function placesIconAfterLabelWhenAsked(): void
    {
        $rendered = $this->renderSource(
            '<k:atom.link href="/k" icon="arrow-forward" iconPosition="after">Weiter</k:atom.link>',
        );

        self::assertSame(
            '<a class="kern-link" href="/k">Weiter'
            . '<span class="kern-icon kern-icon--arrow-forward" aria-hidden="true"></span></a>',
            $rendered,
        );
    }

    #[Test]
    public function externalLinkAnnouncesTheContextChange(): void
    {
        // WCAG 3.2.5: opening a new tab has to be signalled, hence the trailing icon.
        $rendered = $this->renderSource(
            '<k:atom.link href="https://kern-ux.de" external="{true}">KERN</k:atom.link>',
        );

        self::assertSame(
            '<a class="kern-link" href="https://kern-ux.de" target="_blank" rel="noopener noreferrer">KERN'
            . '<span class="kern-icon kern-icon--open-in-new" aria-hidden="true"></span></a>',
            $rendered,
        );
    }

    #[Test]
    public function explicitIconWinsOverTheExternalDefault(): void
    {
        $rendered = $this->renderSource(
            '<k:atom.link href="https://x.de" external="{true}" icon="download">PDF</k:atom.link>',
        );

        self::assertStringContainsString('kern-icon--download', $rendered);
        self::assertStringNotContainsString('kern-icon--open-in-new', $rendered);
        self::assertStringContainsString('rel="noopener noreferrer"', $rendered);
    }

    #[Test]
    public function appliesModifiers(): void
    {
        $rendered = $this->renderSource(
            '<k:atom.link href="/p" small="{true}" stretched="{true}" noVisitedState="{true}">X</k:atom.link>',
        );

        self::assertStringContainsString(
            'class="kern-link kern-link--small kern-link--stretched kern-link--no-visited-state"',
            $rendered,
        );
    }
}
