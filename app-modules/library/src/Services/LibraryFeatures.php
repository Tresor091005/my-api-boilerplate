<?php

declare(strict_types=1);

namespace Lahatre\Library\Services;

use Lahatre\Billing\Contracts\ProvidesFeatures;
use Lahatre\Billing\Registries\FeatureDefinition;
use Lahatre\Library\Enums\FileKind;

final class LibraryFeatures implements ProvidesFeatures
{
    public function definitions(): array
    {
        return [
            FeatureDefinition::choice('library.file_kinds', array_column(FileKind::cases(), 'value')),
        ];
    }
}
