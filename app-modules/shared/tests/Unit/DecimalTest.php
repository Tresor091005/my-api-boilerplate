<?php

declare(strict_types=1);

use Lahatre\Shared\Support\Decimal;

it('counts the significant decimal places of a decimal string', function (string $value, int $places): void {
    expect(Decimal::places($value))->toBe($places);
})->with([
    'integer'               => ['500', 0],
    'trailing zeros only'   => ['18.000000', 0],
    'one significant place' => ['2.50', 1],
    'four places'           => ['0.1234', 4],
]);

it('drops the trailing zeros of a decimal string', function (string $value, string $normalized): void {
    expect(Decimal::normalize($value))->toBe($normalized);
})->with([
    'integer'          => ['500', '500'],
    'whole decimal'    => ['500.000000', '500'],
    'trailing zeros'   => ['18.500000', '18.5'],
    'no trailing zero' => ['0.6069', '0.6069'],
    'zero'             => ['0.000000', '0'],
]);
