<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Exceptions;

use Illuminate\Database\Eloquent\Collection;
use Lahatre\Catalog\Models\Tag;
use Lahatre\Shared\Exceptions\AssertionException;

class TagInUseException extends AssertionException
{
    public function __construct(Tag $tag, Collection $products)
    {
        parent::__construct(
            __('catalog::exceptions.tag_in_use', ['products' => $products->pluck('name')->implode(', ')]),
            [
                'tag_id'   => $tag->id,
                'products' => $products->pluck('id')->all(),
            ]
        );
    }
}
