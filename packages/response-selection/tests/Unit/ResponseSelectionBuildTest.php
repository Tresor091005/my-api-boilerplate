<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Unit;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ApiResponse;
use Lahatre\ResponseSelection\BuiltValues;
use Lahatre\ResponseSelection\Declaration\ResponseDeclaration;
use Lahatre\ResponseSelection\Enums\ResponseMode;
use Lahatre\ResponseSelection\Enums\ResponseShape;
use Lahatre\ResponseSelection\Exceptions\ResponseDeclarationException;
use Lahatre\ResponseSelection\Exceptions\ResponseSelectionException;
use Lahatre\ResponseSelection\FieldComputer;
use Lahatre\ResponseSelection\Rendering\ResponseContext;
use Lahatre\ResponseSelection\Rendering\SelectionLoader;
use Lahatre\ResponseSelection\ResolveResponseSelection;
use Lahatre\ResponseSelection\ResourceDefinition;
use Lahatre\ResponseSelection\ResourceResolver;
use Lahatre\ResponseSelection\Selection\SelectionNode;
use Lahatre\ResponseSelection\Selection\SelectionParser;
use Lahatre\ResponseSelection\VirtualIncludeLoader;
use RuntimeException;

/**
 * Unsaved: no column completion, no query.
 *
 * @property int $id
 */
final class ResponseBuildTestAccount extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** @return BelongsTo<ResponseBuildTestAccount, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(self::class, 'owner_id');
    }

    /** @return HasMany<ResponseBuildTestAccount, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(self::class, 'owner_id');
    }
}

/**
 * Another class sharing the keys of ResponseBuildTestAccount.
 */
final class ResponseBuildTestOtherAccount extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}

final class ResponseBuildTestFormRequest extends FormRequest {}

/**
 * Records every compute() call (the computer, the model keys and the fields it was asked for), and
 * what other builders saw.
 */
final class ResponseBuildTestCalls
{
    /** @var array<int, array{computer: string, models: array<int, mixed>, fields: array<int, string>}> */
    public static array $calls = [];

    /** @var array<int, string> */
    public static array $seen = [];

    public static function see(string $entry): void
    {
        self::$seen[] = $entry;
    }

    /**
     * @param  array<int, string>  $fields
     */
    public static function record(string $computer, Collection $models, array $fields): void
    {
        self::$calls[] = ['computer' => $computer, 'models' => $models->modelKeys(), 'fields' => $fields];
    }
}

final class ResponseBuildTestCurrencies implements FieldComputer
{
    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        ResponseBuildTestCalls::record(self::class, $models, $fields);

        return $models->mapWithKeys(fn (Model $model) => [$model->getKey() => ['currency_code' => 'XOF']])->all();
    }
}

final class ResponseBuildTestBalances implements FieldComputer
{
    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        ResponseBuildTestCalls::record(self::class, $models, $fields);
        $values = [];

        foreach ($models as $model) {
            foreach ($fields as $field) {
                $values[$model->getKey()][$field] = match ($field) {
                    'balance' => $model->getAttribute('amount').' '.$built->get($model, 'currency_code'),
                    default   => $field,
                };
            }
        }

        return $values;
    }
}

final class ResponseBuildTestAccountResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('balance', columns: ['amount'], fields: ['currency_code'], computedBy: ResponseBuildTestBalances::class)
            ->field('currency_code', computedBy: ResponseBuildTestCurrencies::class)
            ->field('limit', computedBy: ResponseBuildTestBalances::class)
            ->field('ceiling', computedBy: ResponseBuildTestBalances::class)
            ->field('label', fields: ['limit'], computedBy: fn (Model $model, BuiltValues $built): string => 'limit '.$built->get($model, 'limit'))
            ->field('owner', columns: ['owner_id'], computedBy: ResponseBuildTestBalances::class, when: fn (Model $model, Request $request): bool => $model->getAttribute('owner_id') === 7)
            ->field('initials', columns: ['name'], computedBy: fn (Model $model): string => strtoupper(substr((string) $model->getAttribute('name'), 0, 2)));
    }
}

/**
 * Returns `rate` for model 1 only: it does not exist for the others.
 */
final class ResponseBuildTestPartialRates implements FieldComputer
{
    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        return [1 => ['rate' => 2]];
    }
}

final class ResponseBuildTestConversions implements FieldComputer
{
    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        ResponseBuildTestCalls::record(self::class, $models, $fields);

