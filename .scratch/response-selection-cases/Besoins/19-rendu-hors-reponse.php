<?php

// Besoin : rendre une Resource hors d'une réponse d'action (instantané de document, résultat d'import, payloads
//   d'authentification, secret passé au constructeur), sans sélection ?fields / ?include.
// Fréquence dans le projet d'origine : 150 new XResource( hors Resources ; cas cités : OrderDocumentPresenter,
//   TemplateFileImportService:365, CashRegisterInstanceCompletionResource, UserProfileService::prepareForLegacyRendering(),
//   extension Scramble ResponseControlOperationExtension.php:141, TransferController (à migrer, besoin 20).
// Avant : new XResource($model) sur une ApiResource ou une JsonResource, mode sans sélection et legacyRelation().
// Maintenant : hors ApiResponse = JsonResource dédiée (toArray() écrit à la main) ; l'appelant charge ce qu'elle lit.
//   Une ApiResource n'est rendue que par ApiResponse (son toArray() généré a besoin de la sélection).
// Frontend : inchangé (mêmes payloads).

namespace Cases\Besoins;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Origine : CashRegisterInstanceCompletionResource ; constructeur indicatif.
// Argument de constructeur : reste une JsonResource, ne peut pas étendre une ApiResource.
final class CashRegisterInstanceCompletionResource extends JsonResource
{
    public function __construct($resource, protected string $instanceSecret)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            // ... champs de l'instance
            'instance_secret' => $this->instanceSecret,
        ];
    }
}

// Origine : packages/order/src/Services/OrderDocumentPresenter.php:16
// OrderDocumentResource : JsonResource dédiée, indépendante d'OrderResource (ApiResource).
final class OrderDocumentPresenter
{
    // Instantané de facture des modèles de document (OrderInterface::serializeOrder).
    public function present(Order $order): array
    {
        // Le service charge ce que l'instantané rend ; les détails catalogue viennent du service catalogue.
        $order->loadMissing([
            'discountApplications.allocations', 'items.allocations', 'items.taxLines', 'taxLines',
            'payments', 'creditDetail', 'seller', 'cashRegister', 'cashier',
        ]);
        $payload = (new OrderDocumentResource($order))->resolve();

        // Alias historiques subtotal / tax_total / line_subtotal : contrat des modèles publiés, pas de la sélection.
        return $this->withLegacyTotals($order, $payload);
    }

    private function withLegacyTotals(Order $order, array $payload): array
    {
        /* ... */
        return $payload;
    }
}

// Origine : packages/template/.../TemplateFileImportService.php:365 ; extrait.
// TemplateFileImportResource reste une JsonResource.
final class TemplateFileImportService
{
    private function reject(TemplateFileImport $import): array
    {
        return [
            // ...
            'data' => (new TemplateFileImportResource($import))->resolve(),
        ];
    }
}

// À trancher : le validateur pourrait signaler un new X() sur une ApiResource (toArray() généré inutilisable hors ApiResponse).
