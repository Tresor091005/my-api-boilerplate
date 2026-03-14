<?php

declare(strict_types=1);

return [
    // Units
    'unit_duplicate_ratio'       => 'Duplicate ratios are not allowed in the same request.',
    'unit_base_required'         => 'A new unit group must have exactly one unit with ratio 1.',
    'unit_ratio_required'        => 'A new unit must give a ratio.',
    'unit_group_mismatch'        => 'The unit :unit_id does not belong to the group :group.',
    'unit_builtin_update'        => 'Built-in units cannot be modified.',
    'unit_base_deactivation'     => 'The base unit (ratio 1) of a group cannot be deactivated.',
    'unit_ratio_immutable'       => 'The ratio of an existing unit cannot be modified.',
    'unit_ratio_exists_in_group' => 'The ratio :ratio already exists in the group :group.',
    'unit_active_limit'          => 'A unit group cannot have more than :limit active units at the same time.',
];
