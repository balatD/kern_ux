<?php

declare(strict_types=1);

namespace BalatD\KernUx\Styleguide;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Renders the component gallery.
 *
 * Shared by the frontend route and the dump command so the accessibility tests check
 * exactly the document a visitor would get, not a second implementation of it.
 */
final readonly class StyleguideRenderer
{
    public const TEMPLATE = 'EXT:kern_ux/Resources/Private/Styleguide/Styleguide.html';

    public const DEFAULT_ASSET_BASE = 'EXT:kern_ux/Resources/Public/Vendor/KernUx/';

    public function __construct(
        private ComponentCatalog $catalog,
        private ViewFactoryInterface $viewFactory,
    ) {}

    public function render(
        string $assetBase = self::DEFAULT_ASSET_BASE,
        ?ServerRequestInterface $request = null,
    ): string {
        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: ['EXT:kern_ux/Resources/Private/Styleguide/'],
            templatePathAndFilename: self::TEMPLATE,
            request: $request,
        ));
        $view->assignMultiple([
            'assetBase' => $assetBase !== '' ? $assetBase : self::DEFAULT_ASSET_BASE,
            'groups' => $this->groupByComponent(),
            'undocumented' => $this->catalog->undocumentedComponents(),
        ]);

        return $view->render();
    }

    /**
     * @return array<string, list<ComponentExample>>
     */
    private function groupByComponent(): array
    {
        $groups = [];
        foreach ($this->catalog->examples() as $example) {
            $groups[$example->component][] = $example;
        }

        return $groups;
    }
}
