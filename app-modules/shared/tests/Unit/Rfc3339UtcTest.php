<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Lahatre\Shared\Rules\Rfc3339Utc;

it('accepts an RFC 3339 UTC timestamp with or without milliseconds', function (string $value): void {
    $validator = Validator::make(
        ['expires_at' => $value],
        ['expires_at' => [new Rfc3339Utc]],
    );

    expect($validator->passes())->toBeTrue();
})->with([
    'without milliseconds' => ['2026-08-28T14:30:00Z'],
    'with milliseconds'    => ['2026-08-28T14:30:00.000Z'],
    'with UTC offset'      => ['2026-08-28T14:30:00+00:00'],
]);

it('rejects timestamps that are not the canonical UTC format', function (mixed $value): void {
    $validator = Validator::make(
        ['expires_at' => $value],
        ['expires_at' => [new Rfc3339Utc]],
    );

    expect($validator->fails())->toBeTrue();
})->with([
    'date only'             => ['2026-08-28'],
    'with a non-UTC offset' => ['2026-08-28T16:30:00.000+02:00'],
    'invalid calendar date' => ['2026-02-30T14:30:00.000Z'],
    'non string value'      => [123],
]);
