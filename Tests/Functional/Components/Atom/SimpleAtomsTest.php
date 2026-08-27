<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Atom;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Exact-markup contracts for the atoms without branching logic.
 */
final class SimpleAtomsTest extends AbstractComponentTestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function markupProvider(): array
    {
        return [
            // KERN styles badge text through `.kern-badge .kern-label`, so the label
            // span is not optional decoration - without it the text falls back to
            // whatever the surrounding context happens to be.
            'badge' => [
                '<k:atom.badge variant="success">Erledigt</k:atom.badge>',
                '<span class="kern-badge kern-badge--success"><span class="kern-label">Erledigt</span></span>',
            ],
            'badge small' => [
                '<k:atom.badge variant="danger" small="{true}">Fehler</k:atom.badge>',
                '<span class="kern-badge kern-badge--danger kern-badge--small"><span class="kern-label">Fehler</span></span>',
            ],
            'badge with decorative icon' => [
                '<k:atom.badge variant="success" icon="success">Erledigt</k:atom.badge>',
                '<span class="kern-badge kern-badge--success">'
                . '<span class="kern-icon kern-icon--success" aria-hidden="true"></span>'
                . '<span class="kern-label">Erledigt</span></span>',
            ],
            'body' => [
                '<k:atom.body>Text</k:atom.body>',
                '<p class="kern-body">Text</p>',
            ],
            'body muted span' => [
                '<k:atom.body variant="muted" tag="span">Text</k:atom.body>',
                '<span class="kern-body kern-body--muted">Text</span>',
            ],
            'divider decorative by default' => [
                '<k:atom.divider />',
                '<hr class="kern-divider kern-divider--decorative" aria-hidden="true" />',
            ],
            'divider semantic' => [
                '<k:atom.divider decorative="{false}" />',
                '<hr class="kern-divider" />',
            ],
            'hint' => [
                '<k:atom.hint id="name-hint">Wie im Ausweis.</k:atom.hint>',
                '<div class="kern-hint" id="name-hint">Wie im Ausweis.</div>',
            ],
            'error' => [
                '<k:atom.error id="name-error">Pflichtfeld</k:atom.error>',
                '<p class="kern-error" id="name-error">'
                . '<span class="kern-icon kern-icon--danger" aria-hidden="true"></span>'
                . '<span class="kern-body">Pflichtfeld</span></p>',
            ],
            'progress' => [
                '<k:atom.progress id="p" value="2" max="5" label="Schritt 2 von 5" />',
                '<div class="kern-progress">'
                . '<label class="kern-label" for="p">Schritt 2 von 5</label>'
                . '<progress id="p" value="2" max="5"></progress></div>',
            ],
            'loader' => [
                '<k:atom.loader label="Wird geladen" />',
                '<div class="kern-loader kern-loader--visible" role="status">'
                . '<span class="kern-sr-only">Wird geladen</span></div>',
            ],
            'list bullet' => [
                '<k:atom.list variant="bullet"><li>Eins</li></k:atom.list>',
                '<ul class="kern-list kern-list--bullet"><li>Eins</li></ul>',
            ],
            'list ordered' => [
                '<k:atom.list tag="ol" variant="number"><li>Eins</li></k:atom.list>',
                '<ol class="kern-list kern-list--number"><li>Eins</li></ol>',
            ],
        ];
    }

    #[Test]
    #[DataProvider('markupProvider')]
    public function rendersExpectedKernMarkup(string $source, string $expected): void
    {
        $rendered = $this->renderSource($source);

        self::assertSame($expected, $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function fieldErrorStaysSilentUnlessExplicitlyAnnounced(): void
    {
        // On a server-rendered page there is nothing for role=alert to announce; it
        // only earns its place when the message appears after load.
        self::assertStringNotContainsString(
            'role="alert"',
            $this->renderSource('<k:atom.error id="e">X</k:atom.error>'),
        );
        self::assertStringContainsString(
            'role="alert"',
            $this->renderSource('<k:atom.error id="e" alert="{true}">X</k:atom.error>'),
        );
    }
}
