<?php

// Besoin : inclure une relation restreinte par une contrainte qui ne dépend pas de la requête
//   (contraintes par type sur un MorphTo : seuls profils utilisateur et membres, colonnes et relations fournies par IAM).
// Fréquence dans le projet d'origine : 2 includes (PosActorsLoader : CashierResource.cashable, SellerResource.sellable).
// Avant : loader: PosActorsLoader, qui chargeait le MorphTo avec ses contraintes et posait la relation.
// Maintenant : relation dédiée du modèle (morphTo()->constrain()), servie par include(relation:) ; relation chargée
//   entière, sans rétrécissement de colonnes : constrain() s'applique tel quel.
// Frontend : inchangé.

namespace Cases\Besoins;

use Cases\Api\ApiResource;
use Cases\Api\ResourceDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

// Origine : modèle Cashier (packages/order/) ; extrait : seule la relation ajoutée est montrée.
class Cashier extends Model
{
    public function posCashable(): MorphTo
    {
        // Nom de la méthode passé à morphTo() : colonnes cashable_type / cashable_id.
        // Contraintes par type fournies par IAM : order n'importe aucun modèle IAM/équipe.
        return $this->morphTo(__FUNCTION__, 'cashable_type', 'cashable_id')
            ->constrain(app(IAMInterface::class)->userProfileMorphConstraints());
    }
}

// Origine : modèle Seller (packages/order/) ; même forme sur sellable_type / sellable_id.
class Seller extends Model
{
    public function posSellable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'sellable_type', 'sellable_id')
            ->constrain(app(IAMInterface::class)->userProfileMorphConstraints());
    }
}

// Origine : packages/order/src/Http/Resources/CashierResource.php:18
final class CashierResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // cashable_type / cashable_id déduits du MorphTo ; une seule Resource pour tous les types.
        $resource->include('cashable', ActorIdentityResource::class, relation: 'posCashable');
    }
}

// Origine : packages/order/src/Http/Resources/SellerResource.php:17
final class SellerResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        $resource->include('sellable', ActorIdentityResource::class, relation: 'posSellable');
    }
}
