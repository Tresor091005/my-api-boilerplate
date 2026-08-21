<?php

declare(strict_types=1);

return [
    'prune_expired_notes' => [
        'description'            => 'Soft-delete expired notes after their retention period.',
        'invalid_retention_days' => 'The retention period must be zero or greater.',
        'completed'              => ':notes expired note(s) pruned.',
    ],
];
