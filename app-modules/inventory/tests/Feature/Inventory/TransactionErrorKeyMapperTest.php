<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Lahatre\Inventory\Services\TransactionErrorKeyMapper;

it('maps multiple validation error keys while preserving all messages', function (): void {
    $exception = ValidationException::withMessages([
        'movements.0.item_id'     => ['The item is invalid.'],
        'movements.1.location_id' => ['The location is invalid.'],
    ]);

    $mapped = app(TransactionErrorKeyMapper::class)->mapValidationException($exception, [
        'movements.*.item_id'     => 'lines.*.product_id',
        'movements.*.location_id' => 'locations.*.id',
    ]);

    expect($mapped->errors())->toBe([
        'lines.0.product_id' => ['The item is invalid.'],
        'locations.1.id'     => ['The location is invalid.'],
    ]);
});

it('maps stock id descendant errors through the stock ids parent key', function (): void {
    $exception = ValidationException::withMessages([
        'movements.0.stock_ids.2' => ['The stock ID is invalid.'],
    ]);

    $mapped = app(TransactionErrorKeyMapper::class)->mapValidationException($exception, [
        'movements.*.stock_ids' => 'lines.*.stock_ids',
    ]);

    expect($mapped->errors())->toBe([
        'lines.0.stock_ids' => ['The stock ID is invalid.'],
    ]);
});

it('keeps unmapped validation keys unchanged', function (): void {
    $exception = ValidationException::withMessages([
        'movements.0.quantity' => ['The quantity is invalid.'],
    ]);

    $mapped = app(TransactionErrorKeyMapper::class)->mapValidationException($exception, [
        'movements.*.item_id' => 'lines.*.product_id',
    ]);

    expect($mapped->errors())->toBe([
        'movements.0.quantity' => ['The quantity is invalid.'],
    ]);
});

it('rejects mappings that change the wildcard count', function (): void {
    expect(fn (): ValidationException => app(TransactionErrorKeyMapper::class)->mapValidationException(
        ValidationException::withMessages(['movements.0.item_id' => ['Invalid item.']]),
        ['movements.*.item_id' => 'lines.product_id']
    ))->toThrow(InvalidArgumentException::class);
});
