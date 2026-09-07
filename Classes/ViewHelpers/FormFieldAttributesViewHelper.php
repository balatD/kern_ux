<?php

declare(strict_types=1);

namespace BalatD\KernUx\ViewHelpers;

use BalatD\KernUx\Form\Service\RequiredFieldDetector;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Builds the ARIA attributes a KERN form control needs.
 *
 * This exists so the rules live in one place instead of in every one of the ~28
 * element partials, because KERN's form contract is unusually easy to get half-right:
 *
 *   - aria-required instead of the native required attribute. KERN's reasoning is that
 *     native validation brings browser UI nobody can style, and a field can still be
 *     announced as required without it.
 *   - aria-invalid whenever the field has an error, in addition to the two error
 *     classes. State conveyed by colour alone fails WCAG 1.4.1.
 *   - aria-describedby listing the hint first and the error second, which is the order
 *     KERN's own reference implementation uses. (Its React kit uses the opposite order;
 *     the plain kit is the reference, so this follows the plain kit.)
 *
 * The ids are derived from the element's unique identifier, so this and the field
 * wrapper agree on them without passing anything around.
 *
 * It also carries the element's own `fluidAdditionalAttributes` through. Overriding
 * `additionalAttributes` outright - which is what a partial does by calling this - would
 * otherwise drop them, and with them everything the form editor writes into that
 * property: `autocomplete` above all, without which WCAG 1.3.5 cannot be satisfied at
 * all, plus `placeholder`, `minlength`/`maxlength`, `min`/`max`, `step` and `pattern`.
 * An editor fills those in and would never learn they were discarded.
 */
final class FormFieldAttributesViewHelper extends AbstractViewHelper
{
    public function __construct(private readonly RequiredFieldDetector $detector) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('element', FormElementInterface::class, 'The form element.', true);
        // mixed, not bool: templates pass through whatever Fluid gives them - an
        // error object, a translated string, null - and coercing that here is more
        // honest than making every partial normalise it first.
        $this->registerArgument('hasErrors', 'mixed', 'Truthy when the field has validation errors.', false, false);
        $this->registerArgument('hasHint', 'mixed', 'Truthy when a hint is rendered for this field.', false, false);
        // A checkbox or radio inside a group is a "child": KERN puts aria-describedby
        // on the fieldset, so repeating it on every option would make a screen reader
        // read the hint and the error once per option.
        $this->registerArgument('scope', 'string', 'control (the labelled control itself) or child (one option inside a group).', false, 'control');
        // Passed in rather than read off the element, so the values arrive already
        // translated: ext:form resolves a property like `placeholder` through the form's
        // own translation file, and only formvh:translateElementProperty knows how.
        $this->registerArgument('additional', 'mixed', "The element's translated fluidAdditionalAttributes.", false, null);
    }

    /**
     * @return array<string, string>
     */
    public function render(): array
    {
        $element = $this->arguments['element'] ?? null;
        if (!$element instanceof FormElementInterface) {
            return [];
        }

        $id = $element->getUniqueIdentifier();
        $attributes = $this->additional();

        if ($this->arguments['scope'] === 'child') {
            // Every option still needs its own invalid state: the fieldset's error
            // alone leaves the individual controls looking untouched, which conveys
            // the state by colour only (WCAG 1.4.1).
            if ($this->flag('hasErrors')) {
                $attributes['aria-invalid'] = 'true';
            }

            return $attributes;
        }

        $describedBy = [];
        if ($this->flag('hasHint')) {
            $describedBy[] = $id . '-hint';
        }
        if ($this->flag('hasErrors')) {
            $describedBy[] = $id . '-error';
        }
        if ($describedBy !== []) {
            $attributes['aria-describedby'] = implode(' ', $describedBy);
        }

        if ($this->flag('hasErrors')) {
            $attributes['aria-invalid'] = 'true';
        }

        if ($this->detector->isRequired($element)) {
            $attributes['aria-required'] = 'true';
        }

        return $attributes;
    }

    /**
     * The element's own attributes, as the floor the ARIA map is laid on top of.
     *
     * Three attributes are dropped rather than merged: aria-describedby, aria-invalid
     * and aria-required are this ViewHelper's own output, and an editor who set them by
     * hand would silently unhook the hint and error wiring the field wrapper builds.
     * class and id go too - the partial passes those as their own arguments, so keeping
     * them here would emit the attribute twice.
     *
     * @return array<string, string>
     */
    private function additional(): array
    {
        $additional = $this->arguments['additional'] ?? null;
        if (!is_array($additional)) {
            return [];
        }

        $owned = ['aria-describedby', 'aria-invalid', 'aria-required', 'class', 'id'];
        $attributes = [];
        foreach ($additional as $name => $value) {
            if (!is_string($name) || $name === '' || in_array(strtolower($name), $owned, true)) {
                continue;
            }
            // Scalars only. An array would be a nested property the form editor never
            // writes, and stringifying it would put "Array" into the markup.
            if (!is_scalar($value)) {
                continue;
            }
            $value = (string)$value;
            if ($value === '') {
                continue;
            }
            $attributes[$name] = $value;
        }

        return $attributes;
    }

    private function flag(string $name): bool
    {
        $value = $this->arguments[$name] ?? null;
        if (is_string($value)) {
            return trim($value) !== '' && $value !== '0';
        }

        return $value !== null && $value !== false && $value !== [] && $value !== 0;
    }

}
