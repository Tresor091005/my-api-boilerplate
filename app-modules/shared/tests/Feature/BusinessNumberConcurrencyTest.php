<?php

declare(strict_types=1);

namespace Lahatre\Shared\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Shared\Services\BusinessNumberService as BusinessNumber;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->organizationId = Str::uuid7()->toString();
});

afterEach(function (): void {
    setPermissionsTeamId(null);
});

it('generates exactly one contiguous sequence for concurrent first-time calls', function (): void {
    $results = runConcurrentBusinessNumbers(
        organizationId: $this->organizationId,
        calls: array_fill(0, 24, ['key' => 'invoice']),
    );

    expect($results)->toHaveCount(24)
        ->and(array_unique($results))->toHaveCount(24)
        ->and(sequenceValues($results))->toEqual(range(1, 24));
});

/**
 * @param  array<int, array{key: string}>  $calls
 * @return array<int, string>
 */
function runConcurrentBusinessNumbers(string $organizationId, array $calls): array
{
    $directory = storage_path('framework/testing/business-number-'.Str::uuid7()->toString());
    mkdir($directory, 0700, true);
    $children = [];

    foreach ($calls as $index => $call) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new \RuntimeException('Unable to fork the business number test process.');
        }

        if ($pid === 0) {
            try {
                DB::disconnect();
                DB::purge();
                setPermissionsTeamId($organizationId);

                $result = BusinessNumber::next(
                    key: $call['key'],
                );
                file_put_contents($directory.'/'.$index, $result);
                exit(0);
            } catch (\Throwable $exception) {
                file_put_contents($directory.'/'.$index.'.error', $exception::class.': '.$exception->getMessage());
                exit(1);
            }
        }

        $children[$pid] = $index;
    }

    foreach (array_keys($children) as $pid) {
        pcntl_waitpid($pid, $status);

        if (pcntl_wifexited($status) === false || pcntl_wexitstatus($status) !== 0) {
            $index = $children[$pid];
            $error = @file_get_contents($directory.'/'.$index.'.error') ?: 'The child process failed without an error message.';
            cleanupConcurrencyDirectory($directory);

            throw new \RuntimeException($error);
        }
    }

    $results = [];
    foreach (array_keys($children) as $pid) {
        $index = $children[$pid];
        $results[] = (string) file_get_contents($directory.'/'.$index);
    }

    cleanupConcurrencyDirectory($directory);

    return $results;
}

/** @return array<int, int> */
function sequenceValues(array $results): array
{
    $values = array_map(static function (string $result): int {
        preg_match('/([0-9_]+)$/', $result, $matches);

        return (int) str_replace('_', '', $matches[1]);
    }, $results);

    sort($values);

    return $values;
}

function cleanupConcurrencyDirectory(string $directory): void
{
    foreach (glob($directory.'/*') ?: [] as $file) {
        unlink($file);
    }

    rmdir($directory);
}
