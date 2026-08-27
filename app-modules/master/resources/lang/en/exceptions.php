<?php

declare(strict_types=1);

return [
    // Units
    'unit_duplicate_ratio'           => 'Duplicate ratios are not allowed in the same request.',
    'unit_base_required'             => 'A new unit group must have exactly one unit with ratio 1.',
    'unit_ratio_required'            => 'A new unit must give a ratio.',
    'unit_ratio_exceeds_maximum'     => 'Unit ratio :ratio exceeds the maximum custom ratio of :maximum.',
    'unit_group_mismatch'            => 'The unit :unit_id does not belong to the group :group.',
    'unit_builtin_update'            => 'Built-in units cannot be modified.',
    'unit_base_deactivation'         => 'The base unit (ratio 1) of a group cannot be deactivated.',
    'unit_ratio_immutable'           => 'The ratio of an existing unit cannot be modified.',
    'unit_ratio_exists_in_group'     => 'The ratio :ratio already exists in the group :group.',
    'unit_active_limit'              => 'A unit group cannot have more than :limit active units at the same time.',
    'conversion_unit_group_mismatch' => 'Cannot convert :from to :to: different unit groups.',

    // Labels
    'label_not_found'                            => 'Some labels do not exist for group ":group": :values.',
    'label_link_not_found'                       => 'Some label links do not exist for group ":group": :values.',
    'organization_resolution_failed'             => 'Unable to resolve organization_id for labels operations.',
    'organization_mismatch'                      => 'The labelable model does not belong to the active organization.',
    'label_in_use'                               => 'This label is still attached to :count item(s).',
    'label_reorder_mismatch'                     => 'The reorder list must contain exactly the active organization labels for the selected group.',
    'model_missing_interacts_with_labels_trait'  => 'Model :model must use Lahatre\Master\Traits\InteractsWithLabels to use label operations.',
    'note_root_has_replies'                      => 'A note thread root cannot be deleted while it has replies.',
    'note_replies_cannot_expire'                 => 'Replies cannot have an expiration date.',
    'note_root_cannot_expire_with_replies'       => 'A note thread root cannot have an expiration date while it has replies.',
    'note_mentioned_visibility_requires_members' => 'A mentioned note must have at least one member mention.',
    'note_visibility_cannot_be_changed'          => 'A note visibility cannot be changed after it becomes collective.',
    'note_expired_cannot_receive_replies'        => 'An expired note cannot receive replies.',
    'note_expired_cannot_be_pinned'              => 'An expired note cannot be pinned.',
    'note_mentions_require_mentioned_visibility' => 'Member mentions require mentioned visibility.',
    'note_invalid_notable_target'                => 'The selected note target is invalid, deleted, or outside the active organization.',
    'note_invalid_mention_target'                => 'The selected member is not active in the organization.',
    'note_member_context_required'               => 'An active organization member context is required for note operations.',
];
