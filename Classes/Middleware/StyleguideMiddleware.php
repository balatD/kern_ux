<?php

declare(strict_types=1);

namespace BalatD\KernUx\Middleware;

use BalatD\KernUx\Styleguide\StyleguideRenderer;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Serves the component gallery as a standalone page.
 *
 * A middleware rather than a page or plugin: the gallery has no editorial content and
 * should not depend on a page tree existing, which also makes it usable as an
 * accessibility-test target in a bare installation.
 *
 * Two gates, not one. The site setting is off by default and has to be switched on
 * deliberately - but on a production site that alone would put an extra public route on
 * every site whose settings said yes, which is exactly what a public extension must not
 * do quietly. So in Production the gallery additionally requires a logged-in backend
 * user, the same shape of gate the admin panel uses. In Development it is simply on,
 * because that is where it is used.
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

        if (!$this->isPermitted($request)) {
            // Handled as if the route did not exist, rather than answered with a 403:
            // whether this site has a gallery configured is not something an anonymous
            // visitor needs to learn.
            return $handler->handle($request);
        }

        $assetBase = $settings->get('kernUx.assets.basePath');
        $markup = $this->renderer->render(is_string($assetBase) ? $assetBase : '', $request);

        $response = $this->responseFactory->createResponse()
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            // Never cached downstream: the gallery reflects the templates on disk.
            ->withHeader('Cache-Control', 'no-store')
            // The document carries a robots meta tag too, but a header also covers the
            // crawlers that never parse the body.
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->getBody()->write($markup);

        return $response;
    }

    /**
     * Development, or a backend user who is already logged in.
     */
    private function isPermitted(ServerRequestInterface $request): bool
    {
        if (Environment::getContext()->isDevelopment()) {
            return true;
        }

        $backendUser = $request->getAttribute('backend.user');
        if (!$backendUser instanceof AbstractUserAuthentication) {
            return false;
        }
        $uid = $backendUser->user['uid'] ?? null;

        return is_numeric($uid) && (int)$uid > 0;
    }

    /**
     * Compares against the path *within* the site, which is what the setting documents.
     *
     * The raw request path carries the site's own base and the language prefix, so
     * matching on it meant the gallery was unreachable on any site not based at "/" -
     * a subdirectory installation, or a language with a base like "/de/". The site
     * middleware has already worked that out and left the remainder in the routing
     * result, so this only has to normalise the slashes.
     */
    private function matches(ServerRequestInterface $request, string $configuredPath): bool
    {
        $routeResult = $request->getAttribute('routing');
        $path = $routeResult instanceof SiteRouteResult
            ? $routeResult->getTail()
            : $request->getUri()->getPath();

        $normalise = static fn(string $candidate): string => '/' . trim($candidate, '/');

        return $normalise($path) === $normalise($configuredPath);
    }
}
