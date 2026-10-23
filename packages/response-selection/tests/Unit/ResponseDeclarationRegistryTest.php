<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Unit;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Validation\ValidationException;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ApiResponse;
use Lahatre\ResponseSelection\Enums\ResponseMode;
use Lahatre\ResponseSelection\Enums\ResponseShape;
use Lahatre\ResponseSelection\Exceptions\ResponseDeclarationException;
use Lahatre\ResponseSelection\ResolveResponseSelection;
use Lahatre\ResponseSelection\ResourceDefinition;
use Lahatre\ResponseSelection\ResourceResolver;
use Lahatre\ResponseSelection\ResponseContext;
use Lahatre\ResponseSelection\ResponseDeclaration;
use Lahatre\ResponseSelection\ResponseDeclarationAnalyzer;
use Lahatre\ResponseSelection\ResponseDeclarationRegistry;
use Lahatre\ResponseSelection\SelectionParser;
use Lahatre\ResponseSelection\SelectionValidator;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class DeclarationTestLeafResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource->field('id')->field('leaf_name');
    }
}

final class DeclarationTestResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->include('owner', DeclarationTestResource::class)
            ->virtualInclude('leaf', DeclarationTestLeafResource::class, fn (Model $parent): ?Model => null);
    }
}

final class DeclarationTestVariantResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource->field('id')->field('sku')->include('options', DeclarationTestLeafResource::class);
    }
}

final class DeclarationTestBundleResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('components_count')
            ->include('components', DeclarationTestResource::class)
            ->include('options', DeclarationTestResource::class);
    }
}

final class DeclarationTestSubjectResolver implements ResourceResolver
{
    public function resourceFor(Model $model): ?string
    {
        return DeclarationTestVariantResource::class;
    }

    public function resources(): array
    {
        return [DeclarationTestVariantResource::class, DeclarationTestBundleResource::class];
    }
}

final class DeclarationTestStockResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource->field('id')->field('quantity')->include('subject', DeclarationTestSubjectResolver::class);
    }
}

final class DeclarationTestRun
{
    public static bool $controllerRan = false;
}

trait DeclarationTestTraitActions
{
    public function fromTrait(): JsonResponse|Response
    {
        return ApiResponse::list([], DeclarationTestResource::class);
    }
}

class DeclarationTestParentController
{
    public function inherited(CursorPaginator $page): JsonResponse|Response
    {
        return ApiResponse::cursorPage($page, DeclarationTestResource::class);
    }
}

final class DeclarationTestController extends DeclarationTestParentController
{
    use DeclarationTestTraitActions;

    public function show(Model $model): JsonResponse|Response
    {
        return ApiResponse::item($model, DeclarationTestResource::class);
    }

    public function store(Model $model): JsonResponse|Response
    {
        return ApiResponse::item($model, DeclarationTestResource::class, status: 201);
    }

    public function update(?Model $model): JsonResponse|Response
    {
        if ($model === null) {
            return ApiResponse::noContent();
        }

        return ApiResponse::item($model, DeclarationTestResource::class);
    }

    public function destroy(): Response
    {
        return ApiResponse::noContent();
    }

    public function stock(Model $model): JsonResponse|Response
    {
        return ApiResponse::item($model, DeclarationTestStockResource::class);
    }

    public function legacy(): JsonResponse
    {
        return response()->json(['data' => []]);
    }
}

