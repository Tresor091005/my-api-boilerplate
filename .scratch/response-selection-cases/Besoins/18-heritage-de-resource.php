<?php

// Besoin : une Resource de détail reprend une Resource de liste et ajoute des membres (solde, règlement,
//   organisation), parfois à une position précise du JSON.
// Fréquence dans le projet d'origine : quelques Resources ; cas cités : AssetResource (AssetSummaryResource),
//   CashRegisterDetailResource, OrganizationMemberResource, PurchaseDetailResource.
// Avant : parent::manifest()->field(...) ; toArray() fixait l'ordre (...parent::toArray(), clé avant le parent,
//   champs du parent répétés, méthodes d'aide par bloc).
// Maintenant : parent::define($resource) d'abord, puis les ajouts ; l'ordre JSON est l'ordre de déclaration.
//   Un champ du parent redéclaré lève une exception. Position exacte : méthodes statiques d'aide appelées par define().
// Frontend : ordre des clés changé pour OrganizationMemberResource et CashRegisterDetailResource (valeurs identiques ;
//   un client qui lit par clé n'est pas touché) ; inchangé pour PurchaseDetailResource et AssetResource.

namespace Cases\Besoins;

use Cases\Api\ApiResource;
use Cases\Api\ResourceDefinition;

// Origine : OrganizationMemberResource ; parent indicatif.
final class OrganizationMemberResource extends MemberResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        parent::define($resource);
        // Avant en tête du JSON, maintenant après les champs du parent.
        $resource->field('organization_id');
    }
}

// Origine : packages/order/src/Http/Resources/CashRegisterDetailResource.php:17 ; parent indicatif.
final class CashRegisterDetailResource extends CashRegisterResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        parent::define($resource);
        // Avant entre cash_account_id et last_closed_at, maintenant en fin ; même forme que CustomerBalances (06).
        $resource->field('balance', columns: [/* colonnes lues par le calcul */], computedBy: CashRegisterBalances::class);
    }
}

// Origine : packages/catalog/src/Http/Resources/PurchaseResource.php:49
// Les méthodes d'aide par bloc deviennent statiques : le bloc settlement garde sa position.
class PurchaseResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        static::defineInvoice($resource);
        static::defineSettlement($resource);
        static::defineWorkflow($resource);
    }

    protected static function defineInvoice(ResourceDefinition $resource): void
    {
        /* ... */
    }

    // Rendu par PurchaseDetailResource seulement.
    protected static function defineSettlement(ResourceDefinition $resource): void
    {
    }

    protected static function defineWorkflow(ResourceDefinition $resource): void
    {
        /* ... */
    }
}

// Origine : packages/catalog/src/Http/Resources/PurchaseDetailResource.php:22
final class PurchaseDetailResource extends PurchaseResource
{
    protected static function defineSettlement(ResourceDefinition $resource): void
    {
        // Calcul par achat (besoin 06).
        $resource->field(
            'settlement',
            columns: ['id', 'company_id', 'invoice_total', 'amount_debt_adjusted'],
            computedBy: PurchaseSettlements::class,
        );
    }
}

// AssetResource (08) : parent::define() puis company_ids, metadata, occupied_volume, fill_rate, déjà en fin avant.