        return $models->mapWithKeys(fn (Model $model) => [$model->getKey() => ['converted' => $built->get($model, 'rate') * 10]])->all();
    }
}

final class ResponseBuildTestKinds implements FieldComputer
{
    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        ResponseBuildTestCalls::record(self::class, $models, $fields);

        return $models->mapWithKeys(fn (Model $model) => [$model->getKey() => ['kind_name' => class_basename($model)]])->all();
    }
}

final class ResponseBuildTestAbsenceResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('owner', columns: ['owner_id'], computedBy: ResponseBuildTestBalances::class, when: fn (Model $model, Request $request): bool => $model->getAttribute('owner_id') === 7)
            ->field('owner_label', fields: ['owner'], computedBy: function (Model $model, BuiltValues $built): string {
                ResponseBuildTestCalls::see('owner_label:'.$model->getKey());

                return 'of '.$built->get($model, 'owner');
            })
            ->field('rate', computedBy: ResponseBuildTestPartialRates::class)
            ->field('converted', fields: ['rate'], computedBy: ResponseBuildTestConversions::class)
            ->field('chain_c', columns: ['kind'], computedBy: fn (Model $model): string => 'c', when: fn (Model $model, Request $request): bool => $model->getAttribute('kind') === 'ok')
            ->field('chain_b', fields: ['chain_c'], computedBy: fn (Model $model, BuiltValues $built): string => $built->get($model, 'chain_c').'b')
            ->field('chain_a', fields: ['chain_b'], computedBy: fn (Model $model, BuiltValues $built): string => $built->get($model, 'chain_b').'a')
            ->virtualInclude('chained', ResponseBuildTestAbsenceResource::class, function (Model $parent): ?Model {
                ResponseBuildTestCalls::see('chained:'.$parent->getKey());

                return null;
            }, fields: ['chain_a'])
            ->field('kind_name', computedBy: ResponseBuildTestKinds::class);
    }
}

final class ResponseBuildTestVirtualResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->virtualInclude('peers', ResponseBuildTestVirtualResource::class, fn (Model $parent): array => [
                new ResponseBuildTestAccount(['id' => 10, 'name' => 'Peer']),
                new ResponseBuildTestAccount(['id' => 11, 'name' => 'Other']),
            ], asList: true)
            ->virtualInclude('partner', ResponseBuildTestVirtualResource::class, fn (Model $parent): Model => new ResponseBuildTestAccount(['id' => 12, 'name' => 'Partner']))
            ->virtualInclude('summary', ResponseBuildTestVirtualResource::class, fn (Model $parent): array => ['id' => 5, 'name' => 'Awa']);
    }
}

final class ResponseBuildTestNamedResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name');
    }
}

/**
 * A model named `hidden` has no API representation.
 */
final class ResponseBuildTestNamedResolver implements ResourceResolver
{
    public function resourceFor(Model $model): ?string
    {
        return $model->getAttribute('name') === 'hidden' ? null : ResponseBuildTestNamedResource::class;
    }

    public function resources(): array
    {
        return [ResponseBuildTestNamedResource::class];
    }
}

final class ResponseBuildTestResolvedResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->virtualInclude('members', ResponseBuildTestNamedResolver::class, fn (Model $parent): array => [
                new ResponseBuildTestAccount(['id' => 10, 'name' => 'Peer']),
                new ResponseBuildTestAccount(['id' => 11, 'name' => 'hidden']),
                new ResponseBuildTestAccount(['id' => 12, 'name' => 'Other']),
            ], asList: true)
            ->virtualInclude('lead', ResponseBuildTestNamedResolver::class, fn (Model $parent): Model => new ResponseBuildTestAccount(['id' => 13, 'name' => 'hidden']));
    }
}

/**
 * Two Resources behind one polymorphic include: `options` exists on the first only, `parts` on the second only.
 */
final class ResponseBuildTestVariantResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->field('options', computedBy: fn (Model $model): array => ['size' => 'L']);
    }
}

final class ResponseBuildTestBundleResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->virtualInclude('parts', ResponseBuildTestNamedResource::class, fn (Model $parent): array => [
                new ResponseBuildTestAccount(['id' => 30, 'name' => 'Part']),
            ], asList: true);
    }
}

