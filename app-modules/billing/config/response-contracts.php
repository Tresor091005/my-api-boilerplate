<?php

declare(strict_types=1);

$planContracts = array_fill_keys(
    [
        'lahatre.billing.plans.index',
        'lahatre.billing.plans.show',
        'lahatre.billing.plans.store',
        'lahatre.billing.plans.update',
    ],
    [],
);

return [
    ...$planContracts,
    'lahatre.billing.plans.destroy' => [],
];
