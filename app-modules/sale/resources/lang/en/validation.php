<?php

declare(strict_types=1);

return [
    'line_duplicate'          => 'This item is already on another line in this unit. Put it on one line.',
    'line_not_in_sale'        => 'This line is not a line of this sale.',
    'line_listed_twice'       => 'This line is already listed. List each line once, with all its sources.',
    'nothing_to_hold'         => 'The stock of this item is not tracked, so nothing is set aside for it. Leave the line out.',
    'lots_for_untracked_item' => 'The stock of this item is not tracked, so it has no lots. Leave the lots out.',
    'warehouse_required'      => 'Name the warehouse the goods leave from.',
    'warehouse_listed_twice'  => 'This warehouse is already a source of this line. Put its whole quantity in one source.',
    'warehouse_unavailable'   => 'This warehouse does not exist, is inactive or is not a warehouse. Choose an active warehouse.',
    'nothing_held_here'       => 'Nothing of this line is set aside in this warehouse. Choose a warehouse it was set aside in.',
    'quantity_over_held'      => 'Only :held :unit of this line are set aside in this warehouse.',
    'sources_cover_line'      => 'The sources must add up to the whole line: :quantity :unit.',
    'sources_over_remaining'  => 'Only :remaining :unit of this line are left to deliver.',
    'lines_missing_sources'   => 'Name where these lines leave from: :items.',
    'lot_unavailable'         => 'This lot is not one of this item in this location, or it is empty.',
];
