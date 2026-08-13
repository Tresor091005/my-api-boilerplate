# Inventory Thresholds and Alerts Spec

Objectif: introduire un systeme de thresholds et d'alertes "sans prise de tete" dans le module Inventory, en restant package-friendly (configurable, faible couplage, peu de faux positifs, debounced).

Date: 2026-04-10

## 1) Principes

- Les thresholds sont definis par "scope": `global`, `item` (across locations), `item_location`.
- Les alertes doivent etre idempotentes et debounced: on cree une alerte uniquement au "crossing" et on la resolv quand la condition n'est plus vraie.
- Les endpoints de lecture actuels restent la source de verite pour "stock": les alerts ne doivent pas forcer a charger plus de donnees que necessaire.
- La configuration doit etre progressive: un projet peut commencer avec uniquement `min/max` et ajouter ensuite expiration/mouvements/valeur.
- Ne pas stocker de thresholds dans `inventory_items` / `inventory_locations` / `inventory_stocks`: on cree des tables dediees (regles + alertes).

## 2) Scopes

`global`
- S'applique a tous les items actifs.

`item`
- S'applique a un `inventory_item` sur l'ensemble de ses locations.

`item_location`
- S'applique au couple `(inventory_item, inventory_location)`.

Resolution de regle:
- Priorite: `item_location` > `item` > `global`.
- Une regle peut ne definir qu'une partie des champs (ex: uniquement `expiring_soon_days`).

## 3) Types d'alertes (v1)

### 3.1 Quantite (remaining)

- `under_min_remaining`
  - Condition: `total_remaining < min_remaining`
- `over_max_remaining`
  - Condition: `total_remaining > max_remaining`
- `stockout`
  - Condition: `total_remaining = 0`
  - Note: peut etre un alias de `under_min_remaining` si `min_remaining` est 1, mais on le garde explicite car c'est une alerte frequente.

### 3.2 Expiration

- `expiring_soon`
  - Condition: au moins un lot actif (`remaining > 0`) expire dans `expiring_soon_days`.
- `expired`
  - Condition: au moins un lot actif est deja expire.

### 3.3 Mouvement

- `no_movement_since`
  - Condition: aucun mouvement depuis `no_movement_days` alors que `total_remaining > 0`.
