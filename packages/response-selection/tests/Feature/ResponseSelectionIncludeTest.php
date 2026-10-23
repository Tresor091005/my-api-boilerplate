<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Feature;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ApiResponse;
use Lahatre\ResponseSelection\BuiltValues;
use Lahatre\ResponseSelection\Enums\ResponseMode;
use Lahatre\ResponseSelection\Enums\ResponseShape;
use Lahatre\ResponseSelection\FieldComputer;
use Lahatre\ResponseSelection\ResourceDefinition;
use Lahatre\ResponseSelection\ResponseContext;
use Lahatre\ResponseSelection\ResponseDeclaration;
use Lahatre\ResponseSelection\SelectionParser;
use Lahatre\ResponseSelection\VirtualIncludeLoader;

uses(RefreshDatabase::class);

/**
 * @property int $id
 */
final class ResponseSelectionTestLocation extends Model
{
    public $timestamps = false;

    protected $table = 'response_selection_test_locations';

    protected $guarded = [];
}

/**
 * @property int $id
 */
final class ResponseSelectionTestTransfer extends Model
{
    public $timestamps = false;

    protected $table = 'response_selection_test_transfers';

    protected $guarded = [];

    /**
     * @return BelongsTo<ResponseSelectionTestLocation, $this>
     */
    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(ResponseSelectionTestLocation::class, 'source_location_id');
    }
}

final class ResponseSelectionTestLocationResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id', columns: ['id'])
            ->field('name', columns: ['name']);
    }
}

/**
 * Labels every transfer after its source location, counting its runs; never computes `missing`.
 */
final class ResponseSelectionTestTransferLabels implements FieldComputer
{
    public static int $runs = 0;

    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        self::$runs++;
        $values = [];

        foreach ($models as $transfer) {
            $label = 'from-'.$transfer->getAttribute('source_location_id');
            $computed = ['label' => $label, 'label_length' => strlen($label)];
            $values[$transfer->getKey()] = array_intersect_key($computed, array_flip($fields));
        }

        return $values;
    }
}

/**
 * Returns, for the first transfer only, every location: the other transfers have none.
 */
final class ResponseSelectionTestOtherLocations implements VirtualIncludeLoader
{
    public function load(Collection $parents, BuiltValues $built): array
    {
        return [$parents->first()->getKey() => ResponseSelectionTestLocation::query()->orderBy('id')->get()];
    }
}

final class ResponseSelectionTestTransferResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id', columns: ['id'])
            ->field('label', columns: ['source_location_id'], computedBy: ResponseSelectionTestTransferLabels::class)
            ->field('label_length', columns: ['source_location_id'], computedBy: ResponseSelectionTestTransferLabels::class)
            ->field('missing', computedBy: ResponseSelectionTestTransferLabels::class)
            ->field('source_name', columns: ['source_location_id'], relations: ['sourceLocation'], computedBy: fn (Model $transfer): string => $transfer->getRelation('sourceLocation')->getAttribute('name'))
            ->include('source_location', ResponseSelectionTestLocationResource::class)
            ->virtualInclude('other_locations', ResponseSelectionTestLocationResource::class, ResponseSelectionTestOtherLocations::class, asList: true);
    }
}

beforeEach(function (): void {
    ResponseSelectionTestTransferLabels::$runs = 0;

    $context = new ResponseContext;
    app()->instance(ResponseContext::class, $context);

    Schema::create('response_selection_test_locations', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });

    Schema::create('response_selection_test_transfers', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('source_location_id');
    });
});

it('selects an include by its snake_case JSON key and loads its camelCase relation', function (): void {
    $location = ResponseSelectionTestLocation::query()->create(['name' => 'Main depot']);
    $transfer = ResponseSelectionTestTransfer::query()->create(['source_location_id' => $location->id]);

    app(ResponseContext::class)->start(ResponseMode::Resource, (new SelectionParser)->parse('id', 'source_location(name)'), new ResponseDeclaration(ResponseSelectionTestTransferResource::class, ResponseShape::Item, [200]));

    $response = ApiResponse::item(ResponseSelectionTestTransfer::query()->findOrFail($transfer->id), ResponseSelectionTestTransferResource::class);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response instanceof JsonResponse ? $response->getData(true) : null)->toBe([
            'data' => [
                'id'              => $transfer->id,
                'source_location' => ['id' => $location->id, 'name' => 'Main depot'],
            ],
        ]);
});

it('renders what a virtual loader returns and an empty list for parents it left out', function (): void {
    $location = ResponseSelectionTestLocation::query()->create(['name' => 'Main depot']);
    $first = ResponseSelectionTestTransfer::query()->create(['source_location_id' => $location->id]);
    $second = ResponseSelectionTestTransfer::query()->create(['source_location_id' => $location->id]);

    app(ResponseContext::class)->start(ResponseMode::Resource, (new SelectionParser)->parse('id', 'other_locations(name)'), new ResponseDeclaration(ResponseSelectionTestTransferResource::class, ResponseShape::List, [200]));

    $response = ApiResponse::list(ResponseSelectionTestTransfer::query()->orderBy('id')->get(), ResponseSelectionTestTransferResource::class);

    expect($response instanceof JsonResponse ? $response->getData(true) : null)->toBe([
        'data' => [
            ['id' => $first->id, 'other_locations' => [['id' => $location->id, 'name' => 'Main depot']]],
            ['id' => $second->id, 'other_locations' => []],
        ],
    ]);
});

it('computes fields once per node and omits a field its computer leaves out', function (): void {
    $location = ResponseSelectionTestLocation::query()->create(['name' => 'Main depot']);
    $first = ResponseSelectionTestTransfer::query()->create(['source_location_id' => $location->id]);
    $second = ResponseSelectionTestTransfer::query()->create(['source_location_id' => $location->id]);

    app(ResponseContext::class)->start(ResponseMode::Resource, (new SelectionParser)->parse('label,label_length,missing', null), new ResponseDeclaration(ResponseSelectionTestTransferResource::class, ResponseShape::List, [200]));

    $response = ApiResponse::list(ResponseSelectionTestTransfer::query()->orderBy('id')->get(), ResponseSelectionTestTransferResource::class);
    $label = 'from-'.$location->id;

    expect(ResponseSelectionTestTransferLabels::$runs)->toBe(1)
        ->and($response instanceof JsonResponse ? $response->getData(true)['data'] : null)->toBe([
            ['id' => $first->id, 'label' => $label, 'label_length' => strlen($label)],
            ['id' => $second->id, 'label' => $label, 'label_length' => strlen($label)],
        ]);
});

it('trusts a relation already loaded with fewer columns and fails loudly on a missing one', function (): void {
    $location = ResponseSelectionTestLocation::query()->create(['name' => 'Main depot']);
    $transfer = ResponseSelectionTestTransfer::query()->create(['source_location_id' => $location->id]);

    app(ResponseContext::class)->start(ResponseMode::Resource, (new SelectionParser)->parse('source_name', null), new ResponseDeclaration(ResponseSelectionTestTransferResource::class, ResponseShape::Item, [200]));

    $narrowed = ResponseSelectionTestTransfer::query()->with('sourceLocation:id')->findOrFail($transfer->id);

    ApiResponse::item($narrowed, ResponseSelectionTestTransferResource::class);
})->throws(MissingAttributeException::class);
