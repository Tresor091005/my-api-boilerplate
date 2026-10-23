<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection;

use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Throwable;

/**
 * Production policy for the protections the engine turns on while it loads and renders, the same
 * as Lahatre's `Model::shouldBeStrict(!app()->isProduction())`: locally and in tests a column read
 * without being loaded, or a relation read without being loaded, throws; in production it is
 * reported once per request and model class and key, then let through. A missing attribute renders
 * `null`; a lazy-loaded relation still queries, so its data stays right. Registered scoped, so the
 * reported keys reset with every request.
 */
final class ModelViolationReporter
{
    /** @var array<string, true> */
    private array $reported = [];

    /**
     * Installs the handlers. Called in production only: Eloquent applies them to every model, and
     * outside the engine production leaves both protections off.
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
     * Reports a violation the first time this request meets it, and returns the value an absent
     * attribute renders.
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
