<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/codex-geoip-'.Str::uuid7();
    File::ensureDirectoryExists($this->directory);
    $this->databasePath = $this->directory.'/GeoLite2-City.mmdb';
    config([
        'services.geoip.database'    => $this->databasePath,
        'services.geoip.account_id'  => '123456',
        'services.geoip.license_key' => 'test-only-license-key',
    ]);
    Http::preventStrayRequests();
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

/** Build a real compressed archive around the licensed MaxMind test fixture. */
function geoipTestArchive(string $directory, bool $validDatabase = true, string $entryName = 'GeoLite2-City_20261001/GeoLite2-City.mmdb'): string
{
    $tar = new PharData($directory.'/fixture.tar');
    if ($validDatabase) {
        $tar->addFile(base_path('tests/Fixtures/GeoIP/GeoIP2-City-Test.mmdb'), $entryName);
    } else {
        $tar->addFromString($entryName, 'invalid database contents');
    }
    $tar->compress(Phar::GZ);

    return file_get_contents($directory.'/fixture.tar.gz');
}

it('installs and validates a streamed City database with credentials in Basic authentication', function (): void {
    $archive = geoipTestArchive($this->directory);
    $modified = 'Thu, 01 Oct 2026 03:00:00 GMT';
    Http::fake(fn (Request $request) => Http::response($request->method() === 'HEAD' ? '' : $archive, 200, ['Last-Modified' => $modified]));
    currentTestCase()->artisan('iam:geoip-update')->expectsOutput(__('iam::console.geoip.updated'))->assertSuccessful();
    expect(file_get_contents($this->databasePath))->toBe(file_get_contents(base_path('tests/Fixtures/GeoIP/GeoIP2-City-Test.mmdb')))
        ->and(filemtime($this->databasePath))->toBe(strtotime($modified))
        ->and(glob($this->directory.'/.geoip-update-*'))->toBe([]);
    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && $request->url() === 'https://download.maxmind.com/geoip/databases/GeoLite2-City/download?suffix=tar.gz'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('123456:test-only-license-key')));
});

it('checks the release date without downloading an unchanged valid database', function (): void {
    copy(base_path('tests/Fixtures/GeoIP/GeoIP2-City-Test.mmdb'), $this->databasePath);
    touch($this->databasePath, strtotime('2026-10-01T03:00:00Z'));
    Http::fake(fn () => Http::response('', 200, ['Last-Modified' => 'Thu, 01 Oct 2026 03:00:00 GMT']));
    currentTestCase()->artisan('iam:geoip-update')->expectsOutput(__('iam::console.geoip.unchanged'))->assertSuccessful();
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'HEAD');
});

it('replaces an older database atomically and clears temporary files', function (): void {
    copy(base_path('tests/Fixtures/GeoIP/GeoIP2-City-Test.mmdb'), $this->databasePath);
    touch($this->databasePath, strtotime('2026-09-01T00:00:00Z'));
    $archive = geoipTestArchive($this->directory);
    Http::fake(fn (Request $request) => Http::response($request->method() === 'HEAD' ? '' : $archive, 200, ['Last-Modified' => 'Thu, 01 Oct 2026 03:00:00 GMT']));
    currentTestCase()->artisan('iam:geoip-update')->assertSuccessful();
    expect(filemtime($this->databasePath))->toBe(strtotime('2026-10-01T03:00:00Z'))
        ->and(glob($this->directory.'/.geoip-update-*'))->toBe([]);
});

it('repairs a corrupted local database even when its file modification time is recent', function (): void {
    file_put_contents($this->databasePath, 'corrupted database');
    touch($this->databasePath, strtotime('2026-10-02T00:00:00Z'));
    $archive = geoipTestArchive($this->directory);
    Http::fake(fn (Request $request) => Http::response($request->method() === 'HEAD' ? '' : $archive, 200, ['Last-Modified' => 'Thu, 01 Oct 2026 03:00:00 GMT']));
    currentTestCase()->artisan('iam:geoip-update')->expectsOutput(__('iam::console.geoip.updated'))->assertSuccessful();
    expect(filesize($this->databasePath))->toBeGreaterThan(1000);
});

it('keeps the current database when MaxMind rejects the request and hides response contents', function (int $status): void {
    copy(base_path('tests/Fixtures/GeoIP/GeoIP2-City-Test.mmdb'), $this->databasePath);
    $before = file_get_contents($this->databasePath);
    Http::fake(fn () => Http::response('test-only-license-key', $status));
    currentTestCase()->artisan('iam:geoip-update')->expectsOutput(__('iam::exceptions.geoip.download_failed'))->assertFailed();
    expect(file_get_contents($this->databasePath))->toBe($before)
        ->and(glob($this->directory.'/.geoip-update-*'))->toBe([]);
})->with([401, 429, 503]);