- `too_many_movements`
  - Objectif: detecter une activite anormale (suspect: bug d'integration, double envoi, fraude, etc.).
  - Condition (simple): sur une fenetre glissante `movement_window_days`, le `movement_count` ou `moved_quantity` depasse un seuil.
  - Exemples de regles:
    - `movement_count > max_movements_per_window`
    - `sum(abs(quantity_base_unit)) > max_moved_qty_per_window`

## 4) Valeur financiere (v2)

Base: on a deja `unit_cost` et `currency_code` sur les lots, mais on doit clarifier la definition.

Propositions:
- `total_value` par scope = SUM(remaining * unit_cost) en devise `currency_code`.
- Contraintes:
  - Si plusieurs devises existent dans le scope, on ne calcule pas (ou on force une devise attendue via la regle).
- Alertes:
  - `under_min_value`, `over_max_value`.

## 5) Modele de donnees

### 5.1 Table `inventory_threshold_rules`

But: definir des regles par scope, activables/desactivables, et evolutives sans toucher aux tables core.

Champs proposes:
- `id` uuid (PK)
- `scope` enum: `global`, `item`, `item_location`
- `item_id` uuid nullable FK -> `inventory_items.id`
- `location_id` uuid nullable FK -> `inventory_locations.id`
- `stock_tracking_enabled` boolean default true on inventory items; threshold
  evaluation must ignore items that do not participate in stock tracking.
- Quantite:
  - `min_remaining` bigint nullable
  - `max_remaining` bigint nullable
- Expiration:
  - `expiring_soon_days` int nullable
- Mouvement:
  - `no_movement_days` int nullable
  - `movement_window_days` int nullable
  - `max_movements_per_window` int nullable
  - `max_moved_qty_per_window` bigint nullable
- Valeur (v2):
  - `min_value` bigint nullable
  - `max_value` bigint nullable
  - `currency_code` char(3) nullable
- `metadata` jsonb nullable
- timestamps
- softDeletes (optionnel, mais utile pour garder l'historique de config)

Contraintes:
- Check scope:
  - `global` => `item_id IS NULL AND location_id IS NULL`
  - `item` => `item_id IS NOT NULL AND location_id IS NULL`
  - `item_location` => `item_id IS NOT NULL AND location_id IS NOT NULL`
- Unicite (partial unique indexes avec `deleted_at IS NULL` si soft delete):
  - une seule regle `global`
  - une seule regle `item` par `item_id`
  - une seule regle `item_location` par `(item_id, location_id)`

### 5.2 Table `inventory_alerts`

But: stocker l'etat des alertes (open/resolved) + snapshot + debounce.

Champs proposes:
- `id` uuid (PK)
- `rule_id` uuid FK -> `inventory_threshold_rules.id`
- `type` enum:
  - `under_min_remaining`, `over_max_remaining`, `stockout`
  - `expiring_soon`, `expired`
  - `no_movement_since`, `too_many_movements`
  - (v2) `under_min_value`, `over_max_value`
- `status` enum: `open`, `resolved`
- `opened_at` timestamp
- `resolved_at` timestamp nullable
- `snapshot` jsonb (ex: totals, ids, counts, last_movement_at, expiring_count, sample_lot_ids)
- `last_evaluated_at` timestamp nullable (utile si evaluation scheduler)
- timestamps

Regle d'idempotence:
- Cle logique: `(rule_id, type, status=open)` unique (partial unique index) pour eviter les doublons.

## 6) Evaluation des regles

### 6.1 Deux canaux

On-write (synchrone ou queue):
- Quand une transaction est enregistree, on reevalue uniquement:
  - `item` touches
  - `item_location` touches
- Cibles:
  - `under_min_remaining`, `over_max_remaining`, `stockout`
  - `too_many_movements` peut etre evalue ici si la requete reste raisonnable.

Scheduler (toutes les X heures / 1 fois par jour):
- Reevalue:
  - `expiring_soon`, `expired`
  - `no_movement_since`
  - `too_many_movements` (si on veut eviter le cout on-write)

### 6.2 Calculs de base par scope

`total_remaining`
- item: SUM(remaining) sur `inventory_stocks` ou via relation agreggee.
- item_location: SUM(remaining) filtre `(item_id, location_id)`.
- global: soit par item (moins de bruit), soit "tous les items" (a definir; recommandation: global = fallback de regle, mais evaluation reste par item/item_location).

Expiration
- `expiring_soon`: lots actifs `expiration_date <= now + expiring_soon_days`.
- `expired`: lots actifs `expiration_date < now`.

Mouvements
- `last_movement_at`: MAX(`inventory_movements.created_at`) par scope.
- `movement_count` et `moved_quantity` sur une fenetre:
  - Fenetre: `created_at >= now - movement_window_days`.
  - Quantite: idealement en "base unit" si possible, sinon en "unit_code" (v1: count only, v1.1: quantity base unit).

Debounce
- Creation: si breach detecte et aucune alerte open existante.
- Resolution: si pas breach et alerte open existante.
- Optionnel: cooldown par type (ex: 24h) si on veut emettre "still low" (pas requis en v1).

## 7) Events

Events proposes:
- `InventoryAlertOpened`
- `InventoryAlertResolved`

Payload minimal:
- `alert_id`
- `type`
- `scope` + `item_id` + `location_id`
- `snapshot`

Note: on peut aussi emettre `InventoryTransactionRecorded` (si pas deja existant) et laisser un listener lancer l'evaluation.

## 8) API (lecture + admin)

Lecture:
- `GET /v1/inventory/alerts` (cursor pagination)
  - filtres: `status`, `type`, `item_id`, `location_id`, `scope`
- `GET /v1/inventory/alerts/{alert}`

Admin thresholds:
- `GET /v1/inventory/threshold-rules`
- `GET /v1/inventory/threshold-rules/{rule}`
- `POST /v1/inventory/threshold-rules`
- `PATCH /v1/inventory/threshold-rules/{rule}`
- `DELETE /v1/inventory/threshold-rules/{rule}`

Note: en mode package, l'admin UI n'est pas obligatoire, mais l'API facilite beaucoup l'integration.

## 9) Indexing (attendu)

Stocks (Postgres partial indexes):
- `(item_id, location_id)` WHERE `deleted_at IS NULL AND remaining > 0`
- `(location_id, item_id)` WHERE `deleted_at IS NULL AND remaining > 0`
- `(expiration_date)` WHERE `deleted_at IS NULL AND remaining > 0 AND expiration_date IS NOT NULL`

Movements (a ajouter si evaluation mouvement):
- `(item_id, created_at)`
- `(item_id, location_id, created_at)`
- Optionnel: `(transaction_id)` existe deja.

Threshold rules:
- indexes sur `scope`, `item_id`, `location_id`, `is_active`.

Alerts:
- indexes sur `status`, `type`, `opened_at`, `item_id/location_id` si on denormalise dans snapshot ou colonnes.

## 10) Decisions ouvertes

- Global scope: evaluation "par item" ou "total de tous les items".
  - Recommandation: evaluation par item (moins de bruit, plus actionnable).
- Movement thresholds: v1 uniquement `count`, ou v1.1 `quantity` en base unit (necessite conversion).
- Valeur: impose-t-on une devise par regle, ou refuse-t-on le calcul si multi-currency dans le scope.
