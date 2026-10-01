<?php

declare(strict_types=1);

namespace Lahatre\Iam\Integrations;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;
use Illuminate\Support\Facades\Cache;

final class SessionGeoLocator
{
    /** @return array{city: string|null, region: string|null, region_code: string|null, country: string|null, country_code: string|null}|null */
    public function locate(?string $ipAddress): ?array
    {
        $path = config('services.geoip.database');
        if (is_string($path)) {
            clearstatcache(true, $path);
        }
        if (!$ipAddress || !filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)
            || !is_string($path) || !is_readable($path)) {
            return null;
        }

        $key = 'iam:session-location:'.hash('sha256', $path.':'.filemtime($path).':'.$ipAddress);
        $result = Cache::remember($key, 86400, function () use ($path, $ipAddress): array {
            $reader = new Reader($path, ['en']);
            try {
                $record = $reader->city($ipAddress);

                return ['location' => [
                    'city'         => $record->city->name,
                    'region'       => $record->mostSpecificSubdivision->name,
                    'region_code'  => $record->mostSpecificSubdivision->isoCode,
                    'country'      => $record->country->name,
                    'country_code' => $record->country->isoCode,
                ]];
            } catch (AddressNotFoundException) {
                return ['location' => null];
            } finally {
                $reader->close();
            }
        });

        return $result['location'];
    }
}
