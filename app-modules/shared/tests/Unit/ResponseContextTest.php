<?php

declare(strict_types=1);

use Lahatre\Shared\Http\Responses\ResponseContext;
use Lahatre\Shared\Http\Responses\ResponseMode;
use Lahatre\Shared\Http\Responses\ResponseShape;

it('uses default relation loads when no response shape is configured', function (): void {
    $context = new ResponseContext;

    expect($context->relationsToLoad(['product', 'unitGroup']))
        ->toBe(['product', 'unitGroup']);
});

it('uses the configured shape and ignores defaults when available', function (): void {
    $context = new ResponseContext;
    $context->configure(
        ResponseMode::Resource,
        ResponseShape::fromArray('list', [
            'required_loads' => ['unitGroup'],
        ]),
        [],
    );

    expect($context->relationsToLoad(['product', 'unitGroup']))
        ->toBe(['unitGroup']);

    $context->configure(ResponseMode::None, null, []);

    expect($context->relationsToLoad(['product', 'unitGroup']))->toBe([]);
});
