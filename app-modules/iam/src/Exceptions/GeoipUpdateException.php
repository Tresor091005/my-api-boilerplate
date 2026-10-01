<?php

declare(strict_types=1);

namespace Lahatre\Iam\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class GeoipUpdateException extends AssertionException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function configurationMissing(): self
    {
        return new self(__('iam::exceptions.geoip.configuration_missing'));
    }

    public static function downloadFailed(): self
    {
        return new self(__('iam::exceptions.geoip.download_failed'));
    }

    public static function invalidArchive(): self
    {
        return new self(__('iam::exceptions.geoip.invalid_archive'));
    }

    public static function storageUnavailable(): self
    {
        return new self(__('iam::exceptions.geoip.storage_unavailable'));
    }
}
