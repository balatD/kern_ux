<?php

declare(strict_types=1);

namespace BalatD\KernUx\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Maps TYPO3's spacing values onto KERN's margin utilities.
 *
 * Content Blocks reuse the core `space_before_class` / `space_after_class` fields
 * rather than inventing their own, so existing content stays migratable - but core's
 * values ("extra-small" … "extra-large") and KERN's t-shirt scale (xs … xl) are two
 * different vocabularies. This is the single place they meet.
 */
final class SpacingClassViewHelper extends AbstractViewHelper
{
    /**
     * Core value => KERN scale step. Anything not listed - including core's empty
     * "default" value - yields no class, which leaves the block on the default rhythm
     * that Molecule/Section applies between siblings. An editor's choice is added as a
     * KERN margin utility and overrides that default, in either direction.
     */
    private const SCALE = [
        // Not one of core's own values: core offers no way to say "no gap", and once
        // blocks have a default rhythm an editor needs to be able to close it. Added to
        // both fields in Configuration/TCA/Overrides/tt_content.php.
        'none' => 'none',
        'extra-small' => 'xs',
        'small' => 'sm',
        'medium' => 'md',
        'large' => 'lg',
        'extra-large' => 'xl',
    ];

    public function initializeArguments(): void
    {
        $this->registerArgument('before', 'string', 'Value of the space_before_class field.', false, '');
        $this->registerArgument('after', 'string', 'Value of the space_after_class field.', false, '');
    }

    public function render(): string
    {
        $classes = [];

        $before = $this->step($this->arguments['before'] ?? null);
        if ($before !== null) {
            $classes[] = 'kern-mt-' . $before;
        }

        $after = $this->step($this->arguments['after'] ?? null);
        if ($after !== null) {
            $classes[] = 'kern-mb-' . $after;
        }

        return implode(' ', $classes);
    }

    private function step(mixed $value): ?string
    {
        return is_string($value) ? (self::SCALE[$value] ?? null) : null;
    }
}
