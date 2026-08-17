<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Lahatre\Shared\Http\Responses\ResponseContract;
use Lahatre\Shared\Http\Responses\ResponseMode;
use Lahatre\Shared\Registries\ResponseContractRegistry;

it('resolves a named shape and its relation dependencies', function (): void {
    $contract = ResponseContract::fromArray([
        'default_shape' => 'list',
        'shapes'        => [
            'list' => [
                'required_loads' => ['product'],
                'includes'       => ['tags' => ['loads' => ['tags.metadata']]],
            ],
        ],
    ]);

    $shape = $contract->resolveShape(null);

    expect($contract->resolveMode(null, 'GET'))->toBe(ResponseMode::Resource)
        ->and($shape?->validateIncludes(['tags']))->toBe(['tags'])
        ->and($shape?->relationsToLoad(['tags']))->toEqualCanonicalizing(['product', 'tags.metadata']);
});

it('rejects a response mode or include that the contract does not allow', function (): void {
    $contract = ResponseContract::fromArray([
        'shapes' => [
            'list' => ['includes' => ['tags' => ['loads' => ['tags']]]],
        ],
        'default_shape' => 'list',
    ]);

    expect(fn () => $contract->resolveMode('none', 'GET'))
        ->toThrow(ValidationException::class);

    expect($contract->resolveMode(null, 'POST'))->toBe(ResponseMode::None)
        ->and($contract->resolveMode('resource', 'POST'))->toBe(ResponseMode::Resource)
        ->and(fn () => $contract->resolveMode('resource', 'DELETE'))
        ->toThrow(ValidationException::class);

    expect(fn () => $contract->resolveShape('list')?->validateIncludes(['inventory']))
        ->toThrow(ValidationException::class);

    expect(fn () => ResponseContract::fromArray([
        'shapes' => ['invalid' => ['fields' => ['id']]],
    ]))->toThrow(InvalidArgumentException::class, 'field selection');
});

it('rejects a response contract route that is already registered', function (): void {
    $registry = new ResponseContractRegistry;
    $registry->registerMany(['variants.index' => []]);

    expect(fn () => $registry->registerMany(['variants.index' => []]))
        ->toThrow(InvalidArgumentException::class, 'variants.index');
});
