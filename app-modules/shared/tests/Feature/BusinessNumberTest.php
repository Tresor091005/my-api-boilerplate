<?php

declare(strict_types=1);

namespace Lahatre\Shared\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Lahatre\Shared\Exceptions\AssertionException;
use Lahatre\Shared\Exceptions\BusinessNumberException;
use Lahatre\Shared\Services\BusinessNumberService as BusinessNumber;

use function Pest\Laravel\travelBack;
use function Pest\Laravel\travelTo;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    setPermissionsTeamId(Str::uuid7()->toString());
});

afterEach(function (): void {
    setPermissionsTeamId(null);
    travelBack();
});

it('stores only the technical id and visible counter identity fields', function (): void {
    expect(Schema::getColumnListing('shared_business_number_counters'))->toBe([
        'id',
        'organization_id',
        'number_identity_hash',
        'number_identity',
        'value',
    ]);
});

it('generates a simple yearly invoice number', function (): void {
    travelTo('2026-08-21 12:00:00');

    expect(BusinessNumber::next('invoice'))
        ->toBe('INV-2026-000001')
        ->and(BusinessNumber::next('invoice'))
        ->toBe('INV-2026-000002');
});

it('resets a yearly sequence when the year changes', function (): void {
    travelTo('2026-12-31 23:59:59');

    expect(BusinessNumber::next('invoice'))->toBe('INV-2026-000001');

    travelTo('2027-01-01 00:00:00');

    expect(BusinessNumber::next('invoice'))->toBe('INV-2027-000001');
});

it('resets monthly and daily sequences at their period boundary', function (): void {
    defineBusinessNumber('monthly_document', [
        'format'   => 'MON-{YEAR2}-{MONTH}-{SEQ}',
        'reset'    => 'monthly',
        'sequence' => ['start' => 1, 'pad' => 4, 'grouping' => null],
    ]);
    defineBusinessNumber('daily_document', [
        'format'   => 'DAY-{YEAR2}{MONTH}{DAY}-{SEQ}',
        'reset'    => 'daily',
        'sequence' => ['start' => 1, 'pad' => 4, 'grouping' => null],
    ]);

    travelTo('2026-08-31 23:59:59');
    expect(BusinessNumber::next('monthly_document'))->toBe('MON-26-08-0001');
    expect(BusinessNumber::next('daily_document'))->toBe('DAY-260831-0001');

    travelTo('2026-09-01 00:00:00');
    expect(BusinessNumber::next('monthly_document'))->toBe('MON-26-09-0001');
    expect(BusinessNumber::next('daily_document'))->toBe('DAY-260901-0001');
});

it('never resets a never sequence', function (): void {
    defineBusinessNumber('purchase_order', [
        'format'   => 'PO-{SEQ}',
        'reset'    => 'never',
        'sequence' => ['start' => 1, 'pad' => 6, 'grouping' => null],
    ]);

    travelTo('2026-01-01');
    expect(BusinessNumber::next('purchase_order'))->toBe('PO-000001');

    travelTo('2040-12-31');
    expect(BusinessNumber::next('purchase_order'))->toBe('PO-000002');
});

it('isolates counters by organization', function (): void {
    $organizationA = Str::uuid7()->toString();
    $organizationB = Str::uuid7()->toString();
    travelTo('2026-08-21');

    setPermissionsTeamId($organizationA);
    expect(BusinessNumber::next('invoice'))->toBe('INV-2026-000001');

    setPermissionsTeamId($organizationB);
    expect(BusinessNumber::next('invoice'))->toBe('INV-2026-000001');

    setPermissionsTeamId($organizationA);
    expect(BusinessNumber::next('invoice'))->toBe('INV-2026-000002');
});

it('generates the configured stock transfer number', function (): void {
    defineBusinessNumber('stock_transfer', [
        'format'   => 'TRF-{YEAR}-{SEQ}',
        'reset'    => 'yearly',
        'sequence' => ['start' => 1, 'pad' => 5, 'grouping' => null],
    ]);

    travelTo('2026-08-21');

    expect(BusinessNumber::next('stock_transfer'))->toBe('TRF-2026-00001')
        ->and(BusinessNumber::next('stock_transfer'))->toBe('TRF-2026-00002');
});

it('treats padding as a minimum width', function (): void {
    seedBusinessCounter('INV-2026-0', 9997);

    travelTo('2026-08-21');
    expect(BusinessNumber::next('invoice'))->toEndWith('9998');
    expect(BusinessNumber::next('invoice'))->toEndWith('9999');
    expect(BusinessNumber::next('invoice'))->toEndWith('10000');
    expect(BusinessNumber::next('invoice'))->toEndWith('10001');
});

it('groups sequence digits only for display', function (): void {
    defineBusinessNumber('large_document', [
        'format'   => 'DOC-{SEQ}',
        'reset'    => 'never',
        'sequence' => [
            'start'    => 1,
            'pad'      => 9,
            'grouping' => [
                'every'     => 3,
                'separator' => '_',
            ],
        ],
    ]);

    seedBusinessCounter('DOC-0', 4999);

    expect(BusinessNumber::next('large_document'))->toBe('DOC-000_005_000');
    expect(currentCounterValue('DOC-0'))->toBe(5000);
});

it('supports a custom starting value', function (): void {
    defineBusinessNumber('legacy_invoice', [
        'format'   => 'LEG-{SEQ}',
        'reset'    => 'never',
        'sequence' => ['start' => 1000, 'pad' => 6, 'grouping' => null],
    ]);

    expect(BusinessNumber::next('legacy_invoice'))->toBe('LEG-001000')
        ->and(BusinessNumber::next('legacy_invoice'))->toBe('LEG-001001');
});

