<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Selection;

/**
 * One level of a selection: the fields asked for here and the includes below it.
 */
final class SelectionNode
{
    /**
     * @param  array<int, string>|null  $fields
     * @param  array<string, SelectionNode>  $children
     */
    public function __construct(
        public readonly string $path = '',
        private readonly ?array $fields = null,
        private array $children = [],
    ) {}

    public function name(): string
    {
        $segments = explode('.', $this->path);

        return end($segments) ?: '';
    }

    public function isRoot(): bool
    {
        return $this->path === '';
    }

    public function selectsAllFields(): bool
    {
        return $this->fields === null;
    }

    /**
     * @return array<int, string>|null null when every field is selected
     */
    public function fields(): ?array
    {
        return $this->fields;
    }

    public function wantsField(string $key): bool
    {
        return $this->fields === null || in_array($key, $this->fields, true);
    }

    /**
     * @return array<string, SelectionNode>
     */
    public function children(): array
    {
        return $this->children;
    }

    public function child(string $name): ?SelectionNode
    {
        return $this->children[$name] ?? null;
    }

    public function hasChild(string $name): bool
    {
        return isset($this->children[$name]);
    }

    /**
     * Every include path below this node, parents before children.
     *
     * @return array<int, string>
     */
    public function paths(): array
    {
        $paths = [];

        foreach ($this->children as $child) {
            $paths[] = $child->path;
            array_push($paths, ...$child->paths());
        }

        return $paths;
    }

    /**
     * @return array{fields: array<int, string>|null, include: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'fields'  => $this->fields,
            'include' => array_map(fn (SelectionNode $child): array => $child->toArray(), $this->children),
        ];
    }
}
