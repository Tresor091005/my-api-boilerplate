<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Fluent;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ApiResponse;
use Lahatre\ResponseSelection\Declaration\ResponseDeclaration;
use Lahatre\ResponseSelection\Enums\ResponseMode;
use Lahatre\ResponseSelection\Enums\ResponseShape;
use Lahatre\ResponseSelection\Exceptions\ResponseSelectionException;
use Lahatre\ResponseSelection\Rendering\ResponseContext;
use Lahatre\ResponseSelection\ResourceDefinition;
use Lahatre\ResponseSelection\Selection\SelectionTree;

/**
 * Unsaved; its `name` accessor records the lazy-loading flag it sees while rendering.
 */
final class ApiResponseTestModel extends Model
{
    /** @var array<int, bool> */
    public static array $lazyLoadingSeen = [];

    public $timestamps = false;

    protected $guarded = [];

    public function getNameAttribute(?string $value): ?string
    {
        self::$lazyLoadingSeen[] = $this->preventsLazyLoading;

        return $value;
    }
}

final class ApiResponseTestResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name');
    }
}

/**
 * Renders in `value` whatever the test puts in $value.
 */
final class ApiResponseTestValueResource extends ApiResource
{
    public static mixed $value = null;

    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('value', columns: [], computedBy: fn (): mixed => self::$value);
    }
}

final class ApiResponseTestOtherResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource->field('id');
    }
}

/**
 * Starts the context as ResolveResponseSelection does for an action with this declaration.
 */
function startApiResponseTest(ResponseDeclaration $declaration, ResponseMode $mode = ResponseMode::Resource): void
{
    app(ResponseContext::class)->start($mode, SelectionTree::rootFields(), $declaration);
}

beforeEach(function (): void {
    ApiResponseTestModel::$lazyLoadingSeen = [];
    app()->instance(ResponseContext::class, new ResponseContext);
    startApiResponseTest(new ResponseDeclaration(ApiResponseTestResource::class, ResponseShape::Item, [200]));
});

it('wraps an item in data', function (): void {
    $response = ApiResponse::item(new ApiResponseTestModel(['id' => 1, 'name' => 'Alpha']), ApiResponseTestResource::class);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response instanceof JsonResponse ? $response->getData(true) : null)->toBe([
            'data' => ['id' => 1, 'name' => 'Alpha'],
        ]);
});

it('wraps a list in data', function (): void {
    startApiResponseTest(new ResponseDeclaration(ApiResponseTestResource::class, ResponseShape::List, [200]));
    $response = ApiResponse::list([
        new ApiResponseTestModel(['id' => 1, 'name' => 'Alpha']),
        new ApiResponseTestModel(['id' => 2, 'name' => 'Beta']),
    ], ApiResponseTestResource::class);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response instanceof JsonResponse ? $response->getData(true) : null)->toBe([
            'data' => [
                ['id' => 1, 'name' => 'Alpha'],
                ['id' => 2, 'name' => 'Beta'],
            ],
        ]);
});

it('rejects an item that is not a model', function (mixed $item): void {
    expect(fn (): JsonResponse|Response => ApiResponse::item($item, ApiResponseTestResource::class))
        ->toThrow(ResponseSelectionException::class, ApiResponseTestResource::class);
})->with([
    'associative array' => [['id' => 1, 'name' => 'Alpha']],
    'fluent'            => [new Fluent(['id' => 1, 'name' => 'Alpha'])],
    'plain object'      => [(object) ['id' => 1]],
]);

it('rejects a list containing an item that is not a model', function (): void {
    startApiResponseTest(new ResponseDeclaration(ApiResponseTestResource::class, ResponseShape::List, [200]));
    // @phpstan-ignore argument.type (the wrong item is the point of the test)
    ApiResponse::list([new ApiResponseTestModel(['id' => 1]), ['id' => 2]], ApiResponseTestResource::class);
})->throws(ResponseSelectionException::class, 'array');

it('refuses to render a resource outside ApiResponse', function (): void {
    new ApiResponseTestResource(new ApiResponseTestModel(['id' => 1, 'name' => 'Alpha']))->resolve();
})->throws(ResponseSelectionException::class);

