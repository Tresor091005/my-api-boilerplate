<?php

declare(strict_types=1);

return [
    'disk' => env('LIBRARY_DISK', 'local'),

    'root_prefix' => 'organizations',

    'upload' => [
        'max_files'      => (int) env('LIBRARY_MAX_FILES_PER_UPLOAD', 20),
        'max_file_size'  => ini_parse_quantity((string) env('LIBRARY_MAX_FILE_SIZE', '25M')),
        'max_batch_size' => ini_parse_quantity((string) env('LIBRARY_MAX_BATCH_SIZE', '100M')),
        'allowed_mimes'  => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'application/pdf',
            'text/plain',
            'text/csv',
            'text/html',
            'application/xhtml+xml',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip',
            'application/x-7z-compressed',
            'application/x-rar-compressed',
            'audio/mpeg',
            'audio/ogg',
            'audio/wav',
            'video/mp4',
            'video/webm',
        ],
    ],

    'serving' => [
        'inline_mimes' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
        ],
        'cache_control' => 'private, no-cache',
    ],

    'maintenance' => [
        'orphan_grace_hours'   => (int) env('LIBRARY_ORPHAN_GRACE_HOURS', 24),
        'trash_retention_days' => 30,
    ],
];
