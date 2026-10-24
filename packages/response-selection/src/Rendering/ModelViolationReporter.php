<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Rendering;

use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Throwable;

/**
 * When tolerated (in production by default), reading a column or relation that was not loaded is
 * reported instead of throwing, so users still get a response. A missing column renders null; a missing relation is queried.
 */
final class ModelViolationReporter
{
    /** @var array<string, true> */
    private array $reported = [];

    /**
     * Turns on this reporting, when the `tolerate_model_violations` config allows it.
     */
    public static function tolerate(): void
    {
        Model::handleMissingAttributeViolationUsing(
            static fn (Model $model, string $key, MissingAttributeException $violation): mixed => app(self::class)->report($violation),
        );
        Model::handleLazyLoadingViolationUsing(
            static fn (Model $model, string $relation, LazyLoadingViolationException $violation): mixed => app(self::class)->report($violation),
        );
    }

    /**
     * Reports a problem once per request, and returns null as the missing column's value.
     */
    public function report(Throwable $violation): null
    {
        $key = $violation::class.'|'.$violation->getMessage();

        if (!isset($this->reported[$key])) {
            $this->reported[$key] = true;
            report($violation);
        }

        return null;
    }
}
