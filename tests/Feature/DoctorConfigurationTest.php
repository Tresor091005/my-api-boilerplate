<?php

declare(strict_types=1);

use App\Doctor\Diagnostics\EnvironmentFileMatchesExample;
use App\Doctor\Diagnostics\GeoipDatabaseIsAvailable;
use App\Doctor\Diagnostics\HorizonServiceIsManaged;
use App\Doctor\Diagnostics\ReverbServiceIsManaged;
use App\Doctor\Diagnostics\SchedulerServiceIsManaged;
use Laravel\Doctor\Diagnostics\QueueConnectionIsAsynchronous;
use Laravel\Doctor\Diagnostics\ScheduledTasksRequireScheduler;
use Laravel\Doctor\Facades\Doctor;
use Laravel\Doctor\Results\Status;

it('registers the GeoIP database diagnostic', function (): void {
    expect(Doctor::registered())
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