it('keeps business number diagnostics in exception context', function (): void {
    $exceptions = [
        BusinessNumberException::definitionNotFound('invoice'),
        BusinessNumberException::invalidDefinition('invoice', __('shared::exceptions.business_number_reasons.invalid_format')),
    ];

    $messages = array_map(
        static fn (AssertionException $exception): string => $exception->getMessage(),
        $exceptions,
    );
    $contexts = array_map(
        static fn (AssertionException $exception): array => $exception->context(),
        $exceptions,
    );

    expect($messages)->toBe([
        'The requested business number definition does not exist.',
        'The business number definition is invalid.',
    ])->and($contexts)->toBe([
        ['key' => 'invoice'],
        ['key' => 'invoice', 'reason' => __('shared::exceptions.business_number_reasons.invalid_format')],
    ]);
});

it('rejects unknown format tokens', function (): void {
    defineBusinessNumber('broken', [
        'format'   => 'ABC-{WHATEVER}-{SEQ}',
        'reset'    => 'never',
        'sequence' => ['start' => 1, 'pad' => 3, 'grouping' => null],
    ]);

    expect(fn (): string => BusinessNumber::next('broken'))
        ->toThrow(BusinessNumberException::class);
});

it('rejects formats missing date tokens required by their reset period', function (): void {
    $definitions = [
        'monthly_without_month' => ['format' => 'INV-{YEAR}-{SEQ}', 'reset' => 'monthly'],
        'daily_without_day'     => ['format' => 'INV-{YEAR}-{MONTH}-{SEQ}', 'reset' => 'daily'],
    ];

    foreach ($definitions as $key => $definition) {
        defineBusinessNumber($key, [
            ...$definition,
            'sequence' => ['start' => 1, 'pad' => 3, 'grouping' => null],
        ]);

        expect(fn (): string => BusinessNumber::next($key))
            ->toThrow(BusinessNumberException::class);
    }
});

it('allows additional date tokens beyond the reset minimum', function (): void {
    travelTo('2026-08-21');

    defineBusinessNumber('never_with_year', [
        'format'   => 'INV-{YEAR}-{SEQ}',
        'reset'    => 'never',
        'sequence' => ['start' => 1, 'pad' => 3, 'grouping' => null],
    ]);

    expect(BusinessNumber::next('never_with_year'))->toBe('INV-2026-001');

    defineBusinessNumber('yearly_with_month', [
        'format'   => 'INV-{YEAR}-{MONTH}-{SEQ}',
        'reset'    => 'yearly',
        'sequence' => ['start' => 1, 'pad' => 3, 'grouping' => null],
    ]);

    expect(BusinessNumber::next('yearly_with_month'))->toBe('INV-2026-08-001');
});

it('resumes a visible sequence when its configured format is restored', function (): void {
    travelTo('2026-08-21');
    $defaultDefinition = config('business-numbering.invoice');

    expect(BusinessNumber::next('invoice'))->toBe('INV-2026-000001');

    defineBusinessNumber('invoice', [
        'format'   => '#In-{YEAR}-{SEQ}',
        'reset'    => 'yearly',
        'sequence' => ['start' => 1, 'pad' => 3, 'grouping' => null],
    ]);

    expect(BusinessNumber::next('invoice'))->toBe('#In-2026-001');

    Config::set('business-numbering.invoice', $defaultDefinition);

    expect(BusinessNumber::next('invoice'))->toBe('INV-2026-000002');
});

it('shares one counter when different keys render the same visible identity', function (): void {
    travelTo('2026-08-21');
    defineBusinessNumber('alternate_invoice', [
        'format'   => 'INV-{YEAR}-{SEQ}',
        'reset'    => 'yearly',
        'sequence' => ['start' => 1, 'pad' => 6, 'grouping' => null],
    ]);

    expect(BusinessNumber::next('invoice'))->toBe('INV-2026-000001')
        ->and(BusinessNumber::next('alternate_invoice'))->toBe('INV-2026-000002');
});

it('stores the rendered sequence family as the counter identity', function (): void {
    travelTo('2026-08-21');

    BusinessNumber::next('invoice');

    $counter = DB::table('shared_business_number_counters')->first();

    expect($counter)->not->toBeNull()
        ->and($counter->number_identity)->toBe('INV-2026-0')
        ->and($counter->number_identity_hash)->toBe(hash('sha256', 'INV-2026-0'))
        ->and((int) $counter->value)->toBe(1);
});

it('rolls back the counter with the surrounding business transaction', function (): void {
    try {
        DB::transaction(function (): void {
            expect(BusinessNumber::next('invoice'))->toBe('INV-2026-000001');
            throw new \RuntimeException('rollback test');
        });
    } catch (\RuntimeException $exception) {
        expect($exception->getMessage())->toBe('rollback test');
    }

    expect(BusinessNumber::next('invoice'))->toBe('INV-2026-000001');
});

function defineBusinessNumber(string $key, array $definition): void
{
    Config::set('business-numbering.'.$key, $definition);
}

function seedBusinessCounter(string $numberIdentity, int $value): void
{
    DB::table('shared_business_number_counters')->insert([
        'id'                   => Str::uuid7()->toString(),
        'organization_id'      => currentOrganizationId(),
        'number_identity_hash' => hash('sha256', $numberIdentity),
        'number_identity'      => $numberIdentity,
        'value'                => $value,
    ]);
}

function currentCounterValue(string $numberIdentity): int
{
    return (int) DB::table('shared_business_number_counters')
        ->where('organization_id', currentOrganizationId())
        ->where('number_identity_hash', hash('sha256', $numberIdentity))
        ->value('value');
}
