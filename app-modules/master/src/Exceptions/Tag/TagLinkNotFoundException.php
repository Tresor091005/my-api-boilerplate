<?php

declare(strict_types=1);

namespace Lahatre\Master\Exceptions\Tag;

use Lahatre\Shared\Exceptions\AssertionException;

class TagLinkNotFoundException extends AssertionException
{
    /**
     * @param  array<int, string>  $names
     */
    public function __construct(string $type, array $names)
    {
        parent::__construct(
            __('master::exceptions.tag_link_not_found', [
                'type'  => $type,
                'names' => implode(', ', $names),
            ])
        );
    }
}
