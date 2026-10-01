<?php

declare(strict_types=1);

namespace Lahatre\Iam\Integrations;

use GeoIp2\Database\Reader;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lahatre\Iam\Exceptions\GeoipUpdateException;
use MaxMind\Db\Reader\InvalidDatabaseException;
use PharData;
use RecursiveIteratorIterator;
use UnexpectedValueException;

final class GeoipDatabaseUpdater
{
    private const DOWNLOAD_URL = 'https://download.maxmind.com/geoip/databases/GeoLite2-City/download?suffix=tar.gz';

    private const MAX_ARCHIVE_BYTES = 134_217_728;

    private const MAX_UNPACKED_BYTES = 536_870_912;

    public function isConfigured(): bool
    {
        return is_string(config('services.geoip.database')) && config('services.geoip.database') !== ''
            && is_scalar(config('services.geoip.account_id')) && (string) config('services.geoip.account_id') !== ''
            && is_string(config('services.geoip.license_key')) && config('services.geoip.license_key') !== '';
    }

    /**
     * Install on first use or atomically replace a validated database when MaxMind publishes a newer archive.
     * A local file lock protects the target, including independent CLI and scheduler invocations.
     * Temporary downloads are always removed; the existing database survives every failed update.
     *
     *
     * @throws GeoipUpdateException
     *
     * @return 'updated'|'unchanged'|'locked'
     */
    public function update(): string
    {
        if (!$this->isConfigured()) {
            throw GeoipUpdateException::configurationMissing();
        }

        $path = (string) config('services.geoip.database');
        $directory = dirname($path);
        $this->ensureDirectoryExists($directory, 0750);
        $lock = @fopen($path.'.lock', 'c');
        if ($lock === false) {
            throw GeoipUpdateException::storageUnavailable();
        }

        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) {
                return 'locked';
            }

            return $this->downloadAndInstall($path);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return 'updated'|'unchanged' */
    private function downloadAndInstall(string $path): string
    {
        $temporaryDirectory = dirname($path).'/.geoip-update-'.Str::uuid7();
        $this->ensureDirectoryExists($temporaryDirectory, 0700);
        try {
            $client = Http::withBasicAuth((string) config('services.geoip.account_id'), config('services.geoip.license_key'))
                ->connectTimeout(10)->timeout(180)
                ->withOptions(['allow_redirects' => ['max' => 3, 'protocols' => ['https']]]);
            try {
                $head = $client->head(self::DOWNLOAD_URL);
                if (!$head->successful()) {
                    throw GeoipUpdateException::downloadFailed();
                }
                $modifiedAt = strtotime($head->header('Last-Modified'));
                clearstatcache(true, $path);
                if ($modifiedAt !== false && is_file($path) && filemtime($path) >= $modifiedAt && $this->isValidDatabase($path)) {
                    return 'unchanged';
                }

                $archivePath = $temporaryDirectory.'/database.tar.gz';
                $response = $client->withOptions([
                    'progress' => function (float $total, float $downloaded): void {
                        if ($total > self::MAX_ARCHIVE_BYTES || $downloaded > self::MAX_ARCHIVE_BYTES) {
                            throw GeoipUpdateException::invalidArchive();
                        }
                    },
                ])->sink($archivePath)->get(self::DOWNLOAD_URL);
                if (!$response->successful()) {
                    throw GeoipUpdateException::downloadFailed();
                }
            } catch (ConnectionException) {
                throw GeoipUpdateException::downloadFailed();
            }

            $candidate = $this->extractDatabase($archivePath, $temporaryDirectory);
            if (!$this->isValidDatabase($candidate)) {
                throw GeoipUpdateException::invalidArchive();
            }
            $responseModifiedAt = strtotime($response->header('Last-Modified'));
            if ($responseModifiedAt !== false) {
                if (!@touch($candidate, $responseModifiedAt)) {
                    throw GeoipUpdateException::storageUnavailable();
                }
            } elseif ($modifiedAt !== false) {
                if (!@touch($candidate, $modifiedAt)) {
                    throw GeoipUpdateException::storageUnavailable();
                }
            }
            if (!@rename($candidate, $path)) {
                throw GeoipUpdateException::storageUnavailable();
            }
            clearstatcache(true, $path);

            return 'updated';
        } finally {
            File::deleteDirectory($temporaryDirectory);
        }
    }

    private function extractDatabase(string $archivePath, string $directory): string
    {
        if (!is_file($archivePath) || filesize($archivePath) > self::MAX_ARCHIVE_BYTES) {
            throw GeoipUpdateException::invalidArchive();
        }

        $tarPath = $directory.'/database.tar';
        $source = @gzopen($archivePath, 'rb');
        $target = @fopen($tarPath, 'wb');
        if ($source === false || $target === false) {
            if (is_resource($source)) {
                gzclose($source);
            }
            if (is_resource($target)) {
                fclose($target);
            }
            throw GeoipUpdateException::invalidArchive();
        }
        try {
            $size = stream_copy_to_stream($source, $target, self::MAX_UNPACKED_BYTES + 1);
            if ($size === false || $size > self::MAX_UNPACKED_BYTES) {
                throw GeoipUpdateException::invalidArchive();
            }
        } finally {
            gzclose($source);
            fclose($target);
        }

        try {
            $archive = new PharData($tarPath);
            foreach (new RecursiveIteratorIterator($archive) as $entry) {
                if ($entry->getFilename() !== 'GeoLite2-City.mmdb' || !$entry->isFile() || $entry->isLink()) {
                    continue;
                }
                $candidate = $directory.'/GeoLite2-City.mmdb';
                if (!@copy($entry->getPathname(), $candidate)) {
                    throw GeoipUpdateException::storageUnavailable();
                }

                return $candidate;
            }
        } catch (UnexpectedValueException) {
            throw GeoipUpdateException::invalidArchive();
        }

        throw GeoipUpdateException::invalidArchive();
    }

    private function isValidDatabase(string $path): bool
    {
        try {
            $reader = new Reader($path);
            try {
                return str_contains($reader->metadata()->databaseType, 'City');
            } finally {
                $reader->close();
            }
        } catch (InvalidDatabaseException|UnexpectedValueException) {
            return false;
        }
    }

    private function ensureDirectoryExists(string $directory, int $permissions): void
    {
        if (!is_dir($directory) && !@mkdir($directory, $permissions, true) && !is_dir($directory)) {
            throw GeoipUpdateException::storageUnavailable();
        }
    }
}
