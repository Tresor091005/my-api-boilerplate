<?php

// Besoin : rendre une valeur lue sur une relation du modèle (ou plusieurs niveaux), sans N+1.
// Fréquence dans le projet d'origine : 76 champs avec loads: ; cas cités : ChequeResource (source),
//   ProductResource.options, AssetResource.company_ids, MoneyAccountResource.supports_*, paliers de remise (campaign).
// Avant : loads: ['rel'] (avec colonnes de relation possibles), résolveur sur $this->rel dans toArray().
// Maintenant : relations: ['rel.sous'] nus, chargés entiers en une requête par niveau (loadMissing), relation déjà
//   chargée reprise ; closure computedBy: lit la relation ; lazy loading interdit (exception si non déclarée).
// Frontend : inchangé.

namespace Cases\Besoins;

use Cases\Api\ApiResource;
use Cases\Api\ResourceDefinition;

// Origine : packages/accounting/src/Http/Resources/ChequeResource.php:15 (et :80)
final class ChequeResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource->field(
            'customer',
            columns: ['customer_id', 'accounting_payment_source_id'],
            relations: ['source'],
            computedBy: fn (Cheque $cheque) => ['id' => $cheque->customer_id, 'name' => $cheque->source->customer_name],
        );

        $resource->field(
            'amount',
            columns: ['accounting_payment_source_id'],
            relations: ['source'],
            computedBy: fn (Cheque $cheque) => $cheque->source->functional_amount,
        );

        // Valeurs portées par la source de paiement ; settlement_id compris (lu sur la source, plus de décision ouverte).
        foreach ([
            'source_type', 'source_id', 'currency_code', 'currency_exponent', 'original_amount',
            'original_currency_code', 'original_currency_exponent', 'reference', 'received_at', 'accounting_status',
            'settlement_id', 'cheque_transfer_id',
        ] as $key) {
            $resource->field(
                $key,
                columns: ['accounting_payment_source_id'],
                relations: ['source'],
                computedBy: fn (Cheque $cheque) => $cheque->source->getAttribute($key),
            );
        }

        // ...
        // Enums rendues nues (valeur de l'enum).
        $resource->field('financial_status');
        // Alias historique de financial_status, gardé pour compatibilité.
        $resource->field('status', columns: ['financial_status'], computedBy: fn (Cheque $cheque) => $cheque->financial_status);
        $resource->field('recovery_status');
    }
}

// Origine : packages/catalog/src/Http/Resources/ProductResource.php (options)
final class ProductResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        $resource->field(
            'options',
            relations: ['optionValues.option'],
            computedBy: fn (Product $product) => /* options groupées depuis optionValues.option, inchangé */ [],
        );
    }
}

// Mêmes formes, déclarées dans le fichier du cas complet :
//   AssetResource (08) : company_ids lit companies ;
//   MoneyAccountResource (09) : supports_incoming / supports_outgoing lisent companyLinks ;
//   DiscountCampaignTierResource (06) : maximum_amount lit campaign.currency_exponent.
