<?php

declare(strict_types=1);

namespace BalatD\KernUx\Styleguide;

final readonly class ComponentExample
{
    public function __construct(
        public string $component,
        public string $label,
        public string $source,
        public string $markup,
        public string $note = '',
        /**
         * Set for examples that demonstrate *absence* - a hidden heading, an empty
         * footer. Without it the "every example renders something" guard would force
         * such behaviour to go undocumented.
         */
        public bool $rendersNothing = false,
    ) {}
}
