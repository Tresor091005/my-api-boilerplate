<?php

declare(strict_types=1);

namespace Lahatre\Master\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Lahatre\Master\Models\Label;
use Lahatre\Master\Services\LabelService;

/**
 * @phpstan-require-extends Model
 *
 * @mixin Model
 */
trait InteractsWithLabels
{
    /**
     * @param  array<int, string>  $labels
     */
    public function scopeWithAnyLabelsOfGroup(Builder $query, string $group, array $labels): Builder
    {
        $normalizedGroup = $this->normalizeLabelGroup($group);
        $normalizedLabels = $this->normalizeLabelValues($labels);
        if ($normalizedLabels->isEmpty()) {
            return $query;
        }

        $query = $this->scopeToLabelOrganization($query);

        return $query->whereHas('labels', function (Builder $labelQuery) use ($normalizedGroup, $normalizedLabels): void {
            $labelQuery->where('master_labels.group', $normalizedGroup);
            $labelQuery->whereIn('master_labels.value', $normalizedLabels);
        });
    }

    /**
     * @param  array<int, string>  $labels
     */
    public function scopeWithAllLabelsOfGroup(Builder $query, string $group, array $labels): Builder
    {
        $normalizedGroup = $this->normalizeLabelGroup($group);
        $normalizedLabels = $this->normalizeLabelValues($labels);
        if ($normalizedLabels->isEmpty()) {
            return $query;
        }

        $query = $this->scopeToLabelOrganization($query);

        return $query->whereHas(
            'labels',
            function (Builder $labelQuery) use ($normalizedGroup, $normalizedLabels): void {
                $labelQuery->where('master_labels.group', $normalizedGroup);
                $labelQuery->whereIn('master_labels.value', $normalizedLabels);
            },
            '>=',
            $normalizedLabels->count()
        );
    }

    /**
     * @param  array<int, string>  $labels
     */
    public function scopeWithoutLabelsOfGroup(Builder $query, string $group, array $labels): Builder
    {
        $normalizedGroup = $this->normalizeLabelGroup($group);
        $normalizedLabels = $this->normalizeLabelValues($labels);
        if ($normalizedLabels->isEmpty()) {
            return $query;
        }

        $query = $this->scopeToLabelOrganization($query);

        return $query->whereDoesntHave('labels', function (Builder $labelQuery) use ($normalizedGroup, $normalizedLabels): void {
            $labelQuery->where('master_labels.group', $normalizedGroup);
            $labelQuery->whereIn('master_labels.value', $normalizedLabels);
        });
    }

    /**
     * @return MorphToMany<Label, $this>
     */
    public function labels(): MorphToMany
    {
        $organizationId = currentOrganizationId();

        return $this->morphToMany(Label::class, 'labelable', 'master_labelables', 'labelable_id', 'label_id')
            ->wherePivot('organization_id', $organizationId)
            ->where('master_labels.organization_id', $organizationId)
            ->orderBy('master_labels.group')
            ->orderBy('master_labels.order_col')
            ->orderBy('master_labels.id');
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $labelsByGroup
     */
    public function attachLabels(array $labelsByGroup): void
    {
        app(LabelService::class)->attach($this, $labelsByGroup);
    }

    /**
     * @param  array<int, string>|Collection<int, string>  $labels
     */
    public function attachLabelsForGroup(string $group, Collection|array $labels): void
    {
        $this->attachLabels([$group => $labels]);
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $labelsByGroup
     */
    public function detachLabels(array $labelsByGroup): void
    {
        app(LabelService::class)->detach($this, $labelsByGroup);
    }

    /**
     * @param  array<int, string>|Collection<int, string>  $labels
     */
    public function detachLabelsForGroup(string $group, Collection|array $labels): void
    {
        $this->detachLabels([$group => $labels]);
    }

    /**
     * @param  array<string, array<int, string>|Collection<int, string>>  $labelsByGroup
     */
    public function syncLabels(array $labelsByGroup): void
    {
        app(LabelService::class)->sync($this, $labelsByGroup);
    }

    /**
     * @param  array<int, string>|Collection<int, string>  $labels
     */
    public function syncLabelsForGroup(string $group, Collection|array $labels): void
    {
        app(LabelService::class)->syncForGroup($this, $group, $labels);
    }

    /**
     * @param  array<int, string>  $labels
     * @return Collection<int, string>
     */
    protected function normalizeLabelValues(array $labels): Collection
    {
        /** @var Collection<int, string> $normalized */
        $normalized = collect($labels)
            ->map(fn (string $label): string => str($label)->normalize()->value())
            ->filter(fn (string $label): bool => $label !== '')
            ->unique()
            ->values();

        return $normalized;
    }

    protected function normalizeLabelGroup(string $group): string
    {
        return str($group)->normalize()->value();
    }

    protected function scopeToLabelOrganization(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('organization_id'), currentOrganizationId());
    }
}
