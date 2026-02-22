<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Assertions;

use Lahatre\Catalog\Exceptions\TagInUseException;
use Lahatre\Catalog\Models\Tag;

class TagAssertion
{
    /**
     * Asserts that a tag can be deleted.
     * A tag can only be deleted if it is not associated with any products.
     *
     * @throws TagInUseException If the tag is in use by products.
     */
    public function assertCanDelete(Tag $tag): void
    {
        $products = $tag->products()->take(5)->get();

        if ($products->isNotEmpty()) {
            throw new TagInUseException($tag, $products);
        }
    }
}
