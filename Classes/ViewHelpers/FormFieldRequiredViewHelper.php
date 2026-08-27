<?php

declare(strict_types=1);

namespace BalatD\KernUx\ViewHelpers;

use BalatD\KernUx\Form\Service\RequiredFieldDetector;
use TYPO3\CMS\Form\Domain\Model\Renderable\RootRenderableInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Whether a form field is required.
 *
 * Templates use this instead of {element.required}: KERN marks optional fields, so
 * getting this wrong labels a required field "- Optional".
 */
final class FormFieldRequiredViewHelper extends AbstractViewHelper
{
    public function __construct(private readonly RequiredFieldDetector $detector) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('element', RootRenderableInterface::class, 'The form element.', true);
    }

    public function render(): bool
    {
        $element = $this->arguments['element'] ?? null;

        return $element instanceof RootRenderableInterface && $this->detector->isRequired($element);
    }
}
