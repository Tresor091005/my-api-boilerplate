<?php

// Besoin : inclure une relation filtrée par le contexte de la requête (organisation ou entreprise de session,
//   livre comptable courant, partition fiscale, visibilité de l'acteur), avec un tri stable si besoin.
// Fréquence dans le projet d'origine : 11 includes avec scope: ; cas cités : CurrentTaxPartitionScope,
//   VisibleStockableScope, EligibleInventoryLevelsScope, MoneyAccountCompaniesScope, PaymentSourceSettlementScope,
//   CurrentCompanyVendorScope, CurrentOrganizationScope (InventoryStockListingScope : besoin 14).
// Avant : include(..., scope: XScope) ; IncludeScope appliquait le filtre à la requête de chargement.
// Maintenant : relation dédiée du modèle, servie par include(relation:) ; le contexte est lu à l'appel de la relation
//   (relation paresseuse : aucun coût tant que l'include n'est pas demandé). Contexte pas toujours valable pour le
//   modèle : virtualInclude() (besoin 16).
// Frontend : inchangé.

namespace Cases\Besoins;

use Cases\Api\ApiResource;
use Cases\Api\ResourceDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Origine : CurrentTaxPartitionScope, modèle Category ; extrait. Stockable reçoit la même relation (StockableFiscalResource.php:20).
class Category extends Model
{
    public function currentPartitionTaxes(): BelongsToMany // ou le type actuel de taxes()
    {
        $relation = $this->taxes();

        // Contexte d'équipe toujours présent au rendu d'une catégorie.
        foreach (app(TaxPartitionService::class)->current() as $column => $value) {
            $relation->where($relation->getRelated()->qualifyColumn($column), $value);
        }

        return $relation;
    }
}

// Origine : packages/catalog/src/Http/Resources/CategoryFiscalResource.php:18
final class CategoryFiscalResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        $resource->include('taxes', TaxResource::class, relation: 'currentPartitionTaxes');
    }
}

// Origine : VisibleStockableScope et EligibleInventoryLevelsScope, modèle InventoryItem ; extrait.
// ProductVariant reçoit la même relation eligibleInventoryLevels() (ProductVariantResource.php:38, besoin 16).
class InventoryItem extends Model
{
    public function visibleStockable(): BelongsTo
    {
        return $this->belongsTo(Stockable::class, 'stockable_id')->visibleInCurrentScope();
    }

    public function eligibleInventoryLevels(): HasMany
    {
        // À trancher : acteur courant toujours présent quand un article est rendu ; sinon virtualInclude().
        return $this->inventoryLevels()->eligibleForCurrentActor();
    }
}

// Origine : packages/catalog/src/Http/Resources/InventoryItemResource.php:20 et :39
final class InventoryItemResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // L'inventaire appartient à l'organisation mais les lots sont privés à une entreprise :
        // un article d'un lot d'une entreprise sœur est listé avec stockable null.
        $resource->include('stockable', StockableResource::class, relation: 'visibleStockable');
        $resource->include('levels', InventoryLevelResource::class, relation: 'eligibleInventoryLevels');
    }
}

// Origine : MoneyAccountCompaniesScope, modèle MoneyAccount (packages/team/) ; extrait.
// Servie par MoneyAccountResource (09) : include('companies', ..., relation: 'currentOrganizationCompanies').
class MoneyAccount extends Model
{
    public function currentOrganizationCompanies(): BelongsToMany // ou le type actuel de companies()
    {
        $related = $this->companies()->getRelated();

        // Organisation de session lue à l'appel ; tri stable du payload.
        return $this->companies()
            ->where($related->qualifyColumn('organization_id'), getTeamOrganization()->id)
            ->orderBy($related->qualifyColumn('name'))
            ->orderBy($related->qualifyColumn('id'));
    }
}

// Origine : PaymentSourceSettlementScope, modèle PaymentSource (packages/accounting/) ; extrait.
// Servie par PaymentSourceResource (09) : include('settlement', ..., relation: 'currentBookSettlement', when: droit).
class PaymentSource extends Model
{
    public function currentBookSettlement(): BelongsTo
    {
        $relation = $this->belongsTo(Settlement::class, 'settlement_id');
        $related = $relation->getRelated();

        // Session d'organisation : l'entreprise est le livre choisi ; session d'entreprise : l'entreprise de la session.
        return $relation
            ->where($related->qualifyColumn('company_id'), app(AccountingBookContext::class)->current()?->id ?? getPermissionsTeamId())
            ->where($related->qualifyColumn('organization_id'), getTeamOrganization()->id);
    }
}

// Même forme : CurrentCompanyVendorScope (InventoryStockResource.vendor, 09), CurrentOrganizationScope
//   (UomCategoryResource.php:18, units) → une relation dédiée sur chaque modèle.
