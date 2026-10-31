<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Rendering;

/**
 * What a Resource renders at one selection level in this response: the selected members the
 * request may see, in declaration order. Built once per response.
 *
 * @internal
 */
final readonly class RenderPlan
{
    /**
     * @param  array<int, RenderStep>  $steps
     * @param  bool  $readsConditions  some step checks a per-model `when:`
     */
    public function __construct(
        public array $steps,
        public bool $readsConditions,
    ) {}
}
