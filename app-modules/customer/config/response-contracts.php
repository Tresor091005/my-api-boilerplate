<?php

declare(strict_types=1);

// Every other customer route answers through ApiResponse (response selection). This one streams a
// file, so it keeps an empty legacy contract until the legacy middleware is removed.
return [
    'lahatre.customer.customers.files.content' => [],
];
