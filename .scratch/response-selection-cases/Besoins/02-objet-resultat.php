<?php

// Besoin : rendre un objet résultat ou un tableau associatif (pas un modèle).
// Fréquence dans le projet d'origine : rare ; AccountingBookResource, retours de LetteringController (voir 20).
// Avant : field('x') sans colonne ; le résolveur de toArray() lisait la propriété ; aucun loader (pas de clé de modèle).
// Maintenant : le moteur ne rend que des modèles Eloquent ; un objet résultat est rendu par un JsonResource simple,
//   sans sélection, qui renvoie l'objet entier (toArray() écrit à la main, comme le besoin 19).
// Frontend : ?fields n'est plus honoré sur ces endpoints ; l'objet entier est renvoyé.

namespace Cases\Besoins;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Origine : packages/accounting/src/Http/Resources/AccountingBookResource.php:13
// Les livres sont des objets résultat (AccountingBook, propriétés readonly).
/**
 * @property AccountingBook $resource
 */
final class AccountingBookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->resource->id,
            'type'              => $this->resource->type,
            'name'              => $this->resource->name,
            'currency_code'     => $this->resource->currencyCode,
            'currency_exponent' => $this->resource->currencyExponent,
            'is_active'         => $this->resource->isActive,
        ];
    }
}
