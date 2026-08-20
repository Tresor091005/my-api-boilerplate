<?php

declare(strict_types=1);

namespace Lahatre\Billing\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lahatre\Billing\CapacityResolverRegistry;
use Lahatre\Billing\Enums\FeatureType;
use Lahatre\Billing\Exceptions\BillingException;
use Lahatre\Billing\FeatureCatalog;
use Lahatre\Billing\Models\Feature;

class SyncFeatures extends Command
{
    protected $signature = 'features:sync';

    protected $description = 'Synchronize the code-driven billing feature catalog.';

    public function __construct(
        private readonly FeatureCatalog $catalog,
        private readonly CapacityResolverRegistry $resolvers,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $count = DB::transaction(function (): int {
                $definitions = $this->catalog->all();
                $databaseFeatures = Feature::withTrashed()->get()->keyBy('key');

                foreach ($databaseFeatures as $feature) {
                    if (!isset($definitions[$feature->key])) {
                        throw new BillingException(
                            "Feature [{$feature->key}] exists in the database but is missing from the code catalog."
                        );
                    }
                }

                foreach ($definitions as $definition) {
                    if ($definition->type === FeatureType::Capacity
                        && !$this->resolvers->has($definition->resolverKey)) {
                        throw new BillingException(
                            "Capacity resolver [{$definition->resolverKey}] for feature [{$definition->key}] is not registered."
                        );
                    }

                    $feature = $databaseFeatures->get($definition->key);

                    if ($feature === null) {
                        Feature::query()->create([
                            'key'          => $definition->key,
                            'name'         => $definition->name,
                            'type'         => $definition->type,
                            'resolver_key' => $definition->resolverKey,
                            'is_active'    => $definition->isActive,
                        ]);
                        continue;
                    }

                    if ($feature->type !== $definition->type
                        || $feature->resolver_key !== $definition->resolverKey) {
                        throw new BillingException(
                            "Stable definition for feature [{$definition->key}] cannot be changed through sync."
                        );
                    }

                    if ($feature->trashed()) {
                        $feature->restore();
                    }

                    $feature->update([
                        'name'      => $definition->name,
                        'is_active' => $definition->isActive,
                    ]);
                }

                return count($definitions);
            });
        } catch (BillingException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Synchronized {$count} billing feature(s).");

        return self::SUCCESS;
    }
}
