<?php

declare(strict_types=1);

return [
    'file_attached'                      => 'Detach this file from its records before deleting it.',
    'attachment_duplicate'               => 'This file is already attached to the slot.',
    'attachment_selection_invalid'       => 'Select at least one file for the slot.',
    'attachment_order_invalid'           => 'The order must contain every current attachment in the slot exactly once.',
    'attachment_slot_unavailable'        => 'This file slot is unavailable.',
    'attachment_limit_exceeded'          => 'This file slot cannot contain more than :maximum files.',
    'attachment_mime_type_not_allowed'   => 'This file type is not allowed in the slot.',
    'folder_name_already_exists'         => 'A folder with this name already exists at this location.',
    'folder_cannot_contain_itself'       => 'A folder cannot contain itself.',
    'folder_cannot_move_into_descendant' => 'A folder cannot be moved into one of its descendants.',
    'folder_depth_exceeded'              => 'The folder hierarchy cannot exceed :maximum levels.',
    'folder_width_exceeded'              => 'A folder cannot contain more than :maximum direct children.',
    'folder_not_empty'                   => 'The folder must be empty before it can be deleted.',
    'upload_file_count_exceeded'         => 'An upload cannot contain more than :maximum files.',
    'upload_file_too_large'              => 'A file cannot exceed :maximum bytes.',
    'upload_batch_too_large'             => 'The combined upload size cannot exceed :maximum bytes.',
    'mime_type_not_allowed'              => 'This file type is not allowed.',
    'organization_quota_exceeded'        => 'The organization storage quota would be exceeded.',
    'storage_write_failed'               => 'The file could not be stored.',
    'file_content_missing'               => 'The file content is unavailable.',
    'member_context_required'            => 'An organization member context is required to upload files.',
    'organization_context_invalid'       => 'The active organization context is invalid.',
];
