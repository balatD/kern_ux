<?php

declare(strict_types=1);

namespace BalatD\KernUx\ViewHelpers;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Error\Result;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Collects every field error of a form, in field order, for the error summary.
 *
 * KERN requires an error summary but ships no component for it: its documentation
 * lists WCAG 4.1.3 ("the error summary is marked up as a status message") as
 * implementation-dependent. So the summary itself is ours, and this ViewHelper
 * supplies its content.
 *
 * Built in PHP rather than Fluid because the summary needs to walk every element of
 * every page and ask each one whether it failed - which in Fluid means nesting
 * f:form.validationResults inside a loop and reassembling ids by string surgery.
 *
 * Order follows the form definition, not the order errors happened to be recorded:
 * a summary whose entries do not match the visual order of the fields makes the page
 * harder to work through, not easier.
 *
 * Each entry links to something that can actually take focus. For a plain field that is
 * the element's own id, but a grouped field puts that id on the <fieldset> - and a
 * fieldset is not focusable, so the link would scroll and then drop the focus. Those
 * elements therefore point at their first control instead. The suffixes are the ones the
 * partials build (see GROUP_FIRST_CONTROL).
 */
final class FormErrorSummaryViewHelper extends AbstractViewHelper
{
    /**
     * Element type => id suffix of its first focusable control.
     *
     * RadioButton and MultiCheckbox number their options from zero
     * ("{uniqueIdentifier}-{i.index}"); KernDate names its three parts. Anything absent
     * from this map carries its id on the control itself.
     *
     * @var array<string, string>
     */
    private const GROUP_FIRST_CONTROL = [
        'RadioButton' => '-0',
        'MultiCheckbox' => '-0',
        'KernDate' => '-day',
    ];

    public function initializeArguments(): void
    {
        $this->registerArgument('form', FormRuntime::class, 'The form runtime.', true);
    }

    /**
     * @return list<array{id: string, label: string, message: string}>
     */
    public function render(): array
    {
        $form = $this->arguments['form'] ?? null;
        if (!$form instanceof FormRuntime) {
            return [];
        }

        $results = $this->mappingResults();
        if ($results === null) {
            return [];
        }

        $formIdentifier = $form->getFormDefinition()->getIdentifier();
        $summary = [];

        foreach ($form->getFormDefinition()->getElements() as $element) {
            $elementResults = $results->forProperty($formIdentifier . '.' . $element->getIdentifier());
            $errors = $elementResults->getErrors();
            if ($errors === []) {
                continue;
            }

            $label = $element->getLabel();
            $summary[] = [
                'id' => $element->getUniqueIdentifier()
                    . (self::GROUP_FIRST_CONTROL[$element->getType()] ?? ''),
                'label' => $label !== '' ? $label : $element->getIdentifier(),
                // The first error is enough: the summary points at the field, and the
                // field itself shows every message it has.
                'message' => $errors[0]->getMessage(),
            ];
        }

        return $summary;
    }

    private function mappingResults(): ?Result
    {
        $context = $this->renderingContext;
        if ($context === null || !$context->hasAttribute(ServerRequestInterface::class)) {
            return null;
        }

        $request = $context->getAttribute(ServerRequestInterface::class);
        if (!$request instanceof RequestInterface) {
            return null;
        }

        $extbaseParameters = $request->getAttribute('extbase');
        if (!is_object($extbaseParameters) || !method_exists($extbaseParameters, 'getOriginalRequestMappingResults')) {
            return null;
        }

        $results = $extbaseParameters->getOriginalRequestMappingResults();

        return $results instanceof Result ? $results : null;
    }
}
