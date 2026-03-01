<?php

declare(strict_types=1);

return [
    'category_has_children'                => 'Cannot delete a category that has children. Please delete or reassign the children first.',
    'category_cannot_be_descendant_parent' => 'A category cannot have one of its descendants or itself as its parent.',
    'tag_in_use'                           => 'Cannot delete a tag that is currently in use by products: :products.',

    // Units
    'unit_duplicate_ratio'       => 'Duplicate ratios are not allowed in the same request.',
    'unit_base_required'         => 'A new unit group must have exactly one unit with ratio 1.',
    'unit_group_mismatch'        => 'The unit :unit_id does not belong to the group :group.',
    'unit_builtin_update'        => 'Built-in units cannot be modified.',
    'unit_base_deactivation'     => 'The base unit (ratio 1) of a group cannot be deactivated.',
    'unit_ratio_immutable'       => 'The ratio of an existing unit cannot be modified.',
    'unit_ratio_exists_in_group' => 'The ratio :ratio already exists in the group :group.',
    'unit_active_limit'          => 'A unit group cannot have more than :limit active units at the same time.',
];
