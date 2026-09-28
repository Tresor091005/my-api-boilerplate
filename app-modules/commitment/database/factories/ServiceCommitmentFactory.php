<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\Service as CatalogService;
use Lahatre\Commitment\Enums\CommitmentState;
use Lahatre\Commitment\Models\ServiceCommitment;
use Lahatre\Customer\Models\Customer;
use Lahatre\Shared\Database\Factories\Concerns\ResolvesOrganizationId;

/** @extends Factory<ServiceCommitment> */
final class ServiceCommitmentFactory extends Factory
{
    use ResolvesOrganizationId;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $organizationId = $this->resolveOrganizationId();
        $catalogItem = CatalogItem::factory()->service()->create(['organization_id' => $organizationId]);
        $service = CatalogService::factory()->forCatalogItem($catalogItem)->create();
        $customer = Customer::factory()->create(['organization_id' => $organizationId]);

        return [
            'organization_id'  => $organizationId,
            'service_id'       => $service->id,
            'customer_id'      => $customer->id,
            'client_email'     => fake()->safeEmail(),
            'public_reference' => Str::upper(Str::random(16)),
            'state'            => CommitmentState::Draft->value,
        ];
    }
}
