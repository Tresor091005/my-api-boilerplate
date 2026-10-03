<?php

declare(strict_types=1);

namespace App\Doctor\Diagnostics;

use GeoIp2\Database\Reader;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Message;
use MaxMind\Db\Reader\InvalidDatabaseException;
use UnexpectedValueException;

class GeoipDatabaseIsAvailable extends Diagnostic
{
    public string $name = 'GeoIP database is available';

    public string $group = 'storage';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'not-configured' => 'The GeoIP database path is not configured.',
            'missing'        => Message::make(
                summary: 'The GeoIP database file is missing.',
                remediation: 'Run `php artisan iam:geoip-update` to install the GeoLite2 City database.',
            ),
            'unreadable' => Message::make(
                summary: 'The GeoIP database file is not readable.',
                remediation: 'Check the file permissions and the storage directory.',
            ),
            'invalid' => Message::make(
                summary: 'The GeoIP database file is invalid or is not a City database.',
                remediation: 'Run `php artisan iam:geoip-update` to replace it with a valid database.',
            ),
            'available' => 'The GeoIP City database is present and valid.',
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $path = config('services.geoip.database');

        if (!is_string($path) || $path === '') {
            return $this->skip('not-configured');
        }

        if (!is_file($path)) {
            return $this->fail('missing');
        }

        if (!is_readable($path)) {
            return $this->fail('unreadable');
        }

        try {
            $reader = new Reader($path);

            try {
                $isCityDatabase = str_contains($reader->metadata()->databaseType, 'City');
            } finally {
                $reader->close();
            }
        } catch (InvalidDatabaseException|UnexpectedValueException) {
            return $this->fail('invalid');
        }

        return $isCityDatabase ? $this->pass('available') : $this->fail('invalid');
    }
}
