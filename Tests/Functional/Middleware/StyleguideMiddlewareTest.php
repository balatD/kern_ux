<?php

declare(strict_types=1);

namespace BalatD\KernUx\Tests\Functional\Middleware;

use BalatD\KernUx\Middleware\StyleguideMiddleware;
use BalatD\KernUx\Styleguide\StyleguideRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The gallery is an extra public route in an extension anybody can install, so what it
 * refuses matters more than what it serves. Nothing asserted any of it before.
 */
final class StyleguideMiddlewareTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['form'];

    protected array $testExtensionsToLoad = [
        'friendsoftypo3/content-blocks',
        'balatd/kern-ux',
    ];

    private ApplicationContext $originalContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalContext = Environment::getContext();
    }

    protected function tearDown(): void
    {
        $this->setContext((string)$this->originalContext);
        parent::tearDown();
    }

    #[Test]
    public function isOffUnlessTheSiteSwitchesItOn(): void
    {
        $this->setContext('Development');
        $response = $this->process($this->request('/kern-ux-styleguide', ['kernUx.styleguide.enable' => false]));

        // The default for kernUx.styleguide.enable is false, and this is what makes that
        // default mean something.
        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function servesTheGalleryInDevelopmentWhenEnabled(): void
    {
        $this->setContext('Development');
        $response = $this->process($this->request('/kern-ux-styleguide'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('kern-', (string)$response->getBody());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'));
    }

    #[Test]
    public function doesNotServeItToAnAnonymousVisitorInProduction(): void
    {
        $this->setContext('Production');
        $response = $this->process($this->request('/kern-ux-styleguide'));

        // Enabled in the settings, but still not a public route: an extension must not
        // put one on a production site on the strength of a boolean alone.
        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function servesItToALoggedInBackendUserInProduction(): void
    {
        $this->setContext('Production');
        $response = $this->process($this->request('/kern-ux-styleguide', backendUserId: 1));

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function ignoresABackendUserWhoIsNotActuallyLoggedIn(): void
    {
        $this->setContext('Production');
        $response = $this->process($this->request('/kern-ux-styleguide', backendUserId: 0));

        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function pathProvider(): array
    {
        return [
            'configured path' => ['/kern-ux-styleguide', '/kern-ux-styleguide', true],
            'without leading slash' => ['/kern-ux-styleguide', 'kern-ux-styleguide', true],
            'with trailing slash' => ['/kern-ux-styleguide/', '/kern-ux-styleguide', true],
            'a different path' => ['/imprint', '/kern-ux-styleguide', false],
            'a prefix of it' => ['/kern-ux', '/kern-ux-styleguide', false],
            'custom path' => ['/design-system', '/design-system', true],
        ];
    }

    #[Test]
    #[DataProvider('pathProvider')]
    public function matchesThePathWithinTheSite(string $tail, string $configured, bool $expected): void
    {
        $this->setContext('Development');
        $request = $this->request($tail, ['kernUx.styleguide.path' => $configured]);
        $response = $this->process($request);

        self::assertSame($expected ? 200 : 404, $response->getStatusCode());
    }

    #[Test]
    public function matchesOnASiteThatIsNotBasedAtTheRoot(): void
    {
        $this->setContext('Development');

        // The routing result is what the site middleware leaves behind, and its tail is
        // the path *within* the site. Matching the raw request path instead made the
        // gallery unreachable on any subdirectory install or language prefix, while the
        // setting still claimed to be "relative to the site root".
        $request = $this->request('/kern-ux-styleguide', uri: 'https://example.com/de/kern-ux-styleguide');
        $response = $this->process($request);

        self::assertSame(200, $response->getStatusCode());
    }

    private function setContext(string $context): void
    {
        Environment::initialize(
            new ApplicationContext($context),
            Environment::isCli(),
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            Environment::getCurrentScript(),
            Environment::isWindows() ? 'WINDOWS' : 'UNIX',
        );
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function request(
        string $tail,
        array $settings = [],
        ?int $backendUserId = null,
        ?string $uri = null,
    ): ServerRequestInterface {
        $settings += ['kernUx.styleguide.enable' => true, 'kernUx.styleguide.path' => '/kern-ux-styleguide'];

        $site = new Site('kern', 1, [
            'base' => 'https://example.com/',
            'settings' => $this->nest($settings),
            'languages' => [
                ['languageId' => 0, 'title' => 'English', 'locale' => 'en_US.UTF-8', 'base' => '/'],
            ],
        ]);

        $request = new ServerRequest($uri ?? 'https://example.com' . $tail, 'GET');
        $request = $request
            // The gallery template resolves asset URIs, which needs both of these.
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request))
            ->withAttribute('site', $site)
            ->withAttribute('language', $site->getDefaultLanguage())
            ->withAttribute('routing', new SiteRouteResult($request->getUri(), $site, $site->getDefaultLanguage(), $tail));

        if ($backendUserId !== null) {
            $backendUser = new \TYPO3\CMS\Core\Authentication\BackendUserAuthentication();
            $backendUser->user = $backendUserId > 0 ? ['uid' => $backendUserId] : null;
            $request = $request->withAttribute('backend.user', $backendUser);
        }

        return $request;
    }

    /**
     * Site settings arrive as a nested tree, not as dotted keys.
     *
     * @param array<string, mixed> $flat
     * @return array<string, mixed>
     */
    private function nest(array $flat): array
    {
        $tree = [];
        foreach ($flat as $path => $value) {
            $tree = $this->place($tree, explode('.', $path), $value);
        }

        return $tree;
    }

    /**
     * @param array<string, mixed> $tree
     * @param list<string> $segments
     * @return array<string, mixed>
     */
    private function place(array $tree, array $segments, mixed $value): array
    {
        $key = array_shift($segments);
        if ($key === null) {
            return $tree;
        }
        if ($segments === []) {
            $tree[$key] = $value;

            return $tree;
        }

        $child = $tree[$key] ?? [];
        if (!is_array($child)) {
            $child = [];
        }
        /** @var array<string, mixed> $child */
        $tree[$key] = $this->place($child, $segments, $value);

        return $tree;
    }

    private function process(ServerRequestInterface $request): ResponseInterface
    {
        // The real renderer, not a double: StyleguideRenderer is final readonly, and
        // giving it an interface purely so this test could stub it would widen
        // production code for no other reason. It is only reached on the paths that
        // expect a 200 anyway.
        $renderer = $this->get(StyleguideRenderer::class);
        self::assertInstanceOf(StyleguideRenderer::class, $renderer);

        $middleware = new StyleguideMiddleware($renderer, new ResponseFactory());

        // Stands in for the rest of the frontend stack: anything the middleware declines
        // to answer ends up here, which on an unknown path is a 404.
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response('php://temp', 404);
            }
        };

        return $middleware->process($request, $handler);
    }
}
