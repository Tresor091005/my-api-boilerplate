<?php

declare(strict_types=1);

namespace Lahatre\Iam\Console\Commands;

use Illuminate\Console\Command;
use Lahatre\Iam\Exceptions\GeoipUpdateException;
use Lahatre\Iam\Integrations\GeoipDatabaseUpdater;

class UpdateGeoipDatabase extends Command
{
    protected $signature = 'iam:geoip-update';

    public function getDescription(): string
    {
        return __('iam::console.geoip.description');
    }

    public function handle(GeoipDatabaseUpdater $updater): int
    {
        try {
            $result = $updater->update();
        } catch (GeoipUpdateException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(match ($result) {
            'updated' => __('iam::console.geoip.updated'),
            'locked'  => __('iam::console.geoip.locked'),
            default   => __('iam::console.geoip.unchanged'),
        });

        return self::SUCCESS;
    }
}
