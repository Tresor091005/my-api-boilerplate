<?php

// Besoin : les enfants inclus lisent une valeur de leur parent (exposant monétaire de la campagne) sans N+1.
// Fréquence dans le projet d'origine : 1 (DiscountChildCampaignLoader sur les includes de DiscountCampaignResource).
// Avant : batch: [DiscountChildCampaignLoader] sur l'include, qui reposait la campagne sur chaque enfant.
// Maintenant : rien côté parent ; l'enfant déclare relations: ['campaign'] sur le champ qui la lit, chargée entière en
//   une requête bornée par nœud. chaperone() seulement si l'enfant ne lit rien du parent au-delà de sa clé.
// Frontend : inchangé.

namespace Cases\Besoins;

use Cases\Api\ApiResource;
use Cases\Api\ResourceDefinition;

// Origine : packages/discount/src/Http/Resources/DiscountCampaignResource.php:36
final class DiscountCampaignResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // Les enfants déclarent relations: ['campaign'] sur leurs champs monétaires.
        $resource->include('conditions', DiscountConditionResource::class);
        // DiscountCampaignTierResource (06) : maximum_amount, relations: ['campaign'].
        $resource->include('tiers', DiscountCampaignTierResource::class);
    }
}
