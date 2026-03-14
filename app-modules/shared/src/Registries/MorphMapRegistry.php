<?php

declare(strict_types=1);

namespace Lahatre\Shared\Registries;

use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;

class MorphMapRegistry
{
    /** @var array<string, string> */
    protected array $map = [];

    /**
     * Register a map of morph aliases to class names.
     *
     * @param  array<string, string>  $morphs
     *
     * @throws InvalidArgumentException
     */
    public function register(array $morphs): void
    {
        foreach ($morphs as $alias => $class) {
            if (isset($this->map[$alias]) && $this->map[$alias] !== $class) {
                throw new InvalidArgumentException(
                    "The morph alias '{$alias}' is already registered for '{$this->map[$alias]}'. ".
                    "Cannot reassign it to '{$class}'."
                );
            }
            $this->map[$alias] = $class;
        }

        Relation::morphMap($this->map);
    }

    /**
     * Get the current registered map.
     *
     * @return array<string, string>
     */
    public function getMap(): array
    {
        return $this->map;
    }
}
