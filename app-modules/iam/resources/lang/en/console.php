<?php

declare(strict_types=1);

return [
    'geoip' => [
        'description' => 'Install or update the local GeoLite2 City database',
        'updated'     => 'GeoLite2 City database installed successfully.',
        'unchanged'   => 'The local GeoLite2 City database is already up to date.',
        'locked'      => 'Another GeoLite2 City update is already running.',
    ],
    'discovery' => [
        'starting'               => 'Starting permission discovery...',
        'scanning'               => 'Scanning for models in: :path.',
        'discovered_model'       => 'Discovered model: :class -> :model.',
        'skipped_model'          => 'Skipped permission discovery for model: :model.',
        'skipped_models_summary' => 'Skipped :count model(s) without a registered morph alias: :classes.',
        'created_permission'     => '✔ Created permission: :name.',
        'completed_syncing'      => 'Model permissions discovery completed. Syncing roles...',
        'synced_administrator'   => '✔ Synced Administrator role.',
        'success'                => 'Permission discovery and role synchronization completed successfully.',
    ],
    'roles' => [
        'administrator' => [
            'description' => 'Administrator with all permissions.',
        ],
    ],
    'permissions' => [
        'title'       => ':action :model',
        'description' => 'Allow :action :model.',
    ],
];
