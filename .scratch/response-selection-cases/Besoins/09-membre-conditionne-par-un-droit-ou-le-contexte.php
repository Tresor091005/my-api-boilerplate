<?php

// Besoin : omettre un champ ou un include selon un droit (coût d'un lot, règlements) ou le contexte
//   (acteur d'organisation ou d'entreprise, type de propriétaire du profil), sans calculer ce qui sera omis.
// Fréquence dans le projet d'origine : 51 when: dans les toArray() ; drapeaux de loader : LotCostVisibilityLoader,
//   DirectSellerFlagLoader, UserProfileDirectSellerFlagLoader.
// Avant : when: sur $this->field() / $this->relation() dans toArray(), évalué après calcul ; ou un loader posait
//   un drapeau sur le modèle, relu par when:.
// Maintenant : when: sur la déclaration, deux formes. Droit ou contexte de requête : fn (Request $request): bool,
//   évalué une fois avant le chargement ; false = rien n'est chargé ni calculé pour le membre, omis partout.
//   Condition lisant le modèle : fn (Model $model, Request $request): bool, après chargement, avant construction ;
//   un modèle refusé n'est ni calculé ni passé au loader, ses colonnes lues sont déclarées sur le membre (voir 16).
//   Plus de drapeau posé sur le modèle.
// Frontend : inchangé, sauf PaymentSourceResource.settlement (voir plus bas).

namespace Cases\Besoins;

use Cases\Api\ApiResource;
use Cases\Api\BuiltValues;
use Cases\Api\FieldComputer;
use Cases\Api\ResourceDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

// Origine : packages/catalog/src/Http/Resources/InventoryStockResource.php:22 et :53 (aussi StockableStockGroupResource.php:23)
final class InventoryStockResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // Clé id lue sur group_id : le moteur ne suppose pas id = clé primaire (besoin 03).
        $resource->field('id', columns: ['group_id'], computedBy: fn (InventoryStock $lot) => $lot->group_id);
        // ...
        // Droit sans modèle : when: limité à la requête ; sans droit, rien n'est lu ni calculé, champs omis (fail-safe).
        $canViewCosts = fn (Request $request) => Gate::allows('viewCosts', InventoryStock::class);
        $resource->field('cost', columns: ['cost'], computedBy: LotCosts::class, when: $canViewCosts);
        $resource->field('cost_remainder', columns: ['cost_remainder'], computedBy: LotCosts::class, when: $canViewCosts);

        // CurrentCompanyVendorScope → relation dédiée (besoin 13) ; Resource indicative.
        $resource->include('vendor', VendorResource::class, relation: 'currentCompanyVendor');
    }
}

// Origine : packages/catalog/src/Http/Response/LotCostVisibilityLoader.php
// Ne fait plus que convertir : le droit est passé dans when:. StockableStockGroupResource la réutilise.
final class LotCosts implements FieldComputer
{
    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        $exponent = getTeamSettings()->currency_exponent;

        // cost et cost_remainder sont aussi les colonnes lues : clé de champ = colonne.
        return $models->mapWithKeys(fn (InventoryStock $lot) => [$lot->getKey() => collect($fields)
            ->mapWithKeys(fn (string $field) => [$field => fromMinorUnit($lot->getAttribute($field), $exponent)])
            ->all()])->all();
    }
}

// Origine : packages/team/src/Http/Resources/MoneyAccountResource.php:31, :35 et :46
final class MoneyAccountResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // Session d'organisation (contexte), règle inchangée.
        // Contexte de session : when: limité à la requête, supports_* ne charge pas companyLinks pour un acteur d'organisation.
        $organizationActor = fn (Request $request) => /* acteur d'organisation, inchangé */ false;
        $companyActor = fn (Request $request) => ! $organizationActor($request);

        // Relation lue par le champ (besoin 04) : supports_* n'est pas une colonne des comptes.
        $resource->field(
            'supports_incoming',
            relations: ['companyLinks'],
            computedBy: fn (MoneyAccount $account) => (bool) $account->selectedCompanyLink()?->supports_incoming,
            when: $companyActor,
        );
        $resource->field(
            'supports_outgoing',
            relations: ['companyLinks'],
            computedBy: fn (MoneyAccount $account) => (bool) $account->selectedCompanyLink()?->supports_outgoing,
            when: $companyActor,
        );

        // Dérivé du type, restreint à la liste du forfait (besoin 03).
        $resource->field('accepted_payment_methods', columns: ['type'], computedBy: fn (MoneyAccount $account) => array_values(array_intersect(
            MoneyAccountType::from($account->type)->acceptedMethods(),
            planPaymentMethods(),
        )));
        $resource->field('organization_id', when: $organizationActor);

        // MoneyAccountCompaniesScope → relation dédiée (besoin 13).
        $resource->include('companies', MoneyAccountCompanyResource::class, relation: 'currentOrganizationCompanies');
    }
}

// Origine : packages/accounting/src/Http/Resources/PaymentSourceResource.php:40
final class PaymentSourceResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // L'include ne montre jamais plus que sa route directe : droit dans when: limité à la requête (sans droit,
        // la relation n'est pas chargée), filtre du livre dans la relation (besoin 13).
        $resource->include(
            'settlement',
            SettlementResource::class,
            relation: 'currentBookSettlement',
            when: fn (Request $request) => (bool) userHasPermissionTo('settlements.retrieve'),
        );
    }
}
// Frontend : avant, un règlement d'un autre livre omettait la clé settlement (when: lisait la relation chargée) ;
//   maintenant when: ne lit pas la relation de l'include lui-même (chargée pour les seuls modèles acceptés),
//   la clé est rendue à null. Même information
//   (règlement invisible), forme différente : le client doit accepter null là où il attendait l'absence.

// Includes virtuels conditionnés par le type de propriétaire : UserProfileResource (16).
