<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Lahatre\Shared\Rules\YmdDate;

it('accepts a valid civil date', function (): void {
    $validator = Validator::make(
        ['expiration_date' => '2027-01-31'],
        ['expiration_date' => [new YmdDate]],
    );

    expect($validator->passes())->toBeTrue();
});

it('rejects values that are not strict Y-m-d dates', function (mixed $value): void {
    $validator = Validator::make(
        ['expiration_date' => $value],
        ['expiration_date' => [new YmdDate]],
    );

    expect($validator->fails())->toBeTrue();
})->with([
    'date time'             => ['2027-01-31T00:00:00Z'],
    'wrong separator'       => ['2027/01/31'],
    'invalid calendar date' => ['2027-02-29'],
    'non string value'      => [20270131],
]);