final class ResponseBuildTestSubjectResolver implements ResourceResolver
{
    public function resourceFor(Model $model): ?string
    {
        return $model instanceof ResponseBuildTestOtherAccount ? ResponseBuildTestBundleResource::class : ResponseBuildTestVariantResource::class;
    }

    public function resources(): array
    {
        return [ResponseBuildTestVariantResource::class, ResponseBuildTestBundleResource::class];
    }
}

final class ResponseBuildTestSubjectsResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->virtualInclude('subjects', ResponseBuildTestSubjectResolver::class, fn (Model $parent): array => [
                new ResponseBuildTestAccount(['id' => 10, 'name' => 'Variant']),
                new ResponseBuildTestOtherAccount(['id' => 20, 'name' => 'Bundle']),
            ], asList: true);
    }
}

final class ResponseBuildTestRelationResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->include('owner', ResponseBuildTestNamedResource::class)
            ->include('members', ResponseBuildTestNamedResource::class);
    }
}

/**
 * Records each load() call, then gives every parent a partner named after the parent's class.
 */
final class ResponseBuildTestPartners implements VirtualIncludeLoader
{
    public function load(Collection $parents, BuiltValues $built): array
    {
        ResponseBuildTestCalls::see(class_basename($parents->first()).':'.implode(',', $parents->modelKeys()));

        return $parents->mapWithKeys(fn (Model $parent) => [$parent->getKey() => new ResponseBuildTestAccount([
            'id'   => 100 + $parent->getKey(),
            'name' => class_basename($parent).' partner',
        ])])->all();
    }
}

final class ResponseBuildTestPartnerResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->virtualInclude('partner', ResponseBuildTestNamedResource::class, ResponseBuildTestPartners::class);
    }
}

final class ResponseBuildTestUndeclaredRead implements FieldComputer
{
    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        return $models->mapWithKeys(fn (Model $model) => [$model->getKey() => [$fields[0] => $built->get($model, $fields[0] === 'reads_other' ? 'currency_code' : 'name')]])->all();
    }
}

final class ResponseBuildTestRestrictedResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->field('currency_code', computedBy: ResponseBuildTestCurrencies::class)
            ->field('reads_other', computedBy: ResponseBuildTestUndeclaredRead::class)
            ->field('reads_column', fields: ['name'], computedBy: ResponseBuildTestUndeclaredRead::class);
    }
}

class ResponseBuildTestBaseResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->virtualInclude('peers', ResponseBuildTestBaseResource::class, fn (Model $parent): array => [], asList: true)
            ->field('name');
    }
}

final class ResponseBuildTestChildResource extends ResponseBuildTestBaseResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource->field('code');
        parent::define($resource);
        $resource->field('amount');
    }
}

final class ResponseBuildTestGuardedResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->field('secret', columns: ['secret_column'], fields: ['only_secret', 'shared'], when: fn (Request $request): bool => false)
            ->field('only_secret', columns: ['only_secret_column'])
            ->field('shared', columns: ['shared_column'])
            ->field('public_total', columns: ['total_column'], fields: ['shared'])
            ->field('secret_total', columns: ['secret_total_column'], fields: ['secret']);
    }
}

final class ResponseBuildTestCycleResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('balance', fields: ['currency_code'])
            ->field('currency_code', fields: ['rate'])
            ->field('rate', fields: ['balance']);
    }
}

/**
 * Exposes the columns the loader computes for a node.
 */
final class ResponseBuildTestLoader extends SelectionLoader
{
    /**
     * @param  class-string<ApiResource>  $resourceClass
     * @return array<int, string>
     */
    public function columns(Model $model, string $resourceClass, SelectionNode $selection): array
    {
        return $this->nodeColumns($model, $resourceClass, $selection);
    }
}

beforeEach(function (): void {
    ResponseBuildTestCalls::$calls = [];
    ResponseBuildTestCalls::$seen = [];
    app()->instance(ResponseContext::class, new ResponseContext);
});

/**
 * Starts the response context as ResolveResponseSelection does for an action declaring
 * ApiResponse::<shape>($resourceClass).
 *
 * @param  class-string<ApiResource>  $resourceClass
 */
function startSelection(string $resourceClass, ResponseShape $shape, ?string $fields, ?string $include = null): void
{
    app(ResponseContext::class)->start(ResponseMode::Resource, (new SelectionParser)->parse($fields, $include), new ResponseDeclaration($resourceClass, $shape, [200]));
}

