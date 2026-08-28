<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Lahatre\Shared\Rules\LocalTime;

it('accepts strict local times with optional seconds', function (string $value): void {
    $validator = Validator::make(['reminder_time' => $value], ['reminder_time' => [new LocalTime]]);

    expect($validator->passes())->toBeTrue();
})->with(['08:00', '08:00:00', '23:59:59']);

it('rejects invalid local times', function (mixed $value): void {
    $validator = Validator::make(['reminder_time' => $value], ['reminder_time' => [new LocalTime]]);

    expect($validator->fails())->toBeTrue();
})->with(['8:00', '24:00', '08:60', '08:00:00.000', 800]);
