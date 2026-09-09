<?php

declare(strict_types=1);

namespace Lahatre\Service\Contracts;

use Lahatre\Service\Models\ServiceCommitment;

interface ServiceInterface
{
    public function createCommitment(string $serviceId, string $saleLineId): ServiceCommitment;

    public function confirmCommitment(ServiceCommitment $commitment): ServiceCommitment;

    public function closeCommitment(ServiceCommitment $commitment): ServiceCommitment;
}