/**
 * Renders unsaved accounts through ApiResponse::list() with a `fields` selection.
 *
 * @param  class-string<ApiResource>  $resourceClass
 * @param  array<int, array<string, mixed>>  $rows
 * @return array<int, array<string, mixed>>
 */
function renderAccounts(string $resourceClass, ?string $fields, array $rows, ?string $include = null): array
{
    startSelection($resourceClass, ResponseShape::List, $fields, $include);
    $models = new Collection(array_map(fn (array $row) => new ResponseBuildTestAccount($row), $rows));
    $response = ApiResponse::list($models, $resourceClass);

    return $response instanceof JsonResponse ? $response->getData(true)['data'] : [];
}

it('rejects a fields: cycle at freeze and names it', function (): void {
    expect(fn () => ResponseBuildTestCycleResource::definition())
        ->toThrow(ResponseSelectionException::class, ResponseBuildTestCycleResource::class.': fields need each other in a cycle through `fields:`: balance → currency_code → rate → balance');
});

it('rejects an unknown key in fields: at freeze and names the resource', function (): void {
    (new ResourceDefinition)
        ->field('balance', fields: ['currency'])
        ->freeze(ResponseBuildTestAccountResource::class);
})->throws(ResponseSelectionException::class, ResponseBuildTestAccountResource::class.': [balance] needs [currency]');

it('rejects a field needing itself at freeze', function (): void {
    (new ResourceDefinition)
        ->field('balance', fields: ['balance'])
        ->freeze(ResponseBuildTestAccountResource::class);
})->throws(ResponseSelectionException::class);

it('rejects an include name in fields:', function (): void {
    (new ResourceDefinition)
        ->include('owner', ResponseBuildTestAccountResource::class)
        ->field('owner_name', fields: ['owner'])
        ->freeze(ResponseBuildTestAccountResource::class);
})->throws(ResponseSelectionException::class);

it('builds the fields a field needs first, across computer classes, without rendering them', function (): void {
    $data = renderAccounts(ResponseBuildTestAccountResource::class, 'balance', [['id' => 1, 'amount' => 10]]);

    expect(array_column(ResponseBuildTestCalls::$calls, 'computer'))->toBe([ResponseBuildTestCurrencies::class, ResponseBuildTestBalances::class])
        ->and($data)->toBe([['id' => 1, 'balance' => '10 XOF']]);
});

it('passes compute() only the requested keys and those fields: requires', function (): void {
    renderAccounts(ResponseBuildTestAccountResource::class, 'label,ceiling', [['id' => 1]]);

    expect(ResponseBuildTestCalls::$calls)->toBe([
        ['computer' => ResponseBuildTestBalances::class, 'models' => [1], 'fields' => ['limit', 'ceiling']],
    ]);
});

it('computes only the fields a model reached again lacks', function (): void {
    $account = new ResponseBuildTestAccount(['id' => 1]);
    startSelection(ResponseBuildTestAccountResource::class, ResponseShape::Item, 'limit');
    $loader = app(SelectionLoader::class);

    $loader->load($account, ResponseBuildTestAccountResource::class, (new SelectionParser)->parse('limit', null)->root());
    $loader->load($account, ResponseBuildTestAccountResource::class, (new SelectionParser)->parse('limit,ceiling', null)->root());

    expect(array_column(ResponseBuildTestCalls::$calls, 'fields'))->toBe([['limit'], ['ceiling']]);
});

it('excludes a model its when: refuses from compute() and omits the field for it', function (): void {
    $data = renderAccounts(ResponseBuildTestAccountResource::class, 'owner', [
        ['id' => 1, 'owner_id' => 7],
        ['id' => 2, 'owner_id' => 8],
    ]);

    expect(ResponseBuildTestCalls::$calls)->toBe([
        ['computer' => ResponseBuildTestBalances::class, 'models' => [1], 'fields' => ['owner']],
    ])->and($data)->toBe([
        ['id' => 1, 'owner' => 'owner'],
        ['id' => 2],
    ]);
});

it('renders a computedBy closure per model', function (): void {
    $data = renderAccounts(ResponseBuildTestAccountResource::class, 'initials,label', [
        ['id' => 1, 'name' => 'awa'],
        ['id' => 2, 'name' => 'binta'],
    ]);

    expect($data)->toBe([
        ['id' => 1, 'label' => 'limit limit', 'initials' => 'AW'],
        ['id' => 2, 'label' => 'limit limit', 'initials' => 'BI'],
    ]);
});

