<?php

declare(strict_types=1);

return [
    'discovery' => [
        'starting'               => 'Starting permission discovery...',
        'scanning'               => 'Scanning for models in: :path.',
        'discovered_model'       => 'Discovered model: :class -> :model.',
        'skipped_model'          => 'Skipped permission discovery for model: :model.',
        'skipped_models_summary' => 'Skipped :count model(s) without a registered morph alias: :classes.',
        'created_permission'     => '✔ Created permission: :name.',
        'completed_syncing'      => 'Model permissions discovery completed. Syncing roles...',
        'synced_administrator'   => '✔ Synced Administrator role.',
        'synced_read_only'       => '✔ Synced Readonly role.',
        'success'                => 'Permission discovery and role synchronization completed successfully.',
    ],
    'roles' => [
        'administrator' => [
            'description' => 'Administrator with all permissions.',
        ],
        'read_only' => [
            'description' => 'Readonly role with view access.',
        ],
    ],
    'permissions' => [
        'title'       => ':action :model',
        'description' => 'Allow :action :model.',
    ],
];
