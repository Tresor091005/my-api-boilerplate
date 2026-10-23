<?php

declare(strict_types=1);

use App\Http\Middleware\ResolveResponseContext;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Lahatre\ResponseSelection\ResolveResponseSelection;
use Lahatre\ResponseSelection\ResponseDeclarationAnalyzer;
use Lahatre\ResponseSelection\ResponseDeclarationRegistry;
use Lahatre\ResponseSelection\Tests\Support\Example\ExampleTransferController;
use Lahatre\Shared\Registries\ResponseContractRegistry;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * A router with one action rendering through ApiResponse (`declared`) and one legacy closure route.
 */
function selectionIntegrationRouter(): Router
{
    $router = new Router(app('events'), app());
    $router->get('declared', [ExampleTransferController::class, 'index'])->middleware('api');
    $router->get('legacy', fn () => null)->middleware('api');

    return $router;
}

function selectionIntegrationRoute(Router $router, string $uri): Route
{
    foreach ($router->getRoutes()->getRoutes() as $route) {
        if ($route->uri() === $uri) {
            return $route;
        }
    }

    throw new LogicException("No route {$uri}.");
}

function selectionIntegrationRegistry(Router $router): ResponseDeclarationRegistry
{
    return new ResponseDeclarationRegistry($router, new ResponseDeclarationAnalyzer, sys_get_temp_dir().'/missing-response-declarations.php');
}

it('builds the declarations of every application route without error', function (): void {
    $built = app(ResponseDeclarationRegistry::class)->build();

    expect($built)->toHaveKey('Lahatre\\Customer\\Http\\Controllers\\CustomerController@index')
        ->and(count($built))->toBeGreaterThan(70);
});

it('lets the legacy ResolveResponseContext skip a declared route and still reject a legacy one without contract', function (): void {
    $router = selectionIntegrationRouter();
    app()->instance(ResponseDeclarationRegistry::class, selectionIntegrationRegistry($router));
    $handle = function (string $uri) use ($router): SymfonyResponse {
        $request = Request::create("/{$uri}");
        $request->setRouteResolver(fn () => selectionIntegrationRoute($router, $uri));

        return app(ResolveResponseContext::class)->handle($request, fn () => new Response('controller'));
    };

    expect($handle('declared')->getContent())->toBe('controller')
        ->and(fn () => $handle('legacy'))->toThrow(InvalidArgumentException::class, 'has no registered response contract');
});

it('requires a response contract only from the legacy api routes in the architecture test', function (): void {
    $router = selectionIntegrationRouter();

    $failures = routesMissingResponseContract($router->getRoutes()->getRoutes(), new ResponseContractRegistry, selectionIntegrationRegistry($router));

    expect($failures)->toHaveCount(1)
        ->and($failures[0])->toStartWith('Route [legacy] (GET|HEAD)');
});

it('runs in the api group, after authentication when the route requires it', function (): void {
    expect(app('router')->getMiddlewareGroups()['api'])->toContain(ResolveResponseSelection::class);

    $route = app('router')->get('/selection-order-test', fn () => null)->middleware(['api', 'auth.api']);
    $middleware = array_map(fn (string $name) => explode(':', $name)[0], app('router')->gatherRouteMiddleware($route));

    expect(array_search(Authenticate::class, $middleware, true))
        ->toBeLessThan(array_search(ResolveResponseSelection::class, $middleware, true));
});