final class DeclarationTestInvalidController
{
    public function twice(Model $model, bool $many): JsonResponse|Response
    {
        if ($many) {
            return ApiResponse::list([$model], DeclarationTestResource::class);
        }

        return ApiResponse::item($model, DeclarationTestResource::class);
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function dynamic(Model $model, string $resourceClass): JsonResponse|Response
    {
        return ApiResponse::item($model, $resourceClass);
    }
}

/**
 * A router holding only the fixture routes of the valid controller, unless others are given.
 *
 * @param  array<string, array{0: string, 1: array{0: class-string, 1: string}}>  $routes  uri => [method, action]
 */
function declarationTestRouter(array $routes = []): Router
{
    $router = new Router(app('events'), app());
    $routes = $routes !== [] ? $routes : [
        'show'       => ['GET', [DeclarationTestController::class, 'show']],
        'store'      => ['POST', [DeclarationTestController::class, 'store']],
        'update'     => ['PATCH', [DeclarationTestController::class, 'update']],
        'destroy'    => ['DELETE', [DeclarationTestController::class, 'destroy']],
        'archive'    => ['POST', [DeclarationTestController::class, 'destroy']],
        'stock'      => ['GET', [DeclarationTestController::class, 'stock']],
        'legacy'     => ['GET', [DeclarationTestController::class, 'legacy']],
        'inherited'  => ['GET', [DeclarationTestController::class, 'inherited']],
        'from-trait' => ['GET', [DeclarationTestController::class, 'fromTrait']],
    ];

    foreach ($routes as $uri => [$method, $action]) {
        $router->addRoute($method, $uri, $action);
    }
    $router->get('closure', fn () => null);

    return $router;
}

function declarationTestRegistry(?Router $router = null, ?string $cachePath = null): ResponseDeclarationRegistry
{
    return new ResponseDeclarationRegistry($router ?? declarationTestRouter(), new ResponseDeclarationAnalyzer, $cachePath ?? sys_get_temp_dir().'/missing-response-declarations.php');
}

function declarationTestRoute(Router $router, string $uri): Route
{
    foreach ($router->getRoutes()->getRoutes() as $route) {
        if ($route->uri() === $uri) {
            return $route;
        }
    }

    throw new \LogicException("No fixture route {$uri}.");
}

/**
 * Runs ResolveResponseSelection on a fixture route; the controller records that it ran and what
 * the context held.
 *
 * @return array{ran: bool, response: SymfonyResponse|null, context: array<string, mixed>|null}
 */
function runSelection(string $uri, string $method, string $query = ''): array
{
    $router = declarationTestRouter();
    $route = declarationTestRoute($router, $uri);
    $request = Request::create("/{$uri}?{$query}", $method);
    $request->setRouteResolver(fn () => $route);
    $context = app(ResponseContext::class);
    $middleware = new ResolveResponseSelection($context, declarationTestRegistry($router), new SelectionParser, new SelectionValidator);
    $result = ['ran' => false, 'response' => null, 'context' => null];

    $result['response'] = $middleware->handle($request, function () use ($context, &$result): SymfonyResponse {
        $result['ran'] = DeclarationTestRun::$controllerRan = true;
        $result['context'] = [
            'active'      => $context->isActive(),
            'mode'        => $context->mode(),
            'selection'   => $context->selection()->toArray(),
            'declaration' => $context->declaration(),
        ];

        return new Response('controller');
    });

    return $result;
}

/**
 * Runs the middleware and returns the validation errors it answered with; the controller must not run.
 *
 * @return array<string, array<int, string>>
 */
function selectionErrors(string $uri, string $method, string $query): array
{
    try {
        runSelection($uri, $method, $query);
    } catch (ValidationException $exception) {
        expect(DeclarationTestRun::$controllerRan)->toBeFalse();

        return $exception->errors();
    }

    throw new \LogicException('The middleware answered no validation error.');
}

beforeEach(function (): void {
    DeclarationTestRun::$controllerRan = false;
    app()->instance(ResponseContext::class, new ResponseContext);
});

it('reads the single factory call of an action, its Resource, shape and status', function (): void {
    $analyzer = new ResponseDeclarationAnalyzer;

    expect($analyzer->forAction(DeclarationTestController::class, 'show')?->toArray())->toBe([
        'resource' => DeclarationTestResource::class, 'shape' => 'item', 'statuses' => [200], 'can_be_empty' => false,
    ])->and($analyzer->forAction(DeclarationTestController::class, 'store')?->statuses)->toBe([201])
        ->and($analyzer->forAction(DeclarationTestController::class, 'update')?->canBeEmpty)->toBeTrue();
});

it('declares an action whose only ApiResponse call is noContent() as no content only', function (): void {
    $declaration = (new ResponseDeclarationAnalyzer)->forAction(DeclarationTestController::class, 'destroy');

    expect($declaration?->isNoContentOnly())->toBeTrue()
        ->and($declaration?->resourceClass)->toBeNull()
        ->and($declaration?->shape)->toBeNull()
        ->and($declaration?->canBeEmpty)->toBeTrue();
});

it('ignores a legacy action making no ApiResponse call', function (): void {
    expect((new ResponseDeclarationAnalyzer)->forAction(DeclarationTestController::class, 'legacy'))->toBeNull()
        ->and(declarationTestRegistry()->for(declarationTestRoute(declarationTestRouter(), 'legacy')))->toBeNull()
        ->and(declarationTestRegistry()->for(declarationTestRoute(declarationTestRouter(), 'closure')))->toBeNull();
});

it('reads an inherited action and a trait action in the file they are written in', function (): void {
    $registry = declarationTestRegistry();
    $router = declarationTestRouter();

    expect($registry->for(declarationTestRoute($router, 'inherited'))?->shape)->toBe(ResponseShape::CursorPage)
        ->and($registry->for(declarationTestRoute($router, 'from-trait'))?->shape)->toBe(ResponseShape::List);
});

it('rejects at build an action making two factory calls', function (): void {
    declarationTestRegistry(declarationTestRouter(['twice' => ['GET', [DeclarationTestInvalidController::class, 'twice']]]))->build();
})->throws(ResponseDeclarationException::class, DeclarationTestInvalidController::class.'@twice: expected one explicit ApiResponse::item()');

it('rejects at build an action whose Resource is not a literal class', function (): void {
    declarationTestRegistry(declarationTestRouter(['dynamic' => ['GET', [DeclarationTestInvalidController::class, 'dynamic']]]))->build();
})->throws(ResponseDeclarationException::class, DeclarationTestInvalidController::class.'@dynamic: use an explicit Resource::class');

it('builds every routed action once, legacy ones as null', function (): void {
    $built = declarationTestRegistry()->build();

    expect(array_keys($built))->toBe([
        DeclarationTestController::class.'@destroy',
        DeclarationTestController::class.'@fromTrait',
        DeclarationTestController::class.'@inherited',
        DeclarationTestController::class.'@legacy',
        DeclarationTestController::class.'@show',
        DeclarationTestController::class.'@stock',
        DeclarationTestController::class.'@store',
        DeclarationTestController::class.'@update',
    ])->and($built[DeclarationTestController::class.'@legacy'])->toBeNull();
});

it('rebuilds a declaration from its array form', function (ResponseDeclaration $declaration): void {
    expect(ResponseDeclaration::fromArray($declaration->toArray()))->toEqual($declaration);
})->with([
    'item'            => [new ResponseDeclaration(DeclarationTestResource::class, ResponseShape::Item, [200, 201], canBeEmpty: true)],
    'cursor page'     => [new ResponseDeclaration(DeclarationTestResource::class, ResponseShape::CursorPage, [200])],
    'no content only' => [ResponseDeclaration::noContentOnly()],
]);

it('reads the cache file in production only, and analyzes an action it does not list', function (): void {
    $path = sys_get_temp_dir().'/response-declarations-'.uniqid().'.php';
    $router = declarationTestRouter();
    $route = declarationTestRoute($router, 'show');

    try {
        declarationTestRegistry($router, $path)->cache();
        expect(require $path)->toBe(declarationTestRegistry($router)->build());

        // a stale cache declaring show() as a list and update() as legacy, built before store()
        file_put_contents($path, '<?php return '.var_export([
            DeclarationTestController::class.'@show'   => (new ResponseDeclaration(DeclarationTestResource::class, ResponseShape::List, [200]))->toArray(),
            DeclarationTestController::class.'@update' => null,
        ], true).';');

        expect(declarationTestRegistry($router, $path)->for($route)?->shape)->toBe(ResponseShape::Item);

        config(['app.env' => 'production']);
        $registry = declarationTestRegistry($router, $path);

        expect($registry->for($route)?->shape)->toBe(ResponseShape::List)
            ->and($registry->for(declarationTestRoute($router, 'update')))->toBeNull()
            ->and($registry->for(declarationTestRoute($router, 'store'))?->statuses)->toBe([201]);

        $registry->clear();
        expect(file_exists($path))->toBeFalse();
    } finally {
        @unlink($path);
    }
});

it('passes a legacy route through untouched, without starting the context', function (): void {
    $result = runSelection('legacy', 'GET', 'fields=nope&include=nope&response=none');

    expect($result['ran'])->toBeTrue()
        ->and($result['context']['active'] ?? null)->toBeFalse()
        ->and($result['response']?->getContent())->toBe('controller');
});

it('starts the context with the mode, the selection and the declaration, then resets it', function (): void {
    $result = runSelection('store', 'POST', 'response=resource&fields=name&include=owner(name),leaf');

    expect($result['context']['active'] ?? null)->toBeTrue()
        ->and($result['context']['mode'] ?? null)->toBe(ResponseMode::Resource)
        ->and($result['context']['selection'] ?? null)->toBe([
            'fields'  => ['name'],
            'include' => ['owner' => ['fields' => ['name'], 'include' => []], 'leaf' => ['fields' => null, 'include' => []]],
        ])
        ->and($result['context']['declaration']?->statuses)->toBe([201])
        ->and(app(ResponseContext::class)->isActive())->toBeFalse();
});

it('answers 422 for an unknown name before the controller runs, for every method', function (string $uri, string $method, string $query, array $errors): void {
    expect(selectionErrors($uri, $method, $query))->toBe($errors);
})->with([
    'root field'          => ['show', 'GET', 'fields=id,nope', ['fields' => ['The field "nope" does not exist.']]],
    'include in fields'   => ['show', 'GET', 'fields=owner', ['fields' => ['The field "owner" does not exist.']]],
    'include'             => ['show', 'GET', 'include=nope', ['include' => ['The include "nope" does not exist.']]],
    'nested include'      => ['show', 'GET', 'include=owner.owner.nope', ['include' => ['The include "owner.owner.nope" does not exist.']]],
    'field of an include' => ['show', 'GET', 'include=leaf(leaf_name,nope)', ['include' => ['The field "leaf.nope" does not exist.']]],
    'write'               => ['store', 'POST', 'response=resource&fields=nope', ['fields' => ['The field "nope" does not exist.']]],
    'write with body'     => ['update', 'PATCH', 'response=resource&include=nope', ['include' => ['The include "nope" does not exist.']]],
]);

it('accepts id, and a name under a polymorphic include that one possible Resource declares', function (string $include): void {
    expect(runSelection('stock', 'GET', "fields=id,quantity&include={$include}")['ran'])->toBeTrue();
})->with([
    'variant field'            => ['subject(id,sku)'],
    'bundle field'             => ['subject(components_count)'],
    'fields of both'           => ['subject(sku,components_count)'],
    'include of one'           => ['subject.components(name),subject.components.owner'],
    'union child, variant key' => ['subject.options(leaf_name)'],
    'union child, bundle key'  => ['subject.options(name),subject.options.owner'],
]);

it('rejects a name under a polymorphic include that no possible Resource declares', function (string $include, array $errors): void {
    expect(selectionErrors('stock', 'GET', "include={$include}"))->toBe($errors);
})->with([
    'field'            => ['subject(nope)', ['include' => ['The field "subject.nope" does not exist.']]],
    'include'          => ['subject.nope', ['include' => ['The include "subject.nope" does not exist.']]],
    'child of a union' => ['subject.options(nope)', ['include' => ['The field "subject.options.nope" does not exist.']]],
    'include of one'   => ['subject.options.owner.nope', ['include' => ['The include "subject.options.owner.nope" does not exist.']]],
]);

it('rejects ?response=resource on a no-content-only route before the controller runs', function (): void {
    expect(selectionErrors('archive', 'POST', 'response=resource'))->toBe([
        'response' => ['The response mode "resource" is not allowed on this endpoint.'],
    ]);

    $result = runSelection('archive', 'POST');

    expect($result['context']['mode'] ?? null)->toBe(ResponseMode::None)
        ->and($result['context']['declaration']?->isNoContentOnly())->toBeTrue();
});
