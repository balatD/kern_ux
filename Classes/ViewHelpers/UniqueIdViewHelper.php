<?php

declare(strict_types=1);

namespace BalatD\KernUx\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Builds a DOM id that is unique per record.
 *
 * Components wire aria-describedby, aria-controls and label/for by id, and the same
 * content block can appear several times on one page - so ids derived from the record
 * uid rather than from the field name. Derived, not random: a cached page and a fresh
 * one must produce the same document, and a random id would also break any anchor
 * pointing at it.
 */
final class UniqueIdViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('prefix', 'string', 'Semantic prefix, e.g. "accordion".', true);
        $this->registerArgument('uid', 'int', 'Record uid the id belongs to.', true);
        $this->registerArgument('suffix', 'string', 'Distinguishes several ids within one record.', false, '');
    }

    public function render(): string
    {
        // Fluid hands arguments back as mixed, so the boundary is checked rather than
        // cast - a non-scalar here would otherwise become the string "Array".
        $prefix = $this->arguments['prefix'] ?? null;
        $uid = $this->arguments['uid'] ?? null;

        $parts = [
            'kern',
            is_string($prefix) ? $prefix : '',
            is_int($uid) || is_string($uid) ? (string)$uid : '0',
        ];

        $suffix = $this->arguments['suffix'] ?? null;
        if (is_string($suffix) && $suffix !== '') {
            $parts[] = $suffix;
        }

        return implode('-', array_map(
            static fn(string $part): string => strtolower(
                (string)preg_replace('/[^A-Za-z0-9]+/', '-', $part),
            ),
            $parts,
        ));
    }
}
