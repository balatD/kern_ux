<?php

declare(strict_types=1);

namespace BalatD\KernUx\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Formats a KernDate value for display.
 *
 * KernDate maps to an array of day, month and year rather than to a DateTime, and that
 * is deliberate: property mapping runs before validation, so a DateTime target would
 * turn "31.02." into a mapping failure and cost KernDateValidator its precise error
 * codes. The array is therefore what every consumer receives - and an array is exactly
 * what none of them can print.
 *
 * ext:form's own escape hatch does not reach this case. RenderFormValueViewHelper only
 * calls StringableFormElementInterface::valueToString() when the value is an *object*
 * (`is_object($value)`); an array without an `options` property falls through and is
 * returned unchanged. So the conversion has to happen where the value is rendered, and
 * this is the single place it does.
 *
 * An incomplete date yields an empty string rather than a guess: the summary shows its
 * "not provided" placeholder, which is the truth about what the visitor entered.
 */
final class FormDateValueViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('value', 'mixed', 'The KernDate value: day, month, year.', true);
        $this->registerArgument(
            'format',
            'string',
            'PHP date format. The default is the unambiguous German administrative form.',
            false,
            'd.m.Y',
        );
    }

    public function render(): string
    {
        $value = $this->arguments['value'] ?? null;
        if (!is_array($value)) {
            return '';
        }

        $parts = [];
        foreach (['year', 'month', 'day'] as $part) {
            $raw = $value[$part] ?? null;
            if (!is_string($raw) && !is_int($raw)) {
                return '';
            }
            $raw = trim((string)$raw);
            if ($raw === '' || !ctype_digit($raw)) {
                return '';
            }
            $parts[$part] = $raw;
        }

        // checkdate() before DateTimeImmutable for the same reason the validator does it:
        // createFromFormat happily rolls 31.02. over into 03.03.
        if (!checkdate((int)$parts['month'], (int)$parts['day'], (int)$parts['year'])) {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            sprintf('%04d-%02d-%02d', $parts['year'], $parts['month'], $parts['day']),
        );
        if ($date === false) {
            return '';
        }

        $format = $this->arguments['format'] ?? 'd.m.Y';

        return $date->format(is_string($format) && $format !== '' ? $format : 'd.m.Y');
    }
}
