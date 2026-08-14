# Feature Showcase: Unit Upsert (Bulk & Upsert)

Ce document détaille l'implémentation de la gestion des unités dans le module `master`. C'est une fonctionnalité avancée qui démontre comment gérer un upsert de données complexes de manière performante (Bulk operations) et sécurisée (Business assertions).

## 1. Architecture du Flux "Upsert"

Contrairement à un CRUD classique, les unités sont gérées par **groupes** (ex: Masse, Volume) via un endpoint unique d'upsert. Les éléments absents du payload ne sont pas supprimés.

```mermaid
sequenceDiagram
    participant Client
    participant Controller
    participant Request (UnitUpsertRequest)
    participant Data (UnitUpsertData)
    participant Service (UnitService)
    participant Assertion (UnitAssertion)
    participant Database

    Client->>Controller: POST /v1/master/units/upsert
    Controller->>Request: Payload déjà validé et normalisé
    Controller->>Data: UnitUpsertData::fromArray(validated)
    Data->>Controller: Collection de UnitData
    Controller->>Service: upsert(UnitUpsertData)
    Service->>Database: Fetch existing units of group (1 query)
    Service->>Assertion: assertCanUpsert($existingUnits)
    Assertion-->>Service: OK (or throws SpecificException)
    Service->>Database: Bulk UPSERT (1 query)
    Service->>Database: Final Fetch (1 query)
    Service-->>Client: UnitCollection (JSON)
```

## 2. Les Composants Clés

### A. La Route et le Controller
- **Route :** `POST /v1/master/units/upsert`
- **Controller :** `UnitController@upsert`
  - Utilise le `UnitPolicy@upsert` pour vérifier les permissions `units.create` ou `units.update`.
  - Délègue immédiatement au service.

### B. Form Request et Data typées
La validation HTTP et le transport vers le service sont séparés :
- **`UnitUpsertRequest`** : valide la structure complète, notamment `units.*`, et conserve les chemins d'erreur indexés.
- **`UnitUpsertData`** : construit le contrat typé du service via `::fromArray()`.
- **`UnitData`** : représente une unité individuelle (ID, nom, symbole et ratio).

**Optimisation :** 
La Form Request effectue les validations collectives en une seule requête SQL lorsque c'est possible, au lieu de déclencher une validation ou une requête par élément imbriqué.

### C. Assertions Métier (`UnitAssertion`)
Les règles métier ne sont pas dans le contrôleur, mais isolées dans des assertions documentées :
- **Limite d'activité :** Un groupe ne peut pas avoir plus de **10 unités actives** simultanément (`UnitActiveLimitException`).
- **Unicité du Ratio :** Un seul ratio de chaque valeur par groupe (`UnitRatioConflictException`).
- **Unicité de la Base :** Forcer exactement une unité avec `ratio = 1` lors de la création (`UnitBaseRequiredException`).
- **Immuabilité :** Interdiction de modifier le `ratio` ou le `code` d'une unité existante pour préserver l'historique (`UnitRatioImmutableException`).
- **Protection Système :** Impossible de modifier les unités `is_builtin` (`UnitBuiltInUpdateException`).

### D. Optimisation de la Persistance (`UnitService`)
Le service est conçu pour être "Database Friendly" :
- **Un seul SELECT** pour charger tout le groupe en mémoire au début.
- **Bulk UPSERT** : Utilisation de `Unit::upsert()`. Cela permet d'insérer les nouvelles unités et de mettre à jour les existantes en **une seule requête SQL atomique**.
- **Calculs en mémoire** : Les comparaisons pour les assertions se font sur la collection chargée, évitant des requêtes N+1.

## 3. Exceptions Spécifiques
Chaque erreur métier possède sa propre classe d'exception dans `Lahatre\Catalog\Exceptions` et son message traduit dans `resources/lang/en/exceptions.php`, permettant un retour API clair et précis.

## 4. Tests Automatisés (Pest)
La fonctionnalité est couverte par une suite de tests Pest (`app-modules/master/tests/Feature/UnitServiceTest.php`) qui valide :
- La création réussie d'un groupe.
- L'échec si le ratio 1 est manquant.
- Le blocage des doublons de ratio.
- La protection des unités de base (interdiction de désactivation).
- Le respect de la limite des 10 unités actives.

## 5. Exemple de Payload
```json
{
    "unit_group": "Mass",
    "units": [
        { "id": "uuid-existant", "name": "Gramme (Modified)", "is_active": true },
        { "name": "Milligramme", "symbol": "mg", "ratio": 1000, "is_active": true }
    ]
}
```
