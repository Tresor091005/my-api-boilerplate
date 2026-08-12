<?php

declare(strict_types=1);

namespace Lahatre\Inventory\Services;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class TransactionErrorKeyMapper
{
    /**
     * @param  array<array-key, mixed>|null  $errorKeyMap
     */
    public function validate(?array $errorKeyMap): void
    {
        foreach ($errorKeyMap ?? [] as $source => $target) {
            if (!is_string($source) || !is_string($target)) {
                throw new InvalidArgumentException('Transaction error key mappings must use string keys and values.');
            }

            if (substr_count($source, '*') !== substr_count($target, '*')) {
                throw new InvalidArgumentException(sprintf(
                    "Transaction error key mapping '%s' must preserve the number of wildcards in '%s'.",
                    $source,
                    $target
                ));
            }
        }
    }

    /**
     * @param  array<array-key, mixed>|null  $errorKeyMap
     */
    public function mapValidationException(ValidationException $exception, ?array $errorKeyMap): ValidationException
    {
        if ($errorKeyMap === null || $errorKeyMap === []) {
            return $exception;
        }

        $this->validate($errorKeyMap);

        /** @var array<string, string> $errorKeyMap */
        $mappedMessages = [];
        foreach ($exception->errors() as $path => $messages) {
            $mappedPath = $this->mapPath($path, $errorKeyMap);
            $mappedMessages[$mappedPath] = array_merge($mappedMessages[$mappedPath] ?? [], $messages);
        }

        return ValidationException::withMessages($mappedMessages);
    }

    /**
     * @param  array<string, string>  $errorKeyMap
     */
    protected function mapPath(string $path, array $errorKeyMap): string
    {
        $bestMatch = null;
        $bestScore = null;

        foreach ($errorKeyMap as $source => $target) {
            $match = $this->matchPath($path, $source, $target);
            if ($match === null) {
                continue;
            }

            if ($bestScore === null || $match['score'] > $bestScore) {
                $bestMatch = $match['path'];
                $bestScore = $match['score'];
            }
        }

        return $bestMatch ?? $path;
    }

    /**
     * @return array{path: string, score: array<int, int>}|null
     */
    protected function matchPath(string $path, string $source, string $target): ?array
    {
        $pathSegments = explode('.', $path);
        $sourceSegments = explode('.', $source);
        $targetSegments = explode('.', $target);
        $isStockIdsParentMapping = end($sourceSegments) === 'stock_ids'
            && count($pathSegments) > count($sourceSegments);

        if (!$isStockIdsParentMapping && count($pathSegments) !== count($sourceSegments)) {
            return null;
        }

        $wildcardValues = [];
        foreach ($sourceSegments as $index => $sourceSegment) {
            $pathSegment = $pathSegments[$index] ?? null;
            if ($sourceSegment === '*') {
                $wildcardValues[] = $pathSegment;
                continue;
            }

            if ($sourceSegment !== $pathSegment) {
                return null;
            }
        }

        $wildcardIndex = 0;
        $mappedSegments = array_map(function (string $targetSegment) use (&$wildcardIndex, $wildcardValues): string {
            if ($targetSegment !== '*') {
                return $targetSegment;
            }

            return (string) ($wildcardValues[$wildcardIndex++] ?? '*');
        }, $targetSegments);

        return [
            'path'  => implode('.', $mappedSegments),
            'score' => [
                $isStockIdsParentMapping ? 0 : 1,
                count($sourceSegments),
                -substr_count($source, '*'),
            ],
        ];
    }
}
