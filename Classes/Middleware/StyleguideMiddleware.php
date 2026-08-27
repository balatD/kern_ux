<?php

declare(strict_types=1);

namespace BalatD\KernUx\Middleware;

use BalatD\KernUx\Styleguide\StyleguideRenderer;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Serves the component gallery as a standalone page.
 *
 * A middleware rather than a page or plugin: the gallery has no editorial content and
 * should not depend on a page tree existing, which also makes it usable as an
 * accessibility-test target in a bare installation.
 *
 * Off unless a site opts in. It is a development and audit tool, and a public
 * extension must not quietly expose an extra route on production sites.
 */
final readonly class StyleguideMiddleware implements MiddlewareInterface
{
    public function __construct(
        private StyleguideRenderer $renderer,
        private ResponseFactoryInterface $responseFactory,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return $handler->handle($request);
        }

        $settings = $site->getSettings();
        if ($settings->get('kernUx.styleguide.enable') !== true) {
            return $handler->handle($request);
        }

        $configuredPath = $settings->get('kernUx.styleguide.path');
        $configuredPath = is_string($configuredPath) ? $configuredPath : '';
        if ($configuredPath === '' || !$this->matches($request, $configuredPath)) {
            return $handler->handle($request);
        }

        $assetBase = $settings->get('kernUx.assets.basePath');
        $markup = $this->renderer->render(is_string($assetBase) ? $assetBase : '', $request);

        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            // Never cached downstream: the gallery reflects the templates on disk.
            ->withHeader('Cache-Control', 'no-store');
        $response->getBody()->write($markup);

        return $response;
    }

    private function matches(ServerRequestInterface $request, string $configuredPath): bool
    {
        $normalise = static fn(string $path): string => '/' . trim($path, '/');

        return $normalise($request->getUri()->getPath()) === $normalise($configuredPath);
    }

}
