<?php

declare(strict_types=1);

return [
    'reconcile' => [
        'description'     => 'Reconcile Library database records with stored file objects.',
        'invalid_options' => 'The organization and grace-hours options must be valid, and only one maintenance mode may be selected.',
        'completed'       => 'Reconciliation completed: :deleted_objects deleted objects cleaned, :purged_files files purged, :purged_folders folders purged, :orphans orphans found, :pruned orphans pruned.',
    ],
];