it('generates toArray() in declaration order, parent::define() where it is called', function (): void {
    startSelection(ResponseBuildTestChildResource::class, ResponseShape::Item, null, 'peers');
    $response = ApiResponse::item(new ResponseBuildTestAccount(['id' => 1, 'name' => 'Awa', 'code' => 'A1', 'amount' => 3]), ResponseBuildTestChildResource::class);

    expect(ResponseBuildTestChildResource::definition()->members())->toBe(['code', 'id', 'peers', 'name', 'amount'])
        ->and($response instanceof JsonResponse ? array_keys($response->getData(true)['data']) : null)->toBe(['code', 'id', 'peers', 'name', 'amount']);
});

it('restricts BuiltValues to the fields: of the builder', function (string $field, string $message): void {
    expect(fn () => renderAccounts(ResponseBuildTestRestrictedResource::class, $field, [['id' => 1, 'name' => 'Awa']]))
        ->toThrow(ResponseSelectionException::class, $message);
})->with([
    'undeclared key'     => ['reads_other', 'do not list it'],
    'non-computed field' => ['reads_column', 'read it on the model'],
]);

it('loads nothing for a member its request-only when: refuses nor for its dependents, but keeps a shared dependency', function (): void {
    $loader = app(ResponseBuildTestLoader::class);

    expect($loader->columns(new ResponseBuildTestAccount, ResponseBuildTestGuardedResource::class, (new SelectionParser)->parse('name,secret,secret_total,public_total', null)->root()))
        ->toBe(['id', 'name', 'shared_column', 'total_column'])
        ->and($loader->columns(new ResponseBuildTestAccount, ResponseBuildTestGuardedResource::class, (new SelectionParser)->parse('secret_total', null)->root()))
        ->toBe(['id']);
});

it('omits a member its request-only when: refuses, and the members needing it', function (): void {
    expect(renderAccounts(ResponseBuildTestGuardedResource::class, 'name,secret,secret_total', [['id' => 1, 'name' => 'Awa', 'secret_column' => 's', 'secret_total_column' => 3]]))
        ->toBe([['id' => 1, 'name' => 'Awa']]);
});

it('omits a member for the models its dependency per-model when: refuses', function (): void {
    expect(renderAccounts(ResponseBuildTestAbsenceResource::class, 'owner_label', [
        ['id' => 1, 'owner_id' => 7],
        ['id' => 2, 'owner_id' => 8],
    ]))->toBe([
        ['id' => 1, 'owner_label' => 'of owner'],
        ['id' => 2],
    ])->and(ResponseBuildTestCalls::$seen)->toBe(['owner_label:1']);
});

it('omits a member for the models its dependency computer left out, without passing them to compute()', function (): void {
    $data = renderAccounts(ResponseBuildTestAbsenceResource::class, 'rate,converted', [['id' => 1], ['id' => 2]]);

    expect($data)->toBe([
        ['id' => 1, 'rate' => 2, 'converted' => 20],
        ['id' => 2],
    ])->and(ResponseBuildTestCalls::$calls)->toBe([
        ['computer' => ResponseBuildTestConversions::class, 'models' => [1], 'fields' => ['converted']],
    ]);
});

it('propagates absence transitively through fields:, to virtual includes too', function (): void {
    expect(renderAccounts(ResponseBuildTestAbsenceResource::class, 'chain_a', [
        ['id' => 1, 'kind' => 'ok'],
        ['id' => 2, 'kind' => 'no'],
    ], 'chained'))->toBe([
        ['id' => 1, 'chain_a' => 'cba', 'chained' => null],
        ['id' => 2],
    ])->and(ResponseBuildTestCalls::$seen)->toBe(['chained:1']);
});

it('computes once per model class, so models of two classes sharing a key keep their own values', function (): void {
    startSelection(ResponseBuildTestAbsenceResource::class, ResponseShape::List, 'kind_name');
    $response = ApiResponse::list([
        new ResponseBuildTestAccount(['id' => 1]),
        new ResponseBuildTestOtherAccount(['id' => 1]),
    ], ResponseBuildTestAbsenceResource::class);

    expect($response instanceof JsonResponse ? $response->getData(true)['data'] : null)->toBe([
        ['id' => 1, 'kind_name' => 'ResponseBuildTestAccount'],
        ['id' => 1, 'kind_name' => 'ResponseBuildTestOtherAccount'],
    ])->and(array_column(ResponseBuildTestCalls::$calls, 'models'))->toBe([[1], [1]]);
});

