<?php

declare(strict_types=1);

use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\required;
use function Lahatre\Shared\Data\withoutMissing;

it('preserves present values including empty values', function (mixed $value): void {
    $resolved = MissingValue::fromArray(['field' => $value], 'field');

    expect($resolved)->toBe($value);
})->with([
    'null'         => [null],
    'false'        => [false],
    'zero'         => [0],
    'empty string' => [''],
    'empty array'  => [[]],
]);

it('represents an allowed absent field', function (): void {
    expect(MissingValue::fromArray([], 'field', ['field']))
        ->toBe(MissingValue::Instance);
});

it('uses a default for an absent field', function (): void {
    expect(MissingValue::fromArray([], 'field', default: false))->toBeFalse()
        ->and(MissingValue::fromArray([], 'nullable', default: null))->toBeNull();
});

it('rejects an unexpected absent field', function (): void {
    expect(fn (): mixed => MissingValue::fromArray([], 'field'))
        ->toThrow(InvalidArgumentException::class);
});

it('filters only missing values', function (): void {
    expect(MissingValue::withoutMissing([
        'missing' => MissingValue::Instance,
        'null'    => null,
        'false'   => false,
        'zero'    => 0,
        'empty'   => [],
    ]))->toBe([
        'null'  => null,
        'false' => false,
        'zero'  => 0,
        'empty' => [],
    ]);
});

it('exposes concise namespaced helpers', function (): void {
    expect(withoutMissing(['missing' => MissingValue::Instance, 'value' => null]))
        ->toBe(['value' => null])
        ->and(required('value'))
        ->toBe('value');
});
