<?php

declare(strict_types=1);

use App\Doctor\Diagnostics\BillingFeatureGrantsMatchCode;
use App\Doctor\Diagnostics\EnvironmentFileMatchesExample;
use App\Doctor\Diagnostics\GeoipDatabaseIsAvailable;
use App\Doctor\Diagnostics\HorizonServiceIsManaged;
use App\Doctor\Diagnostics\ReverbServiceIsManaged;
use App\Doctor\Diagnostics\SchedulerServiceIsManaged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lahatre\Billing\Models\PlanVersionFeature;
use Lahatre\Master\Models\Currency;
use Laravel\Doctor\Diagnostics\QueueConnectionIsAsynchronous;
use Laravel\Doctor\Diagnostics\ScheduledTasksRequireScheduler;
use Laravel\Doctor\Facades\Doctor;
use Laravel\Doctor\Results\Status;

uses(RefreshDatabase::class);

it('registers the GeoIP database diagnostic', function (): void {
    expect(Doctor::registered())
        ->toContain(BillingFeatureGrantsMatchCode::class)
        ->toContain(EnvironmentFileMatchesExample::class)
        ->toContain(GeoipDatabaseIsAvailable::class)
        ->toContain(HorizonServiceIsManaged::class)
        ->toContain(ReverbServiceIsManaged::class)
        ->toContain(SchedulerServiceIsManaged::class);

    expect(config('doctor.except'))
        ->toContain(QueueConnectionIsAsynchronous::class)
        ->toContain(ScheduledTasksRequireScheduler::class);
});

it('reports a missing GeoIP database', function (): void {
    config(['services.geoip.database' => storage_path('app/private/missing-GeoLite2-City.mmdb')]);

    $result = app(GeoipDatabaseIsAvailable::class)->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($result->code)->toBe('geoip-database-is-available.missing');
});

it('fails when a plan grants a feature the code no longer declares', function (): void {
    expect(app(BillingFeatureGrantsMatchCode::class)->check()->status)->toBe(Status::Pass);

    Currency::query()->firstOrCreate(['code' => 'USD'], ['symbol' => '$', 'precision' => 2]);
    PlanVersionFeature::factory()->create(['feature_key' => 'catalog.removed_feature']);
    $result = app(BillingFeatureGrantsMatchCode::class)->check();

    expect($result->status)->toBe(Status::Fail)
        ->and($result->code)->toBe('billing-feature-grants-match-code.mismatched');
});