it('rejects a model without key reaching a FieldComputer', function (): void {
    renderAccounts(ResponseBuildTestAbsenceResource::class, 'kind_name', [['name' => 'Awa']]);
})->throws(ResponseSelectionException::class, 'without key');

it('renders an asList virtual include as a list and another one as one child', function (): void {
    expect(renderAccounts(ResponseBuildTestVirtualResource::class, 'name', [['id' => 1, 'name' => 'Awa']], 'peers,partner'))->toBe([[
        'id'      => 1,
        'name'    => 'Awa',
        'peers'   => [['id' => 10, 'name' => 'Peer'], ['id' => 11, 'name' => 'Other']],
        'partner' => ['id' => 12, 'name' => 'Partner'],
    ]]);
});

it('applies a name under a polymorphic include to the Resources declaring it and ignores it on the others', function (): void {
    expect(renderAccounts(ResponseBuildTestSubjectsResource::class, 'id', [['id' => 1]], 'subjects(name,options,unknown),subjects.parts,subjects.missing'))->toBe([[
        'id'       => 1,
        'subjects' => [
            ['id' => 10, 'name' => 'Variant', 'options' => ['size' => 'L']],
            ['id' => 20, 'name' => 'Bundle', 'parts' => [['id' => 30, 'name' => 'Part']]],
        ],
    ]]);
});

it('drops the children a resolver gives no Resource from a list include, and renders null for a single one', function (): void {
    expect(renderAccounts(ResponseBuildTestResolvedResource::class, 'id', [['id' => 1]], 'members,lead'))->toBe([[
        'id'      => 1,
        'members' => [['id' => 10, 'name' => 'Peer'], ['id' => 12, 'name' => 'Other']],
        'lead'    => null,
    ]]);
});

it('renders an Eloquent collection include as a list and a BelongsTo include as one child', function (): void {
    startSelection(ResponseBuildTestRelationResource::class, ResponseShape::Item, 'name', 'owner,members');
    $account = new ResponseBuildTestAccount(['id' => 1, 'name' => 'Awa', 'owner_id' => 2]);
    $account->setRelation('owner', new ResponseBuildTestAccount(['id' => 2, 'name' => 'Binta']));
    $account->setRelation('members', new Collection([
        new ResponseBuildTestAccount(['id' => 3, 'name' => 'Coumba', 'owner_id' => 1]),
        new ResponseBuildTestAccount(['id' => 4, 'name' => 'Dior', 'owner_id' => 1]),
    ]));

    $response = ApiResponse::item($account, ResponseBuildTestRelationResource::class);

    expect($response instanceof JsonResponse ? $response->getData(true)['data'] : null)->toBe([
        'id'      => 1,
        'name'    => 'Awa',
        'owner'   => ['id' => 2, 'name' => 'Binta'],
        'members' => [['id' => 3, 'name' => 'Coumba'], ['id' => 4, 'name' => 'Dior']],
    ]);
});

it('calls a VirtualIncludeLoader once per parent class, so parents sharing a key keep their own children', function (): void {
    startSelection(ResponseBuildTestPartnerResource::class, ResponseShape::List, 'id', 'partner');
    $response = ApiResponse::list([
        new ResponseBuildTestAccount(['id' => 1]),
        new ResponseBuildTestOtherAccount(['id' => 1]),
    ], ResponseBuildTestPartnerResource::class);

    expect($response instanceof JsonResponse ? $response->getData(true)['data'] : null)->toBe([
        ['id' => 1, 'partner' => ['id' => 101, 'name' => 'ResponseBuildTestAccount partner']],
        ['id' => 1, 'partner' => ['id' => 101, 'name' => 'ResponseBuildTestOtherAccount partner']],
    ])->and(ResponseBuildTestCalls::$seen)->toBe(['ResponseBuildTestAccount:1', 'ResponseBuildTestOtherAccount:1']);
});

it('rejects a parent without key reaching a VirtualIncludeLoader', function (): void {
    renderAccounts(ResponseBuildTestPartnerResource::class, 'id', [['name' => 'Awa']], 'partner');
})->throws(ResponseSelectionException::class, 'without key');

it('rejects a virtual include child that is not a model', function (): void {
    renderAccounts(ResponseBuildTestVirtualResource::class, 'name', [['id' => 1, 'name' => 'Awa']], 'summary');
})->throws(ResponseSelectionException::class, 'summary');

