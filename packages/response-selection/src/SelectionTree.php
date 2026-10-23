<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection;

/**
 * Normalized result of parsing `fields` + `include`: the root node carries the root fields,
 * each include path is a child node carrying its own field list.
 */
final class SelectionTree
{
    public function __construct(private readonly SelectionNode $root) {}

    /**
     * Every field of the root, no include.
     */
    public static function rootFields(): self
    {
        return new self(new SelectionNode);
    }

    public function root(): SelectionNode
    {
        return $this->root;
    }

    /**
     * @return array<int, string>
     */
    public function paths(): array
    {
        return $this->root->paths();
    }

    public function isEmpty(): bool
    {
        return $this->root->selectsAllFields() && $this->root->children() === [];
    }

    /**
     * @return array{fields: array<int, string>|null, include: array<string, mixed>}
     */
    public function toArray(): array
    {
        return $this->root->toArray();
    }
}
