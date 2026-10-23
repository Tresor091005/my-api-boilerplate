<?php

// Besoin : rendre une valeur tirée d'une ou plusieurs colonnes du modèle : autre nom, conversion, combinaison,
//   alias historique, constante de contrat, ou valeur dépendant de l'utilisateur courant.
// Fréquence dans le projet d'origine : fréquent (la plupart des champs à columns: autre que leur clé) ;
//   cas cités : InvitationResource.status, CompanyResource.logo_url / parent, InventoryStockResource.id,
//   OrderItemResource.unit_price (et les 7 totaux d'OrderResource), ChequeResource.status, UserProfileResource.is_current,
//   MoneyAccountResource.accepted_payment_methods.
// Avant : columns: dans manifest(), résolveur fn () => ... sur $this dans toArray().
// Maintenant : columns: (liste exhaustive) + computedBy: closure fn (Model $model, BuiltValues $built), pure, sans requête.
//   Une enum se rend nue (json_encode donne sa valeur) : plus de ->value.
// Frontend : inchangé.

namespace Cases\Besoins;

use Cases\Api\ApiResource;
use Cases\Api\ResourceDefinition;

// Origine : packages/team/src/Http/Resources/InvitationResource.php:13
final class InvitationResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource->field('id');
        $resource->field('email');
        $resource->field('pseudo');
        $resource->field('expired_at');
        $resource->field('profile_ids');
        $resource->field('accepted_at');
        $resource->field('created_at');
        $resource->field('updated_at');

        // Statut effectif : statut stocké et date d'expiration.
        $resource->field(
            'status',
            columns: ['status', 'expired_at'],
            computedBy: fn (Invitation $invitation) => $invitation->effectiveStatus(),
        );
    }
}

// Origine : packages/team/src/Http/Resources/CompanyResource.php (logo_url, parent)
final class CompanyResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        $resource->field('logo_url', columns: ['logo'], computedBy: fn (Company $company) => /* URL du logo, inchangée */ null);

        // Clé gardée pour le contrat historique, toujours null : ne lit rien.
        $resource->field('parent', columns: [], computedBy: fn () => null);
    }
}

// Mêmes formes, déclarées dans le fichier du cas complet :
//   InventoryStockResource (09) : $resource->field('id', columns: ['group_id'], computedBy: fn (InventoryStock $lot) => $lot->group_id);
//   OrderItemResource (07) : $resource->field('unit_price', columns: ['unit_price', 'currency_exponent'], computedBy: ...);
//   ChequeResource (04) : alias status de financial_status, enums nues ;
//   MoneyAccountResource (09) : accepted_payment_methods depuis type ;
//   UserProfileResource (16) : is_current compare profile_id au profil de la session (contexte lu, aucune requête).