it('forbids lazy loading on the rendered model while rendering and restores its flag', function (): void {
    $model = new ApiResponseTestModel(['id' => 1, 'name' => 'Alpha']);

    ApiResponse::item($model, ApiResponseTestResource::class);

    expect(ApiResponseTestModel::$lazyLoadingSeen)->toBe([true])
        ->and($model->preventsLazyLoading)->toBeFalse();
});

it('rejects a factory call that does not match the declaration and names the expected one', function (ResponseDeclaration $declaration, string $expected): void {
    startApiResponseTest($declaration);

    expect(fn (): JsonResponse|Response => ApiResponse::item(new ApiResponseTestModel(['id' => 1]), ApiResponseTestResource::class))
        ->toThrow(ResponseSelectionException::class, "calls ApiResponse::item(ApiResponseTestResource::class), but its response declaration is {$expected}");
})->with([
    'other Resource'  => [new ResponseDeclaration(ApiResponseTestOtherResource::class, ResponseShape::Item, [200]), 'item(ApiResponseTestOtherResource::class)'],
    'other shape'     => [new ResponseDeclaration(ApiResponseTestResource::class, ResponseShape::List, [200]), 'list(ApiResponseTestResource::class)'],
    'no content only' => [ResponseDeclaration::noContentOnly(), 'noContent()'],
]);

it('rejects a call without a started context, as for an action missing from a stale cache', function (Closure $call): void {
    app(ResponseContext::class)->reset();

    expect($call)->toThrow(ResponseSelectionException::class, 'unknown action calls ApiResponse without a response declaration');
})->with([
    'item'      => [fn (): JsonResponse|Response => ApiResponse::item(new ApiResponseTestModel(['id' => 1]), ApiResponseTestResource::class)],
    'noContent' => [ApiResponse::noContent(...)],
]);

it('allows noContent() only when the declaration may answer empty', function (): void {
    startApiResponseTest(new ResponseDeclaration(ApiResponseTestResource::class, ResponseShape::Item, [200], canBeEmpty: true));
    expect(ApiResponse::noContent()->getStatusCode())->toBe(204);

    startApiResponseTest(ResponseDeclaration::noContentOnly(), ResponseMode::None);
    expect(ApiResponse::noContent()->getStatusCode())->toBe(204);

    startApiResponseTest(new ResponseDeclaration(ApiResponseTestResource::class, ResponseShape::Item, [200]));
    expect(fn (): Response => ApiResponse::noContent())
        ->toThrow(ResponseSelectionException::class, 'calls ApiResponse::noContent(), but its response declaration is item(ApiResponseTestResource::class)');
});

it('answers 204 in mode none through the declared factory', function (): void {
    startApiResponseTest(new ResponseDeclaration(ApiResponseTestResource::class, ResponseShape::Item, [200]), ResponseMode::None);

    expect(ApiResponse::item(new ApiResponseTestModel(['id' => 1]), ApiResponseTestResource::class)->getStatusCode())->toBe(204);
});

it('renders null and scalar field values as they are', function (mixed $value): void {
    startApiResponseTest(new ResponseDeclaration(ApiResponseTestValueResource::class, ResponseShape::Item, [200]));
    ApiResponseTestValueResource::$value = $value;

    $response = ApiResponse::item(new ApiResponseTestModel(['id' => 1]), ApiResponseTestValueResource::class);

    expect($response instanceof JsonResponse ? $response->getData(true)['data']['value'] : 'no response')->toBe($value);
})->with([null, 'text', 42, 12.5, true, false]);

it('rejects a field value that is a model, even nested in an array', function (mixed $value): void {
    startApiResponseTest(new ResponseDeclaration(ApiResponseTestValueResource::class, ResponseShape::Item, [200]));
    ApiResponseTestValueResource::$value = $value;

    ApiResponse::item(new ApiResponseTestModel(['id' => 1]), ApiResponseTestValueResource::class);
})->with([
    'model'          => [fn (): ApiResponseTestModel => new ApiResponseTestModel(['id' => 2])],
    'nested in list' => [fn (): array => ['a', [new ApiResponseTestModel(['id' => 2])]]],
])->throws(ResponseSelectionException::class);
