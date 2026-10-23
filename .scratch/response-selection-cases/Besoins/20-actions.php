<?php

// Besoin : répondre depuis une action : un objet, une liste, une page par curseur, un 204, un statut variable
//   (201 ou 200 selon création ou restauration), avec la sélection ?fields / ?include résolue par un middleware.
// Fréquence dans le projet d'origine : ApiResponse::from 244, paginated 85, none 62, collection 28 ; allowedStatuses: 2.
// Avant : ApiResponse::from / collection / paginated / none, allowedStatuses:, ResponseMiddleware (':none').
// Maintenant : ApiResponse::item / list / cursorPage / noContent, possibleStatuses: (littéral non vide pour un statut
//   variable, repris par l'OpenAPI). ResolveResponseSelection, global sur le groupe api, lit l'appel dans le corps de
//   l'action (un seul, XResource::class littéral) : aucun HasMiddleware ; noContent() seul = action sans corps
//   (?response=resource, ?fields ou ?include = 422) ; action sans appel ApiResponse = chemin historique, ignorée.
// Frontend : inchangé (même enveloppe {data}).

namespace Cases\Besoins;

use Cases\Api\ApiResponse;

// Origine : packages/discount/src/Http/Controllers/DiscountCampaignAccessController.php:19 (:38, :43)
final class DiscountCampaignAccessController
{
    public function index(string $campaign)
    {
        $response = /* ... */ null;

        // Exception inchangée : BundleComponentController::index reste sur ResourceCollection (links, meta doublés).
        return ApiResponse::cursorPage($response, DiscountCampaignAccessResource::class);
    }

    // Même forme : DiscountCampaignRestrictionController::store.
    public function store(string $campaign)
    {
        $response = /* ... */ [];

        // Accorder est idempotent : réaccorder une entrée supprimée la restaure (200), sinon création (201).
        $status = ($response['restored'] ?? false) ? 200 : 201;

        return ApiResponse::item($response['model'], DiscountCampaignAccessResource::class, status: $status, possibleStatuses: [200, 201]);
    }

    // Seul appel noContent() : sans corps ; ?fields ou ?include donnent un 422 (selection_without_resource).
    public function destroy(string $campaign, string $access)
    {
        $this->accessService->delete($campaign, $access);

        return ApiResponse::noContent();
    }
}

// Origine : packages/accounting/src/Http/Controllers/LetteringController.php:61 (et :63) ; noms de méthodes indicatifs.
// openItems renvoie response()->json() sans appel ApiResponse : chemin historique, ignoré par le middleware.
final class LetteringController
{
    public function store(/* ... */)
    {
        $result = /* ... */ null;

        // Modèle, objet résultat ou tableau (besoin 02) ; sans clé de modèle, pas de champ calculé.
        return ApiResponse::item($result, LetteringResource::class, status: 201);
    }

    public function storeMany(/* ... */)
    {
        $result = /* ... */ [];

        return ApiResponse::list($result, LetteringResource::class, status: 201);
    }
}

// Origine : OptionValueController (packages/catalog/) ; nom de méthode indicatif.
final class OptionValueController
{
    public function __construct(protected OptionValueService $optionValueService) {}

    public function index($option)
    {
        return ApiResponse::list($this->optionValueService->list($option), OptionValueResource::class);
    }
}

// Origine : packages/catalog/.../TransferController.php:42 ; nom de méthode indicatif.
// response()->json(new LocationGroupResource(...), 201) → ApiResponse::item(..., status: 201) à la migration.
final class TransferController
{
    public function storeLocationGroup(/* ... */)
    {
        $group = /* ... */ null;

        return ApiResponse::item($group->loadCount('locations'), LocationGroupResource::class, status: 201);
    }
}
