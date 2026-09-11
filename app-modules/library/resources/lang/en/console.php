<?php

declare(strict_types=1);

return [
    'reconcile' => [
        'description'      => 'Reconcile Library database records with stored file objects.',
        'invalid_options'  => 'The organization and grace-hours options must be valid.',
        'completed'        => 'Reconciliation completed: :missing missing, :corrupted corrupted, :deleted_objects deleted objects cleaned, :purged_files files purged, :purged_folders folders purged, :orphans orphans found, :pruned orphans pruned.',
        'missing_file'     => 'Missing object for file :file_id.',
        'corrupted_file'   => 'Checksum mismatch for file :file_id.',
    ],
];
