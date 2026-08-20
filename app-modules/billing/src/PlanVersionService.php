<?php

declare(strict_types=1);

namespace Lahatre\Billing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Billing\Enums\PlanVersionStatus;
use Lahatre\Billing\Exceptions\BillingException;
use Lahatre\Billing\Models\Feature;
use Lahatre\Billing\Models\Plan;
use Lahatre\Billing\Models\PlanVersion;
use Lahatre\Billing\Models\PlanVersionFeature;
use Lahatre\Billing\Models\Subscription;

final class PlanVersionService
{
    /** @param array<string, mixed> $attributes */
    public function createDraft(Plan $plan, array $attributes): PlanVersion
    {
        $this->assertDraftAttributes($attributes, true);

        return DB::transaction(function () use ($plan, $attributes): PlanVersion {
            $lastVersion = PlanVersion::withTrashed()
                ->where('plan_id', $plan->getKey())
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();
            $version = ((int) ($lastVersion->version ?? 0)) + 1;

            return $plan->versions()->create([
                ...$attributes,
                'version'      => $version,
                'status'       => PlanVersionStatus::Draft,
                'published_at' => null,
            ]);
        });
    }

    public function publish(PlanVersion $version): PlanVersion
    {
        return DB::transaction(function () use ($version): PlanVersion {
            $version = PlanVersion::query()->lockForUpdate()->findOrFail($version->getKey());
            $this->assertNotReferenced($version);

            if (!in_array($version->status, [PlanVersionStatus::Draft, PlanVersionStatus::Archived], true)) {
                throw new BillingException('Only draft or archived plan versions can be published.');
            }

            $publishedExists = PlanVersion::query()
                ->where('plan_id', $version->plan_id)
                ->where('status', PlanVersionStatus::Published)
                ->where('id', '!=', $version->getKey())
                ->lockForUpdate()
                ->exists();

            if ($publishedExists) {
                throw new BillingException('A plan can have only one published version.');
            }

            $version->update([
                'status'       => PlanVersionStatus::Published,
                'published_at' => CarbonImmutable::now(),
            ]);

            return $version->refresh();
        });
    }

    public function archive(PlanVersion $version): PlanVersion
    {
        if ($version->status !== PlanVersionStatus::Published) {
            throw new BillingException('Only published plan versions can be archived.');
        }

        $version->update(['status' => PlanVersionStatus::Archived]);

        return $version->refresh();
    }

    public function unpublish(PlanVersion $version): PlanVersion
    {
        return DB::transaction(function () use ($version): PlanVersion {
            $version = PlanVersion::query()->lockForUpdate()->findOrFail($version->getKey());

            if ($version->status !== PlanVersionStatus::Published) {
                throw new BillingException('Only published plan versions can be unpublished.');
            }

            $this->assertNotReferenced($version);
            $version->update([
                'status'       => PlanVersionStatus::Draft,
                'published_at' => null,
            ]);

            return $version->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateDraft(PlanVersion $version, array $attributes): PlanVersion
    {
        $this->assertDraftAttributes($attributes, false);

        return DB::transaction(function () use ($version, $attributes): PlanVersion {
            $version = $this->lockVersion($version);
            $this->assertMutable($version);
            $version->update($attributes);

            return $version->refresh();
        });
    }

    public function grantFeature(PlanVersion $version, Feature $feature, ?int $allowance = null): PlanVersionFeature
    {
        $version = $this->lockVersion($version);
        $this->assertMutable($version);
        $this->assertAllowance($feature, $allowance);

        $existing = PlanVersionFeature::withTrashed()
            ->where('plan_version_id', $version->getKey())
            ->where('feature_id', $feature->getKey())
            ->first();

        if ($existing !== null) {
            $existing->restore();
            $existing->update(['allowance' => $allowance]);

            return $existing->refresh();
        }

        return $version->features()->create(['feature_id' => $feature->getKey(), 'allowance' => $allowance]);
    }

    public function updateFeatureAllowance(PlanVersionFeature $planVersionFeature, ?int $allowance): PlanVersionFeature
    {
        $version = $this->lockVersion($planVersionFeature->planVersion);
        $this->assertMutable($version);
        $this->assertAllowance($planVersionFeature->feature, $allowance);
        $planVersionFeature->update(['allowance' => $allowance]);

        return $planVersionFeature->refresh();
    }

    public function revokeFeature(PlanVersionFeature $planVersionFeature): void
    {
        $version = $this->lockVersion($planVersionFeature->planVersion);
        $this->assertMutable($version);
        $planVersionFeature->delete();
    }

    private function lockVersion(PlanVersion $version): PlanVersion
    {
        return PlanVersion::query()->with('plan')->lockForUpdate()->findOrFail($version->getKey());
    }

    private function assertMutable(PlanVersion $version): void
    {
        if ($version->status !== PlanVersionStatus::Draft) {
            throw new BillingException('Published and archived plan versions are immutable.');
        }

        $this->assertNotReferenced($version);
    }

    private function assertNotReferenced(PlanVersion $version): void
    {
        if (Subscription::withTrashed()->where('plan_version_id', $version->getKey())->exists()) {
            throw new BillingException('A plan version referenced by a subscription is permanently immutable.');
        }
    }

    private function assertAllowance(Feature $feature, ?int $allowance): void
    {
        if ($allowance !== null && $allowance < 0) {
            throw new BillingException('Feature allowance cannot be negative.');
        }

        if ($feature->type === FeatureType::Boolean && $allowance !== null) {
            throw new BillingException('Boolean features cannot have an allowance.');
        }
    }

    /** @param array<string, mixed> $attributes */
    private function assertDraftAttributes(array $attributes, bool $required): void
    {
        if ($required && !array_key_exists('billing_interval', $attributes)) {
            throw new BillingException('A plan version requires a billing interval in months.');
        }

        if (array_key_exists('billing_interval', $attributes)
            && (!is_int($attributes['billing_interval']) || $attributes['billing_interval'] < 1)) {
            throw new BillingException('A billing interval must be a positive number of months.');
        }
    }
}
