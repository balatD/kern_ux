<?php

declare(strict_types=1);

namespace BalatD\KernUx\Form\Service;

use BalatD\KernUx\Form\Validation\KernDateValidator;
use TYPO3\CMS\Extbase\Validation\Validator\NotEmptyValidator;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\RootRenderableInterface;

/**
 * Decides whether a form field is required.
 *
 * Exists because ext:form's own `{element.required}` only looks for NotEmptyValidator,
 * and this matters more here than it would elsewhere: KERN marks the *optional* fields,
 * so a required field that is not recognised as such gets an "- Optional" marker - the
 * exact opposite of the truth. A composite validator like KernDateValidator, which
 * carries its own `required` option, is invisible to the built-in check.
 */
final class RequiredFieldDetector
{
    public function isRequired(RootRenderableInterface $element): bool
    {
        // Only form elements carry validators - a page or section does not, and the
        // interface is what makes that explicit rather than a method_exists guess.
        if (!$element instanceof FormElementInterface) {
            return false;
        }

        foreach ($element->getValidators() as $validator) {
            if ($validator instanceof NotEmptyValidator) {
                return true;
            }
            if ($validator instanceof KernDateValidator
                && ($validator->getOptions()['required'] ?? false) === true
            ) {
                return true;
            }
        }

        return false;
    }
}
