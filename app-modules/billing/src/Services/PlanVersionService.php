<?php

declare(strict_types=1);

namespace Lahatre\Billing\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lahatre\Billing\Data\PlanFeatureGrantsData;
use Lahatre\Billing\Data\PlanTermsData;
use Lahatre\Billing\Data\PlanVersionFilterData;
use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Billing\Enums\PlanVersionStatus;
use Lahatre\Billing\Exceptions\FeatureException;
use Lahatre\Billing\Exceptions\PlanVersionException;
use Lahatre\Billing\Models\Plan;
use Lahatre\Billing\Models\PlanVersion;
use Lahatre\Billing\Models\PlanVersionFeature;
use Lahatre\Billing\Models\Subscription;
use Lahatre\Billing\Models\Trial;
use Lahatre\Billing\Registries\FeatureDefinition;
use Lahatre\Billing\Registries\FeatureRegistry;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Shared\Data\MissingValue;

use function Lahatre\Shared\Data\withoutMissing;

final readonly class PlanVersionService
{
    public function __construct(
        private FeatureRegistry $features,
        private MasterInterface $master,
    ) {}

    /**
     * @throws PlanVersionException
     */
    public function updateDraft(Plan $plan, PlanTermsData $terms): Plan
    {
        $maxMonths = (int) config('billing.max_advance_months');

        if (!$terms->billingIntervalMonths instanceof MissingValue && ($terms->billingIntervalMonths < 1 || $terms->billingIntervalMonths > $maxMonths)) {
            throw PlanVersionException::billingIntervalInvalid($terms->billingIntervalMonths, $maxMonths);
        }

        if ($terms->price instanceof MissingValue !== $terms->currencyCode instanceof MissingValue) {
            throw PlanVersionException::priceWithoutCurrency();
        }

        if ($plan->is_trial && !$terms->price instanceof MissingValue && bccomp($terms->price, '0', 8) !== 0) {
            throw PlanVersionException::trialPlanNotFree($plan->getKey());
        }

        return DB::transaction(function () use ($plan, $terms): Plan {
            $draft = $this->openDraft($this->lockPlan($plan), $terms);
            $draft->update(withoutMissing([
                'price' => $terms->price instanceof MissingValue || $terms->currencyCode instanceof MissingValue
                    ? MissingValue::Instance
                    : $this->minorPrice($terms->price, $terms->currencyCode),
                'currency_code'           => $terms->currencyCode,
                'billing_interval_months' => $terms->billingIntervalMonths,
            ]));

            return $plan->freshWithStatus();
        });
    }

    /**
     * @throws FeatureException
     * @throws PlanVersionException
     */
    public function addFeatures(Plan $plan, PlanFeatureGrantsData $grants): Plan
    {
        $features = [];

        foreach ($grants->allowances as $featureKey => $allowance) {
            $features[$featureKey] = $this->features->get($featureKey);
            $this->assertGrantable($features[$featureKey], $allowance, $grants->values[$featureKey]);
        }

        $this->assertGrantedWithSameTypes($features);

        return DB::transaction(function () use ($plan, $grants, $features): Plan {
            $draft = $this->openDraft($this->lockPlan($plan));
            PlanVersionFeature::query()->upsert(
                array_map(fn (string $featureKey): array => [
                    'plan_version_id' => $draft->getKey(),
                    'feature_key'     => $featureKey,
                    'feature_type'    => $features[$featureKey]->type?->value,
                    'allowance'       => $grants->allowances[$featureKey],
                    'values'          => $grants->values[$featureKey] === null ? null : json_encode($grants->values[$featureKey]),
                    'deleted_at'      => null,
                ], array_keys($features)),
                ['plan_version_id', 'feature_key'],
                ['feature_type', 'allowance', 'values', 'deleted_at'],
            );

            return $plan->freshWithStatus();
        });
    }

    /**
     * @param  list<string>  $featureKeys
     *
     * @throws PlanVersionException
     */
    public function removeFeatures(Plan $plan, array $featureKeys): Plan
    {
        return DB::transaction(function () use ($plan, $featureKeys): Plan {
            $this->openDraft($this->lockPlan($plan))->features()->whereIn('feature_key', $featureKeys)->delete();

            return $plan->freshWithStatus();
        });
    }

    /**
     * @throws PlanVersionException
     */
    public function discardDraft(Plan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            $draft = $this->lockPlan($plan)->draftVersion()->first()
                ?? throw PlanVersionException::draftMissing($plan->getKey());

            $draft->features()->delete();
            $draft->delete();
        });
    }

    /**
     * @throws FeatureException
     * @throws PlanVersionException
     */
    public function publish(Plan $plan): Plan
    {
        return DB::transaction(function () use ($plan): Plan {
            $versions = $this->lockPlan($plan)->versions()
                ->whereIn('status', [PlanVersionStatus::Draft, PlanVersionStatus::Published])
                ->lockForUpdate()
                ->get();
            $draft = $versions->firstWhere('status', PlanVersionStatus::Draft)
                ?? throw PlanVersionException::draftMissing($plan->getKey());

            foreach ($draft->features()->pluck('feature_key') as $featureKey) {
                if ($this->features->get($featureKey)->isDisabled()) {
                    throw PlanVersionException::featureDisabled($featureKey);
                }
            }

            $versions->firstWhere('status', PlanVersionStatus::Published)?->update(['status' => PlanVersionStatus::Archived]);
            $draft->update(['status' => PlanVersionStatus::Published, 'published_at' => CarbonImmutable::now()]);

            if ($plan->is_trial) {
                $this->moveRunningTrials($plan, $draft);
            }

            return $plan->freshWithStatus();
        });
    }

    /**
     * @throws PlanVersionException
     */
    public function archive(Plan $plan): Plan
    {
        return DB::transaction(function () use ($plan): Plan {
            $current = $this->lockPlan($plan)->currentVersion()->lockForUpdate()->first()
                ?? throw PlanVersionException::notOnSale($plan->getKey());

            $current->update(['status' => PlanVersionStatus::Archived]);

            return $plan->freshWithStatus();
        });
    }

    /** @return CursorPaginator<int, PlanVersion> */
    public function getVersions(Plan $plan, PlanVersionFilterData $filters): CursorPaginator
    {
        return stableCursorPaginate(
            $plan->versions()
                ->where('status', '!=', PlanVersionStatus::Draft)
                ->withCount(['subscriptions as subscribers_count' => function (Builder $query): void {
                    /** @var Builder<Subscription> $query */
                    $query->givingAccessAt(CarbonImmutable::now());
                }]),
            $filters,
        );
    }

    /** Nobody paid for a trial, so running trials take the new version at once. */
    private function moveRunningTrials(Plan $trialPlan, PlanVersion $to): void
    {
        Trial::query()
            ->whereIn('plan_version_id', PlanVersion::withTrashed()->where('plan_id', $trialPlan->getKey())->select('id'))
            ->where('ends_at', '>', CarbonImmutable::now())
            ->update(['plan_version_id' => $to->getKey()]);
    }

    private function lockPlan(Plan $plan): Plan
    {
        return Plan::query()->lockForUpdate()->findOrFail($plan->getKey());
    }

    private function openDraft(Plan $plan, ?PlanTermsData $terms = null): PlanVersion
    {
        $versions = $plan->versions()
            ->whereIn('status', [PlanVersionStatus::Draft, PlanVersionStatus::Published])
            ->with('features')
            ->get();
        $draft = $versions->firstWhere('status', PlanVersionStatus::Draft);

        if ($draft !== null) {
            return $draft;
        }

        $current = $versions->firstWhere('status', PlanVersionStatus::Published);
        $version = (int) PlanVersion::withTrashed()->where('plan_id', $plan->getKey())->max('version') + 1;

        if ($current === null) {
            if ($terms === null || $terms->price instanceof MissingValue || $terms->currencyCode instanceof MissingValue
                || $terms->billingIntervalMonths instanceof MissingValue) {
                throw PlanVersionException::termsRequired($plan->getKey());
            }

            return $plan->versions()->create([
                'version'                 => $version,
                'status'                  => PlanVersionStatus::Draft,
                'price'                   => $this->minorPrice($terms->price, $terms->currencyCode),
                'currency_code'           => $terms->currencyCode,
                'billing_interval_months' => $terms->billingIntervalMonths,
            ]);
        }

        $draft = $plan->versions()->create([
            'version'                 => $version,
            'status'                  => PlanVersionStatus::Draft,
            'price'                   => $current->price,
            'currency_code'           => $current->currency_code,
            'billing_interval_months' => $current->billing_interval_months,
        ]);
        PlanVersionFeature::query()->fillAndInsert($current->features->map(fn (PlanVersionFeature $grant): array => [
            'plan_version_id' => $draft->getKey(),
            'feature_key'     => $grant->feature_key,
            'feature_type'    => $grant->feature_type,
            'allowance'       => $grant->allowance,
            'values'          => $grant->values,
        ])->all());

        return $draft;
    }

    private function minorPrice(string $price, string $currencyCode): int
    {
        $precision = $this->master->currency($currencyCode)->precision;
        $decimals = str_contains($price, '.') ? strlen(rtrim(explode('.', $price, 2)[1], '0')) : 0;

        if ($decimals > $precision) {
            throw PlanVersionException::pricePrecisionExceeded($price, $currencyCode, $precision);
        }

        return (int) $this->master->toMinor($price, $currencyCode);
    }

    /** @param  list<string>|null  $values */
    private function assertGrantable(FeatureDefinition $feature, ?int $allowance, ?array $values): void
    {
        if ($feature->isDisabled()) {
            throw PlanVersionException::featureDisabled($feature->key);
        }

        if ($allowance !== null && $allowance < 0) {
            throw PlanVersionException::negativeAllowance($feature->key, $allowance);
        }

        if ($feature->type === FeatureType::Boolean && $allowance !== null) {
            throw PlanVersionException::booleanFeatureAllowance($feature->key);
        }

        if ($feature->type === FeatureType::Choice && $allowance !== null) {
            throw PlanVersionException::choiceFeatureAllowance($feature->key);
        }

        if ($feature->type === FeatureType::Choice && $values === null) {
            throw PlanVersionException::valuesRequired($feature->key);
        }

        if ($feature->type !== FeatureType::Choice && $values !== null) {
            throw PlanVersionException::valuesNotChoice($feature->key);
        }

        $unknownValues = array_values(array_diff($values ?? [], $feature->options ?? []));

        if ($unknownValues !== []) {
            throw PlanVersionException::unknownValues($feature->key, $unknownValues);
        }
    }

    /** @param  array<string, FeatureDefinition>  $features */
    private function assertGrantedWithSameTypes(array $features): void
    {
        $grantedWithAnotherType = PlanVersionFeature::query()
            ->whereIn('feature_key', array_keys($features))
            ->get(['feature_key', 'feature_type'])
            ->first(fn (PlanVersionFeature $grant): bool => $grant->feature_type !== $features[$grant->feature_key]->type);

        if ($grantedWithAnotherType !== null) {
            throw PlanVersionException::featureTypeChanged($grantedWithAnotherType->feature_key);
        }
    }
}
