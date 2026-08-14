<?php

declare(strict_types=1);

return [
    // Units
    'unit_duplicate_ratio'           => 'Duplicate ratios are not allowed in the same request.',
    'unit_base_required'             => 'A new unit group must have exactly one unit with ratio 1.',
    'unit_ratio_required'            => 'A new unit must give a ratio.',
    'unit_group_mismatch'            => 'The unit :unit_id does not belong to the group :group.',
    'unit_builtin_update'            => 'Built-in units cannot be modified.',
    'unit_base_deactivation'         => 'The base unit (ratio 1) of a group cannot be deactivated.',
    'unit_ratio_immutable'           => 'The ratio of an existing unit cannot be modified.',
    'unit_ratio_exists_in_group'     => 'The ratio :ratio already exists in the group :group.',
    'unit_active_limit'              => 'A unit group cannot have more than :limit active units at the same time.',
    'conversion_unit_group_mismatch' => 'Cannot convert :from to :to: different unit groups.',

    // Tags
    'tag_not_found'                           => 'Some tags do not exist for type ":type": :names.',
    'tag_link_not_found'                      => 'Some tag links do not exist for type ":type": :names.',
    'organization_resolution_failed'          => 'Unable to resolve organization_id for tags operations.',
    'organization_mismatch'                   => 'The taggable model does not belong to the active organization.',
    'tag_in_use'                              => 'This tag is still attached to :count item(s).',
    'tag_reorder_mismatch'                    => 'The reorder list must contain exactly the active organization tags for the selected type.',
    'model_missing_interacts_with_tags_trait' => 'Model :model must use Lahatre\Master\Traits\InteractsWithTags to use tag operations.',
];
