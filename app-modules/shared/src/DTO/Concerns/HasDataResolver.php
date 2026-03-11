<?php

declare(strict_types=1);

namespace Lahatre\Shared\DTO\Concerns;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait HasDataResolver
{
    /**
     * @throws ValidationException
     */
    public static function fromJson(string $json): static
    {
        $jsonDecoded = json_decode($json, true);
        if (!is_array($jsonDecoded)) {
            throw new \RuntimeException('Invalid JSON provided.');
        }

        return new static($jsonDecoded);
    }

    /**
     * @throws ValidationException
     */
    public static function fromArray(array $array): static
    {
        return new static($array);
    }

    /**
     * @throws ValidationException
     */
    public static function fromRequest(Request $request): static
    {
        return new static($request->all());
    }

    /**
     * @throws ValidationException
     */
    public static function fromModel(Model $model): static
    {
        return new static($model->toArray());
    }

    /**
     * @throws ValidationException
     */
    public static function fromCommandArguments(Command $command): static
    {
        return new static(self::filterArguments($command->arguments()));
    }

    /**
     * @throws ValidationException
     */
    public static function fromCommandOptions(Command $command): static
    {
        return new static($command->options());
    }

    /**
     * @throws ValidationException
     */
    public static function fromCommand(Command $command): static
    {
        return new static(array_merge(self::filterArguments($command->arguments()), $command->options()));
    }

    /**
     * Create a DTO instance for an update operation.
     */
    public static function forUpdate(Request $request, Model $model): static
    {
        $exclude = array_merge(
            $model->getHidden(),
            ['created_at', 'updated_at', 'deleted_at']
        );

        $modelData = collect($model->getAttributes())
            ->except($exclude)
            ->toArray();

        $mergedData = array_merge($modelData, $request->all());

        return new static($mergedData, $model->getKey());
    }

    private static function filterArguments(array $arguments): array
    {
        $result = [];
        foreach ($arguments as $key => $value) {
            if (!is_numeric($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
