<?php

declare(strict_types=1);

// Every other inventory route answers through ApiResponse (response selection). These two render
// rows built in code, not models, so they keep an empty legacy contract.
return [
    // An array of totals with the item's lots, not a model.
    'lahatre.inventory.items.locations.lots.index' => [],
    // Aggregate query rows grouped by item, location and currency.
    'lahatre.inventory.stocks.summary.index' => [],
];
