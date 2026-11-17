<?php

declare(strict_types=1);

return [
    'due_date_is_followed'      => 'The payment due date cannot be emptied: credit sales waiting for approval, approved, active or being cancelled follow it, and confirmed sales cannot be changed. Keep a date on the customer: move it earlier or later instead of emptying it.',
    'file_customer_inactive'    => 'Files cannot be attached to an inactive customer.',
    'in_use'                    => 'This customer has sales under way (waiting for approval, approved, active or being cancelled), so it cannot be deleted. Finish or abandon those sales, or deactivate the customer instead.',
    'walk_in_cannot_be_deleted' => 'This customer is the one counter sales use, so it cannot be deleted. Rename it or deactivate it instead.',
];
