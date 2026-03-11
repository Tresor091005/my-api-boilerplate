<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Concerns;

use Illuminate\Database\Eloquent\Model;

trait HasDataTransformer
{
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function toPrettyJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    public function toModel(string $model): Model
    {
        return new $model($this->toArray());
    }
}
