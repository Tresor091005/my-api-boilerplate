<?php

// Besoin : calculer un champ pour toute la page en un nombre borné de requêtes (service d'un autre module,
//   agrégat, calcul qui compare les modèles du nœud entre eux).
// Fréquence dans le projet d'origine : 34 champs avec batch: pour 22 BatchLoader (avec 07) ; cas cités :
//   CustomerBalanceLoader, VendorComputedDebtsLoader, CashRegisterBalancesLoader, DiscountTierMaximumAmountsLoader,
//   PurchaseSettlementsLoader.
// Avant : batch: [XLoader] ; le loader posait un attribut sur chaque modèle (setRawAttributes, mémo, gardes),
//   le résolveur de toArray() le relisait et convertissait.
// Maintenant : computedBy: classe FieldComputer ; elle renvoie [clé modèle => [champ => valeur finale]], le moteur
//   range et rend ; champ absent pour un modèle = omis, null voulu renvoyé explicitement.
// Frontend : inchangé.

namespace Cases\Besoins;

use Cases\Api\ApiResource;
use Cases\Api\BuiltValues;
use Cases\Api\FieldComputer;
use Cases\Api\ResourceDefinition;
use Illuminate\Support\Collection;

// Origine : packages/customer/src/Http/Resources/CustomerResource.php:29
final class CustomerResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // is_creditable retiré des colonnes : ni le calcul ni le rendu ne le lisent.
        $resource->field('computed_balance', columns: ['debt_ledger_account_id'], computedBy: CustomerBalances::class);
    }
}

// Origine : packages/customer/src/Http/Response/CustomerBalanceLoader.php
// Même forme : VendorComputedDebtsLoader, CashRegisterBalancesLoader (CashRegisterDetailResource, 18).
final class CustomerBalances implements FieldComputer
{
    public function __construct(protected LedgerInterface $ledger) {}

    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        $exponent = getTeamSettings()->currency_exponent;
        $values = [];

        // L'index joint déjà un alias SQL computed_balance (il trie dessus) : on le réutilise.
        $pending = $models->reject(function (Customer $customer) use (&$values, $exponent) {
            if (! array_key_exists('computed_balance', $customer->getAttributes())) {
                return false;
            }
            $values[$customer->getKey()] = ['computed_balance' => fromMinorUnit((int) $customer->getAttribute('computed_balance'), $exponent)];

            return true;
        })->filter(function (Customer $customer) use (&$values, $exponent) {
            if ($customer->getAttribute('debt_ledger_account_id') !== null) {
                return true;
            }
            // Sans compte de dette, 0 comme avant : renvoyé explicitement, sinon le champ serait omis.
            $values[$customer->getKey()] = ['computed_balance' => fromMinorUnit(0, $exponent)];

            return false;
        });

        if ($pending->isEmpty()) {
            return $values;
        }

        $accounts = $this->ledger->retrieveAccountsByIds(
            $pending->map(fn (Customer $c) => $c->getAttribute('debt_ledger_account_id'))->unique()->values()->all()
        );

        foreach ($pending as $customer) {
            $account = $accounts->get($customer->getAttribute('debt_ledger_account_id'));
            if ($account !== null) {
                $values[$customer->getKey()] = ['computed_balance' => fromMinorUnit((int) $account->balance, $exponent)];
            }
        }

        return $values;
    }
}

// Origine : packages/discount/src/Http/Resources/DiscountCampaignTierResource.php:16
final class DiscountCampaignTierResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        $resource->field(
            'maximum_amount',
            columns: ['company_id', 'discount_campaign_id', 'minimum_amount'],
            relations: ['campaign'],
            computedBy: TierMaximumAmounts::class,
        );
    }
}

// Origine : packages/discount/src/Http/Response/DiscountTierMaximumAmountsLoader.php
// Juste seulement si le nœud contient tous les paliers de chaque campagne (l'include tiers de la campagne les fournit).
final class TierMaximumAmounts implements FieldComputer
{
    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        $values = [];
        $campaigns = $models->groupBy(fn ($t) => $t->getAttribute('company_id').':'.$t->getAttribute('discount_campaign_id'));

        foreach ($campaigns as $campaignTiers) {
            $ordered = $campaignTiers->sort(fn ($l, $r) => $l->getAttribute('minimum_amount') <=> $r->getAttribute('minimum_amount'))->values();
            foreach ($ordered as $index => $tier) {
                $next = $ordered->get($index + 1)?->getAttribute('minimum_amount');
                // Dernier palier : null voulu, renvoyé explicitement.
                $values[$tier->getKey()] = ['maximum_amount' => $next === null ? null : fromMinorUnit($next, $tier->campaign->currency_exponent)];
            }
        }

        return $values;
    }
}

// Origine : packages/catalog/src/Http/Response/PurchaseSettlementsLoader.php
// Déclaré par PurchaseDetailResource (18) seulement (GET purchases/{purchase}) : un calcul par achat, pas de version groupée.
final class PurchaseSettlements implements FieldComputer
{
    public function __construct(protected PurchaseSettlementCalculator $calculator) {}

    public function compute(Collection $models, array $fields, BuiltValues $built): array
    {
        // Même exposant que PurchaseResource (réglages de l'équipe).
        $exponent = getTeamSettings()->currency_exponent;

        return $models->mapWithKeys(fn (Purchase $purchase) => [
            $purchase->getKey() => ['settlement' => array_map(
                fn (int $amount) => fromMinorUnit($amount, $exponent),
                $this->calculator->totalsFor($purchase),
            )],
        ])->all();
    }
}
