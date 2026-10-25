<?php

declare(strict_types=1);

return [
    'failed'        => 'Validation failed',
    'rfc3339_utc'   => 'The :attribute must be a valid UTC date and time in RFC 3339 format.',
    'ymd_date'      => 'The :attribute must be a valid date in YYYY-MM-DD format.',
    'local_time'    => 'The :attribute must be a valid local time in HH:MM or HH:MM:SS format.',
    'iana_timezone' => 'The :attribute must be a valid IANA timezone identifier.',
    'bulk_exists'   => 'One or more selected :attribute are invalid.',
    'bulk_unique'   => 'The :attribute contains duplicate or already used values.',
];
