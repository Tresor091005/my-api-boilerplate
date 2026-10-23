<?php

// Besoin : rendre une colonne sous sa propre clé, avec son cast (date, JSON, enum).
// Fréquence dans le projet d'origine : 956 field('x', columns: ['x']), environ 80 % des déclarations.
// Avant : field('x', columns: ['x']) dans manifest(), puis 'x' => $this->field('x') répété dans toArray().
// Maintenant : field('x') nu ; une seule ligne, la valeur vient de getAttribute('x') (cast inclus).
// Frontend : inchangé.

namespace Cases\Besoins;

use Cases\Api\ApiResource;
use Cases\Api\ResourceDefinition;

// Origine : packages/order/src/Http/Resources/OrderPaymentResource.php:24
// Même forme : CreditPaymentResource, SettlementResource, PaymentSourceResource.company_incoming_fee_snapshot.
final class OrderPaymentResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // Instantané JSON : le cast du modèle donne déjà le tableau rendu.
        $resource->field('conversion_snapshot');
    }
}

// Autres cas de colonne simple : InvitationResource (03), OrganizationMemberResource.organization_id (18).
