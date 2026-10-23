<?php

// Origine : contrat ResourceResolver du projet d'origine (implémenté par StockableRegistry)
// Passé à include() ou virtualInclude() à la place de la Resource : choisit la Resource selon le modèle de chaque enfant.

namespace Cases\Api;

use Illuminate\Database\Eloquent\Model;

interface ResourceResolver
{
    /**
     * Resource qui rend ce modèle, ou null pour le rendre null.
     *
     * @return class-string<ApiResource>|null
     */
    public function resourceFor(Model $model): ?string;

    /**
     * Toutes les Resources possibles : validation de ?fields / ?include à ce niveau, couverture et OpenAPI.
     *
     * @return list<class-string<ApiResource>>
     */
    public function resources(): array;
}
