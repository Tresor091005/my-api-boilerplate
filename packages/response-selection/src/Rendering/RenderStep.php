<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Rendering;

use Closure;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceResolver;
use Lahatre\ResponseSelection\Selection\SelectionNode;

/**
 * One member a Resource renders at one selection level in this response, with the checks left to
 * make for each model.
 *
 * @internal
 */
final class RenderStep
{
    /**
     * Resolver of a polymorphic include, built when its first child renders.
     */
    public ?ResourceResolver $resolver = null;

    /**
     * Resources the resolver may return, asked once.
     *
     * @var array<int, string>|null
     */
    public ?array $resolverResources = null;

    /**
     * @param  string|null  $slot  where a computed field's value is kept, null for a plain field or an include
     * @param  array<int, array{0: bool, 1: string, 2: Closure|null}>  $checks  per-model checks, in order:
     *                                                                          [true, member key, `when:`] or [false, slot, null]
     * @param  SelectionNode|null  $child  selection under an include, null for a field
     * @param  string|null  $relation  relation an Eloquent include renders
     * @param  class-string<ApiResource>|null  $childClass  null when a resolver picks it per child
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $isField,
        public readonly ?string $slot,
        public readonly array $checks,
        public readonly ?SelectionNode $child = null,
        public readonly bool $isVirtual = false,
        public readonly ?string $relation = null,
        public readonly ?string $childClass = null,
        public readonly bool $asList = false,
        public readonly int $limit = 0,
    ) {}
}
