<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Lahatre\ResponseSelection\Selection\SelectionParser;

/**
 * @return array<string, array<int, string>>
 */
function selectionLimitErrors(?string $fields, ?string $include): array
{
    try {
        (new SelectionParser)->parse($fields, $include);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('ships lowered limits by default', function (): void {
    expect(config('response-selection.limits'))->toBe(['max_depth' => 4, 'max_paths' => 20, 'max_fields_per_node' => 50, 'max_include_items' => 100])
        ->and(config('response-selection.documented_include_depth'))->toBe(2);
});

it('rejects an include path deeper than the configured depth', function (): void {
    config(['response-selection.limits.max_depth' => 2]);

    expect(selectionLimitErrors(null, 'lines.product'))->toBe([])
        ->and(selectionLimitErrors(null, 'lines.product.category'))->toHaveKey('include');
});

it('rejects more include paths than configured, each parent counted', function (): void {
    config(['response-selection.limits.max_paths' => 3]);

    expect(selectionLimitErrors(null, 'lines.product,source_depot'))->toBe([])
        ->and(selectionLimitErrors(null, 'lines.product.category,source_depot'))->toHaveKey('include');
});

it('rejects more fields per resource than configured', function (): void {
    config(['response-selection.limits.max_fields_per_node' => 2]);

    expect(selectionLimitErrors('id,name', null))->toBe([])
        ->and(selectionLimitErrors('id,name,code', null))->toHaveKey('fields')
        ->and(selectionLimitErrors(null, 'lines(a,b,c)'))->toHaveKey('include');
});