it('preserves the current database and releases its lock after a network failure', function (): void {
    copy(base_path('tests/Fixtures/GeoIP/GeoIP2-City-Test.mmdb'), $this->databasePath);
    Http::fake(['*' => Http::failedConnection()]);
    currentTestCase()->artisan('iam:geoip-update')->expectsOutput(__('iam::exceptions.geoip.download_failed'))->assertFailed();
    expect(file_get_contents($this->databasePath))->toBe(file_get_contents(base_path('tests/Fixtures/GeoIP/GeoIP2-City-Test.mmdb')))
        ->and(glob($this->directory.'/.geoip-update-*'))->toBe([]);
    $lock = fopen($this->databasePath.'.lock', 'c');
    expect(flock($lock, LOCK_EX | LOCK_NB))->toBeTrue();
    fclose($lock);
});

it('keeps the existing database when downloading the newer release fails', function (): void {
    copy(base_path('tests/Fixtures/GeoIP/GeoIP2-City-Test.mmdb'), $this->databasePath);
    touch($this->databasePath, strtotime('2026-09-01T00:00:00Z'));
    Http::fake(fn (Request $request) => $request->method() === 'HEAD'
        ? Http::response('', 200, ['Last-Modified' => 'Thu, 01 Oct 2026 03:00:00 GMT'])
        : Http::response('unavailable', 503));
    currentTestCase()->artisan('iam:geoip-update')->expectsOutput(__('iam::exceptions.geoip.download_failed'))->assertFailed();
    expect(file_get_contents($this->databasePath))->toBe(file_get_contents(base_path('tests/Fixtures/GeoIP/GeoIP2-City-Test.mmdb')))
        ->and(glob($this->directory.'/.geoip-update-*'))->toBe([]);
    Http::assertSentCount(2);
});

it('reports an unusable destination before sending credentials to MaxMind', function (): void {
    file_put_contents($this->directory.'/blocked', 'a file cannot contain a database');
    config(['services.geoip.database' => $this->directory.'/blocked/GeoLite2-City.mmdb']);
    Http::fake();
    currentTestCase()->artisan('iam:geoip-update')->expectsOutput(__('iam::exceptions.geoip.storage_unavailable'))->assertFailed();
    Http::assertNothingSent();
});

it('rejects invalid or incomplete archives without replacing the existing database', function (string $archiveType): void {
    copy(base_path('tests/Fixtures/GeoIP/GeoIP2-City-Test.mmdb'), $this->databasePath);
    touch($this->databasePath, strtotime('2026-09-01T00:00:00Z'));
    $before = file_get_contents($this->databasePath);
    $archive = match ($archiveType) {
        'corrupt_database' => geoipTestArchive($this->directory, false),
        'missing_database' => geoipTestArchive($this->directory, true, 'GeoLite2-City_20261001/OTHER.mmdb'),
        default            => 'not an archive',
    };
    Http::fake(fn (Request $request) => Http::response($request->method() === 'HEAD' ? '' : $archive, 200, ['Last-Modified' => 'Thu, 01 Oct 2026 03:00:00 GMT']));
    currentTestCase()->artisan('iam:geoip-update')->expectsOutput(__('iam::exceptions.geoip.invalid_archive'))->assertFailed();
    expect(file_get_contents($this->databasePath))->toBe($before)
        ->and(glob($this->directory.'/.geoip-update-*'))->toBe([]);
})->with(['corrupt_database', 'missing_database', 'invalid_archive']);

it('does not start a second update while the database target is locked', function (): void {
    $lock = fopen($this->databasePath.'.lock', 'c');
    flock($lock, LOCK_EX);
    Http::fake();
    try {
        currentTestCase()->artisan('iam:geoip-update')->expectsOutput(__('iam::console.geoip.locked'))->assertSuccessful();
        Http::assertNothingSent();
    } finally {
        fclose($lock);
    }
});

it('requires both download credentials before making an HTTP request', function (string $missing): void {
    config(['services.geoip.'.$missing => '']);
    Http::fake();
    currentTestCase()->artisan('iam:geoip-update')->expectsOutput(__('iam::exceptions.geoip.configuration_missing'))->assertFailed();
    Http::assertNothingSent();
})->with(['account_id', 'license_key']);

it('registers a daily update which only runs with configured download credentials', function (): void {
    $event = collect(app(Schedule::class)->events())->first(fn ($event): bool => str_contains($event->command ?? '', 'iam:geoip-update'));
    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 3 * * *')
        ->and($event->runInBackground)->toBeTrue()
        ->and($event->filtersPass(app()))->toBeTrue();
    config(['services.geoip.license_key' => null]);
    expect($event->filtersPass(app()))->toBeFalse();
});
