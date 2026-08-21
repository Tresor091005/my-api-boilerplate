<?php

declare(strict_types=1);

return [
    'category_has_children'                   => 'Cannot delete a category that has children. Please delete or reassign the children first.',
    'category_cannot_be_descendant_parent'    => 'A category cannot have one of its descendants or itself as its parent.',
    'category_parent_not_found'               => 'The selected category parent does not exist in the current organization.',
    'option_in_use'                           => 'Cannot delete an option that is currently used by product variants.',
    'option_value_in_use'                     => 'Cannot delete an option value that is currently used by product variants.',
    'option_value_not_attached_to_option'     => 'The option value does not belong to the selected option.',
    'product_variant_is_last'                 => 'Cannot delete the last variant of a product.',
    'product_variant_not_attached_to_product' => 'The product variant does not belong to the selected product.',
    'label_in_use'                            => 'Cannot delete a label that is currently in use by products: :products.',
];
