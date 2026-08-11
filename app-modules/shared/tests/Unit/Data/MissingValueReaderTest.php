<?php

declare(strict_types=1);

use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

it('reads present values without changing explicit null', function (): void {
    $read = MissingValueReader::fromArray(['field' => null], ['field']);

    expect($read->get('field'))->toBeNull();
});

it('returns the missing sentinel for allowed absent fields', function (): void {
    $read = MissingValueReader::fromArray([], ['field']);

    expect($read->get('field'))->toBe(MissingValue::Instance);
});

it('returns defaults for absent fields when provided', function (): void {
    $read = MissingValueReader::fromArray([]);

    expect($read->get('field', default: false))->toBeFalse();
});
