<?php

declare(strict_types=1);

namespace BalatD\KernUx\Form\FormElements;

use TYPO3\CMS\Form\Domain\Model\FormElements\AbstractFormElement;

/**
 * A date entered as three separate fields: day, month, year.
 *
 * This is what KERN prescribes, and it deliberately rejects both alternatives:
 * `<input type="date">` hands over a browser-specific picker whose keyboard behaviour
 * and announcement cannot be controlled, and a JavaScript datepicker fails without
 * JavaScript. Three plain numeric text fields work everywhere, are dictatable, and can
 * be corrected one part at a time.
 *
 * TYPO3 14 deprecated its own DatePicker element (#109152), so this is also the
 * forward-looking choice.
 *
 * The submitted value is an array with the keys day, month and year. It is left as an
 * array on purpose: a finisher usually wants to print what the visitor typed, and
 * silently normalising "31.02." into a shifted date would hide a mistake rather than
 * report it. KernDateValidator is what rejects impossible dates.
 */
class KernDate extends AbstractFormElement
{
    public function initializeFormElement(): void
    {
        $this->setDataType('array');
        parent::initializeFormElement();
    }
}
