<?php

declare(strict_types=1);

namespace BalatD\KernUx\Form\Validation;

use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;

/**
 * Validates a three-part date from the KernDate element.
 *
 * One validator for the whole date rather than one per field: "31" is a perfectly good
 * day and "02" a perfectly good month, but not together. A per-field validator cannot
 * see that, and would also produce three error messages for one mistake - which is
 * both noisier and less useful than one message on the group.
 */
final class KernDateValidator extends AbstractValidator
{
    /**
     * Full LLL reference, not a bare key: translateErrorMessage() would otherwise look
     * in locallang.xlf, and these messages live with the rest of the form labels.
     */
    private const LANG = 'LLL:EXT:kern_ux/Resources/Private/Language/locallang_form.xlf:';

    /**
     * @var array<string, mixed>
     */
    protected $supportedOptions = [
        'required' => [false, 'Whether a date has to be entered at all.', 'boolean'],
        'earliest' => ['', 'Earliest allowed date, as Y-m-d.', 'string'],
        'latest' => ['', 'Latest allowed date, as Y-m-d.', 'string'],
    ];

    protected function isValid(mixed $value): void
    {
        $parts = is_array($value) ? $value : [];
        $day = $this->part($parts, 'day');
        $month = $this->part($parts, 'month');
        $year = $this->part($parts, 'year');

        if ($day === '' && $month === '' && $year === '') {
            if ($this->options['required'] === true) {
                $this->addError($this->translateErrorMessage(self::LANG . 'validation.error.kernDate.empty', 'kern_ux'), 1756150001);
            }

            return;
        }

        // A partially filled date is its own error case: telling somebody their date is
        // invalid when they simply have not finished typing it is unhelpful.
        if ($day === '' || $month === '' || $year === '') {
            $this->addError($this->translateErrorMessage(self::LANG . 'validation.error.kernDate.incomplete', 'kern_ux'), 1756150002);

            return;
        }

        if (!ctype_digit($day) || !ctype_digit($month) || !ctype_digit($year)) {
            $this->addError($this->translateErrorMessage(self::LANG . 'validation.error.kernDate.notNumeric', 'kern_ux'), 1756150003);

            return;
        }

        if (!checkdate((int)$month, (int)$day, (int)$year)) {
            $this->addError($this->translateErrorMessage(self::LANG . 'validation.error.kernDate.invalid', 'kern_ux'), 1756150004);

            return;
        }

        $date = \DateTimeImmutable::createFromFormat(
            '!Y-n-j',
            sprintf('%d-%d-%d', (int)$year, (int)$month, (int)$day),
        );
        if ($date === false) {
            $this->addError($this->translateErrorMessage(self::LANG . 'validation.error.kernDate.invalid', 'kern_ux'), 1756150005);

            return;
        }

        $this->checkRange($date);
    }

    /**
     * @param array<mixed> $parts
     */
    private function part(array $parts, string $key): string
    {
        $value = $parts[$key] ?? null;

        return is_scalar($value) ? trim((string)$value) : '';
    }

    private function checkRange(\DateTimeImmutable $date): void
    {
        $earliest = $this->boundary('earliest');
        if ($earliest !== null && $date < $earliest) {
            $this->addError($this->translateErrorMessage(self::LANG . 'validation.error.kernDate.tooEarly', 'kern_ux'), 1756150006);
        }

        $latest = $this->boundary('latest');
        if ($latest !== null && $date > $latest) {
            $this->addError($this->translateErrorMessage(self::LANG . 'validation.error.kernDate.tooLate', 'kern_ux'), 1756150007);
        }
    }

    private function boundary(string $option): ?\DateTimeImmutable
    {
        $raw = $this->options[$option] ?? '';
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);

        return $date === false ? null : $date;
    }
}
