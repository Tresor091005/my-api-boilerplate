<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Rendering;

/**
 * What the current response built for one model. ResponseContext holds one per model and changes
 * it in place.
 *
 * @internal
 */
final class ModelState
{
    /**
     * Values builders returned, by slot (ResourceDefinition::slotOf()).
     *
     * @var array<string, mixed>
     */
    public array $values = [];

    /**
     * Slots of the fields a builder computed without returning a value.
     *
     * @var array<string, true>
     */
    public array $absent = [];

    /**
     * Per-model `when:` results, by `Resource::member`.
     *
     * @var array<string, bool>
     */
    public array $conditions = [];

    /**
     * Virtual include children, by Resource class then include.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $virtual = [];
}