it('rejects a when: closure of another shape at freeze', function (callable $when): void {
    (new ResourceDefinition)
        ->field('name', when: $when(...))
        ->freeze(ResponseBuildTestAccountResource::class);
})->with([
    'model only'      => [fn (Model $model): bool => true],
    'no parameter'    => [fn (): bool => true],
    'request first'   => [fn (Request $request, Model $model): bool => true],
    'three arguments' => [fn (Model $model, Request $request, int $extra): bool => true],
    'form request'    => [fn (ResponseBuildTestFormRequest $request): bool => true],
    'model and form'  => [fn (Model $model, ResponseBuildTestFormRequest $request): bool => true],
])->throws(ResponseDeclarationException::class);

it('accepts a when: closure typed with a type the request satisfies', function (): void {
    $definition = (new ResourceDefinition)
        ->field('name', when: fn (\Symfony\Component\HttpFoundation\Request $request): bool => true)
        ->freeze(ResponseBuildTestAccountResource::class);

    expect($definition->conditionReadsRequestOnly('name'))->toBeTrue();
});

final class ResponseBuildTestLazyResource extends ApiResource
{
    /** @var array<int, bool> */
    public static array $seen = [];

    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('guarded', relations: ['owner'], computedBy: function (Model $model): bool {
                $owner = $model->getRelation('owner');
                self::$seen = [$model->preventsLazyLoading, $owner->preventsLazyLoading];

                return true;
            });
    }
}

it('prevents lazy loading on every model reached through loaded relations while building, then restores each flag', function (): void {
    $account = new ResponseBuildTestAccount(['id' => 1, 'owner_id' => 2]);
    // loaded by business code, alone, with a cycle back to the account
    $owner = new ResponseBuildTestAccount(['id' => 2]);
    $account->setRelation('owner', $owner);
    $owner->setRelation('owner', $account);
    startSelection(ResponseBuildTestLazyResource::class, ResponseShape::Item, 'guarded');

    ApiResponse::item($account, ResponseBuildTestLazyResource::class);

    expect(ResponseBuildTestLazyResource::$seen)->toBe([true, true])
        ->and($account->preventsLazyLoading)->toBeFalse()
        ->and($owner->preventsLazyLoading)->toBeFalse();
});

final class ResponseBuildTestThrowingResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('boom', relations: ['owner'], computedBy: fn (Model $model): never => throw new RuntimeException('boom'));
    }
}

it('restores every lazy-loading flag when building throws', function (): void {
    $account = new ResponseBuildTestAccount(['id' => 1, 'owner_id' => 2]);
    $owner = new ResponseBuildTestAccount(['id' => 2]);
    $owner->preventsLazyLoading = true;
    $account->setRelation('owner', $owner);
    startSelection(ResponseBuildTestThrowingResource::class, ResponseShape::Item, 'boom');

    expect(fn () => ApiResponse::item($account, ResponseBuildTestThrowingResource::class))->toThrow(RuntimeException::class, 'boom')
        ->and($account->preventsLazyLoading)->toBeFalse()
        ->and($owner->preventsLazyLoading)->toBeTrue();
});

final class ResponseBuildTestFlagResource extends ApiResource
{
    /** @var array<int, bool> */
    public static array $seen = [];

    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('guarded', columns: [], computedBy: function (Model $model): bool {
                self::$seen[] = $model->preventsLazyLoading;

                return true;
            });
    }
}

final class ResponseBuildTestFlagParentResource extends ApiResource
{
    public static ?Model $child = null;

    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->virtualInclude('buddy', ResponseBuildTestFlagResource::class, fn (Model $parent): Model => self::$child = new ResponseBuildTestAccount(['id' => 3]));
    }
}

it('prevents lazy loading on a virtual child while building it, then restores its flag', function (): void {
    ResponseBuildTestFlagResource::$seen = [];
    ResponseBuildTestFlagParentResource::$child = null;

    expect(renderAccounts(ResponseBuildTestFlagParentResource::class, 'id', [['id' => 1]], 'buddy'))->toBe([['id' => 1, 'buddy' => ['id' => 3, 'guarded' => true]]])
        ->and(ResponseBuildTestFlagResource::$seen)->toBe([true])
        ->and(ResponseBuildTestFlagParentResource::$child?->preventsLazyLoading)->toBeFalse();
});
