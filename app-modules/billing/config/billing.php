<?php

declare(strict_types=1);

return [
    'max_advance_months'    => 12,
    'subscription_required' => true,
    // false lets every organization use every feature, as the test suite does
    'entitlements_enforced' => true,
];
