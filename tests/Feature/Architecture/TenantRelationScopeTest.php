<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\StockTransfer;
use Lahatre\Inventory\Models\InventoryMovement;
use Lahatre\Inventory\Models\InventoryStock;
use Lahatre\Library\Models\File;
use Lahatre\Library\Models\Folder;
use Lahatre\Master\Models\Note;
use Lahatre\Master\Models\Unit;
use Lahatre\Master\Models\UnitGroup;

it('scopes a relation to tenant rows by the current organization', function (string $modelClass, string $relation, string $table): void {
    setPermissionsTeamId((string) Str::uuid7());

    /** @var Model $model */
    $model = new $modelClass;
    $sql = $model->{$relation}()->toBase()->toSql();

    expect($sql)->toContain("\"{$table}\".\"organization_id\"");
})->with([
    'stock transfer lines'    => [StockTransfer::class, 'lines', 'catalog_stock_transfer_lines'],
    'folder files'            => [Folder::class, 'files', 'library_files'],
    'file folder'             => [File::class, 'folder', 'library_folders'],
    'note parent'             => [Note::class, 'parent', 'master_notes'],
    'catalog item unit group' => [CatalogItem::class, 'unitGroup', 'master_unit_groups'],
    'unit group'              => [Unit::class, 'group', 'master_unit_groups'],
    'unit group units'        => [UnitGroup::class, 'units', 'master_units'],
    'unit group base unit'    => [UnitGroup::class, 'baseUnit', 'master_units'],
    'inventory stock unit'    => [InventoryStock::class, 'unit', 'master_units'],
    'inventory movement unit' => [InventoryMovement::class, 'unit', 'master_units'],
]);
