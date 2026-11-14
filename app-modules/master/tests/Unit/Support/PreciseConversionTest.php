<?php

declare(strict_types=1);

use Lahatre\Master\Models\Unit;
use Lahatre\Master\Support\PreciseConversion;

it('converts between units of a group without losing precision', function (string $amount, int $fromRatio, int $toRatio, string $expected): void {
    $from = new Unit(['code' => 'from', 'group_id' => 'group', 'ratio' => $fromRatio]);
    $to = new Unit(['code' => 'to', 'group_id' => 'group', 'ratio' => $toRatio]);

    expect(PreciseConversion::convertUnit($amount, $from, $to))->toBe($expected);
})->with([
    'six bottles make one pack of six'   => ['6', 1, 6, '1.0000000000'],
    'twenty-four bottles make one crate' => ['24', 1, 24, '1.0000000000'],
    'one pack of six makes six bottles'  => ['1', 6, 1, '6.0000000000'],
    'one bottle is a sixth of a pack'    => ['1', 1, 6, '0.1666666666'],
]);

it('refuses to convert between unit groups', function (): void {
    $from = new Unit(['code' => 'b', 'group_id' => 'bottle', 'ratio' => 1]);
    $to = new Unit(['code' => 'kg', 'group_id' => 'mass', 'ratio' => 1000]);

    expect(fn (): string => PreciseConversion::convertUnit('1', $from, $to))->toThrow(InvalidArgumentException::class);
});
