# CRUD operations

`InventoryInterface` provides CRUD integration for inventoryable items and locations:

```php
$inventory->createItem($product);
$inventory->updateItem($product, $data);
$inventory->deleteItem($product);

$inventory->createLocation($warehouse);
$inventory->updateLocation($warehouse, $data);
$inventory->deleteLocation($warehouse);
```

Batch creation is available through `createManyItems()` and `createManyLocations()`.

Stock quantity and financial fields are controlled by transactions. Stock metadata is the only stock field exposed through the dedicated PATCH endpoint; see [Stock metadata](stock-metadata.md).
