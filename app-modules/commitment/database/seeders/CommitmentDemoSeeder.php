<?php

declare(strict_types=1);

namespace Lahatre\Commitment\Database\Seeders;

use Illuminate\Database\Seeder;
use Lahatre\Catalog\Models\Service;
use Lahatre\Commitment\Models\ServiceCommitment;
use Lahatre\Commitment\Models\ServiceCommitmentProposal;
use Lahatre\Customer\Models\Customer;
use Lahatre\Organization\Models\Organization;

final class CommitmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Organization::query()->get(['id']) as $organization) {
            $service = Service::query()->where('organization_id', $organization->id)->first();
            $customer = Customer::query()->where('organization_id', $organization->id)->first();
            if ($service === null || $customer === null) {
                continue;
            }
            $commitment = ServiceCommitment::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'public_reference' => 'DEMO'.substr(str_replace('-', '', $organization->id), 0, 12)],
                [
                    'service_id'   => $service->id,
                    'customer_id'  => $customer->id,
                    'client_email' => 'demo@example.test',
                    'state'        => 'draft',
                ],
            );
            ServiceCommitmentProposal::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'commitment_id' => $commitment->id, 'version' => 1],
                ['title' => $service->name, 'state' => 'draft'],
            );
        }
    }
}
