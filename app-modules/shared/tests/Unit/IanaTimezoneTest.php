<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Lahatre\Shared\Rules\IanaTimezone;

it('accepts an IANA timezone identifier', function (): void {
    $validator = Validator::make(['timezone' => 'Africa/Porto-Novo'], ['timezone' => [new IanaTimezone]]);

    expect($validator->passes())->toBeTrue();
});

it('rejects invalid timezone values', function (mixed $value): void {
    $validator = Validator::make(['timezone' => $value], ['timezone' => [new IanaTimezone]]);

    expect($validator->fails())->toBeTrue();
})->with(['UTC+1', 'Europe/Invalid', 1]);
