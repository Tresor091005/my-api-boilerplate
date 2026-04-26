<?php

declare(strict_types=1);

namespace Lahatre\Master\Exceptions\Tag;

use Lahatre\Shared\Exceptions\AssertionException;

class ModelMissingHasTagsTraitException extends AssertionException
{
    public function __construct(string $model)
    {
        parent::__construct(
            __('master::exceptions.model_missing_has_tags_trait', [
                'model' => $model,
            ])
        );
    }
}
