<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Selection;

use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\Exceptions\ResponseSelectionException;

/**
 * Turns named views into selection nodes. A view is checked against the children's definitions
 * here, on first use, since a Resource cannot read them while it freezes.
 */
final class SelectionViews
{
    /**
     * The selection of one view of a Resource, its includes fixed by a view already expanded.
     *
     * @param  class-string<ApiResource>  $resourceClass
     *
     * @throws ResponseSelectionException when the view is unknown or declared wrong
     */
    public function tree(string $resourceClass, string $view): SelectionTree
    {
        return new SelectionTree($this->buildView($resourceClass, $view, '', []));
    }

    /**
     * Gives each selected include fixed by a view a fresh node of that view, built under its own
     * path. The tree must have passed SelectionValidator.
     *
     * @param  class-string<ApiResource>  $resourceClass
     */
    public function expand(SelectionTree $tree, string $resourceClass): SelectionTree
    {
        $root = $this->expandNode($tree->root(), [$resourceClass]);

        return $root === $tree->root() ? $tree : new SelectionTree($root);
    }

    /**
     * The view an include always renders at a level drawn by these Resources, as [child Resource,
     * view], or null when clients select it freely. Resources not declaring the include are ignored.
     *
     * @param  array<int, class-string<ApiResource>>  $resourceClasses
     *
     * @throws ResponseSelectionException when the Resources declare the include differently
     *
     * @return array{0: class-string<ApiResource>, 1: string}|null
     */
    public static function fixedView(array $resourceClasses, string $name): ?array
    {
        $fixed = null;
        $free = false;
        $declaring = [];

        foreach ($resourceClasses as $class) {
            $definition = $class::definition();
            $childClass = $definition->includeResourceClass($name);

            if ($definition->includeDefinition($name) === null) {
                continue;
            }

            $declaring[] = $class;
            $view = $definition->includeView($name);

            if ($view === null || $childClass === null) {
                $free = true;

                continue;
            }

            if ($fixed !== null && $fixed !== [$childClass, $view]) {
                throw ResponseSelectionException::fixedIncludeMismatch($name, $declaring);
            }

            $fixed = [$childClass, $view];
        }

        if ($fixed !== null && $free) {
            throw ResponseSelectionException::fixedIncludeMismatch($name, $declaring);
        }

        return $fixed;
    }

    /**
     * Every Resource an include may render, for the Resources of a level that declare it.
     *
     * @param  array<int, class-string<ApiResource>>  $resourceClasses
     * @return array<int, class-string<ApiResource>>
     */
    private function childClasses(array $resourceClasses, string $name): array
    {
        $children = [];

        foreach ($resourceClasses as $class) {
            $definition = $class::definition();

            if ($definition->includeDefinition($name) !== null) {
                array_push($children, ...$definition->includeResourceClasses($name));
            }
        }

        return array_values(array_unique($children));
    }

    /**
     * @param  array<int, class-string<ApiResource>>  $resourceClasses
     */
    private function expandNode(SelectionNode $node, array $resourceClasses): SelectionNode
    {
        $children = [];
        $changed = false;

        foreach ($node->children() as $name => $child) {
            $fixed = self::fixedView($resourceClasses, $name);
            $expanded = $fixed === null
                ? $this->expandNode($child, $this->childClasses($resourceClasses, $name))
                : $this->buildView($fixed[0], $fixed[1], $child->path, []);

            $changed = $changed || $expanded !== $child;
            $children[$name] = $expanded;
        }

        return $changed ? new SelectionNode($node->path, $node->fields(), $children) : $node;
    }

    /**
     * @param  class-string<ApiResource>  $resourceClass
     * @param  array<int, string>  $stack  views being built, outermost first, to stop a cycle
     */
    private function buildView(string $resourceClass, string $view, string $path, array $stack): SelectionNode
    {
        $key = "{$resourceClass}::{$view}";

        if (in_array($key, $stack, true)) {
            throw ResponseSelectionException::viewCycle([...$stack, $key]);
        }

        $selection = $resourceClass::definition()->viewSelection($view)
            ?? throw ResponseSelectionException::viewNotDeclared($resourceClass, $view);

        return $this->buildLevel($selection, [$resourceClass], $path, $resourceClass, $view, [...$stack, $key]);
    }

    /**
     * Copies one level of a view under $path, checking its names against the Resources drawing it.
     *
     * @param  SelectionNode  $level  the level as parsed, its path relative to the view
     * @param  array<int, class-string<ApiResource>>  $resourceClasses
     * @param  class-string<ApiResource>  $owner  the Resource declaring the view
     * @param  array<int, string>  $stack
     */
    private function buildLevel(SelectionNode $level, array $resourceClasses, string $path, string $owner, string $view, array $stack): SelectionNode
    {
        foreach ($level->fields() ?? [] as $field) {
            if ($field !== 'id' && !$this->declaresField($resourceClasses, $field)) {
                throw ResponseSelectionException::viewUnknownField($owner, $view, $level->isRoot() ? $field : "{$level->path}.{$field}");
            }
        }

        $children = [];

        foreach ($level->children() as $name => $child) {
            $childClasses = $this->childClasses($resourceClasses, $name);
            $childPath = $path === '' ? $name : "{$path}.{$name}";

            if ($childClasses === []) {
                throw ResponseSelectionException::viewUnknownInclude($owner, $view, $child->path);
            }

            $fixed = self::fixedView($resourceClasses, $name);

            if ($fixed !== null) {
                if ($child->fields() !== null || $child->children() !== []) {
                    throw ResponseSelectionException::viewFixedIncludeNotBare($owner, $view, $child->path);
                }

                $children[$name] = $this->buildView($fixed[0], $fixed[1], $childPath, $stack);
            } elseif ($child->fields() === null) {
                throw ResponseSelectionException::viewIncludeWithoutFields($owner, $view, $child->path);
            } else {
                $children[$name] = $this->buildLevel($child, $childClasses, $childPath, $owner, $view, $stack);
            }
        }

        return new SelectionNode($path, $level->fields(), $children);
    }

    /**
     * @param  array<int, class-string<ApiResource>>  $resourceClasses
     */
    private function declaresField(array $resourceClasses, string $field): bool
    {
        foreach ($resourceClasses as $class) {
            if ($class::definition()->hasField($field)) {
                return true;
            }
        }

        return false;
    }
}
