<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Components\Molecule;

use BalatD\KernUx\Tests\Functional\Components\AbstractComponentTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * KERN's alert is two boxes, and which of them exist is what the styling hangs off:
 * the coloured header supplies the top rounded corners, so an alert without a title
 * needs the body to supply them instead (see .kern-alert__body:first-child in
 * kernt3.css). That makes "is the body the alert's first child" a contract rather than
 * an implementation detail, which is why it is asserted here.
 */
final class AlertTest extends AbstractComponentTestCase
{
    #[Test]
    public function rendersTheVariantOnTheAlertAndAsTheIconName(): void
    {
        // KERN names its alert icons after the variants, so one value drives both.
        $rendered = $this->renderSource(
            '<k:molecule.alert variant="warning" title="Frist">Bis 31. März.</k:molecule.alert>',
        );

        self::assertStringContainsString('<div class="kern-alert kern-alert--warning">', $rendered);
        self::assertStringContainsString('kern-icon kern-icon--warning', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }

    #[Test]
    public function putsTheTitleInTheHeaderAndTheSlotInTheBody(): void
    {
        $rendered = $this->renderSource(
            '<k:molecule.alert variant="info" title="Information">Das Büro ist geschlossen.</k:molecule.alert>',
        );

        self::assertStringContainsString('<div class="kern-alert__header">', $rendered);
        self::assertStringContainsString('Information</h2>', $rendered);
        self::assertStringContainsString('<div class="kern-alert__body">Das Büro ist geschlossen.</div>', $rendered);
    }

    #[Test]
    public function omitsTheHeaderWithoutATitleAndLeavesTheBodyFirst(): void
    {
        // The one-line form from KERN's own examples. The body being the first child is
        // what the rounded-corner rule keys on.
        $rendered = $this->renderSource(
            '<k:molecule.alert variant="info">Nur Text, ohne Kopfzeile.</k:molecule.alert>',
        );

        self::assertStringNotContainsString('kern-alert__header', $rendered);
        self::assertStringContainsString(
            '<div class="kern-alert kern-alert--info"><div class="kern-alert__body">',
            $rendered,
        );
    }

    #[Test]
    public function omitsTheBodyWhenTheSlotIsEmpty(): void
    {
        // The body carries a white background and the bottom corners, so an empty one
        // renders as a blank strip under the heading rather than as nothing.
        $rendered = $this->renderSource('<k:molecule.alert variant="success" title="Erfolg" />');

        self::assertStringNotContainsString('kern-alert__body', $rendered);
        self::assertStringContainsString('kern-alert__header', $rendered);
    }

    #[Test]
    public function keepsTheTitleOutOfTheOutlineWhenAskedTo(): void
    {
        // "New registrations cost 30 euros" is a statement, not a section heading.
        $rendered = $this->renderSource(
            '<k:molecule.alert title="Gebühr" titleAsHeading="{false}">30 Euro.</k:molecule.alert>',
        );

        self::assertStringContainsString('<p class="kern-title">Gebühr</p>', $rendered);
        self::assertStringNotContainsString('<h2', $rendered);
    }

    #[Test]
    public function addsTheLiveRegionOnlyOnRequest(): void
    {
        // An editorially placed alert exists at page load, so an assertive live region
        // would interrupt a screen reader for something that is not news.
        $editorial = $this->renderSource('<k:molecule.alert title="Hinweis">Text.</k:molecule.alert>');
        $live = $this->renderSource('<k:molecule.alert title="Hinweis" live="{true}">Text.</k:molecule.alert>');

        self::assertStringNotContainsString('role="alert"', $editorial);
        self::assertStringContainsString('role="alert"', $live);
    }
}
