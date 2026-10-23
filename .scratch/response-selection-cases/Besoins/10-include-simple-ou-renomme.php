<?php

// Besoin : inclure une relation Eloquent à la demande (?include=), sous la clé de sa relation ou sous une autre clé JSON.
// Fréquence dans le projet d'origine : la majorité des 114 includes (114 moins 19 loader:, 11 scope:, 1 polymorphe).
// Avant : include('nom', Resource::class) dans manifest(), 'nom' => $this->relation('nom') dans toArray().
// Maintenant : include('nom', Resource::class) ; relation Str::camel(nom), ou relation: quand la clé JSON diffère.
// Frontend : inchangé.

namespace Cases\Besoins;

use Cases\Api\ApiResource;
use Cases\Api\ResourceDefinition;

// Origine : packages/iam/src/Http/Resources/ProfileResource.php:38
final class ProfileResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // Clé JSON roles, relation groupedRoles ; Resource indicative.
        $resource->include('roles', RoleResource::class, relation: 'groupedRoles');
    }
}

// Origine : packages/catalog/src/Http/Resources/PurchaseReceptionItemResource.php:43
final class PurchaseReceptionItemResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // Clé JSON purchase_item, relation item ; Resource indicative.
        $resource->include('purchase_item', PurchaseItemResource::class, relation: 'item');
    }
}

// Mêmes formes, déclarées dans le fichier du cas complet :
//   DiscountCampaignResource (11) : include('conditions', ...) / include('tiers', ...), relation du même nom ;
//   InventoryLevelResource (17) : include('item', InventoryItemResource::class, relation: 'inventoryItem').
