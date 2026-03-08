---
name: code-generator
description: Guide de génération de code et Manifeste de Responsabilité. Définit les standards de structure (Migrations, DTOs, Services, Resources) et de propreté du code pour les modules Laravel.
---

# Skill: Code Generator & Responsibility Manifesto

Ce skill définit la structure et la responsabilité de chaque fichier généré dans les modules. 

## 🚨 Règle d'Or : Conformité Stricte
**Tout fichier ne respectant pas strictement sa responsabilité définie ci-dessous est considéré comme hors-convention.** 
- Si un fichier "déborde", c'est soit un problème d'edge case non documenté (nécessitant une mise à jour de ce skill), soit une violation de l'architecture.
- Les exceptions à ces règles doivent être **inexistantes**.

## 💡 Standards de Code & Propreté

### 1. Style & Lisibilité
- **Lisibilité > Brièveté** : Ne pas hésiter à écrire plus de lignes si cela rend le code plus explicite et facile à maintenir.
- **Nommage (Strict)** :
    - **`snake_case`** : Données provenant du frontend (Request), colonnes de base de données, clés JSON.
    - **`camelCase`** : Variables PHP locales, propriétés de classe, noms de méthodes et fonctions.
- **Signatures** : 
    - Type de retour obligatoire pour toutes les fonctions.
    - PHPDoc obligatoire pour préciser les types de collections (ex: `Collection<int, Model>`) ou quand la signature PHP est insuffisante.

### 2. Manipulation de Données
- **Laravel Collections > Arrays** : Privilégier systématiquement les `Collection` (Laravel). Les `array` sont réservés aux transitions rapides ou aux cas de performance extrêmes.
- **Signatures de Méthodes** : Préférer `Collection|array` ou `Collection` pour les paramètres et retours.
- **Helpers Laravel** :
    - Utiliser `str()` pour les manipulations de chaînes.
    - Utiliser `data_get()` pour accéder aux données imbriquées de manière sûre.
    - Utiliser `optional()` sur le premier élément d'une chaîne d'appels pour éviter l'accumulation de `?->` (ex: `optional($user->profile)->address`).

---

## 🛠 TODO : Types de Fichiers & Responsabilités

### 1. Database (Persistance & État)
- [x] **Migrations** : Définition pure du schéma SQL (PostgreSQL).
    - **Responsabilité** : Structure (tables, colonnes, index, contraintes) ET données de référence indispensables à la production (ex: devises de base, unités de mesure fondamentales).
    - **Règle d'Or** : Celui qui exécute `migrate` doit avoir une application fonctionnelle sans dépendre de seeders.
    - **PostgreSQL & Types** :
        - Utiliser `jsonb` pour les données structurées.
        - Utiliser `text` pour les champs libres (pas de limite arbitraire).
        - Utiliser `string(limit)` uniquement si une contrainte métier stricte existe.
        - Utiliser `uuidMorphs()` / `foreignUuid()` systématiquement.
    - **Indexation** :
        - Index obligatoire sur toutes les Foreign Keys (FK).
        - Index uniques sur les combinaisons logiques (ex: `[option_id, code]`).
        - Utiliser les noms de contraintes par défaut de Laravel.
    - **Organisation** :
        - Tables préfixées par le nom du module (ex: `catalog_products`).
        - Possibilité de grouper plusieurs tables liées dans une seule migration si cohérent.
    - **Maintenance** : La méthode `down()` est remplie pour le confort en dev, mais pour corriger une erreur en environnement stable, on privilégie une nouvelle migration `up()`.
- [x] **Factories** : Génération de données de test cohérentes.
    - **Usage Unique** : Dédié aux **Feature Tests** pour simuler des états complexes rapidement.
    - **Lien** : Connecté au modèle via `SharedTraits` ou la méthode `newFactory()`.
- [x] **Seeders** : Peuplement de la base exclusivement pour le **développement et la démo**.
    - **Contenu** : Données "bouchon" (ex: iPhone 15, Catégorie "Électronique"). Aucune donnée vitale à la prod.
    - **Organisation** : Un seeder par entité (ex: `ProductSeeder`).
    - **Style (Natural Code)** : Utiliser Eloquent et `firstOrCreate()` pour garantir l'idempotence (on peut relancer le seeder sans tout casser).
    - **Orchestration** : Appelés par le `DatabaseSeeder` principal ou via `php artisan db:seed --module=...`.

### 2. Resources (Interface & Localisation)
- [x] **Lang (Traductions)** : Uniquement des couples clés/valeurs.
    - **Règle d'Or (Zéro Hardcoded Text)** : Aucun texte brut ne doit exister dans le code PHP (hors commentaires). Tout texte doit passer par `__('module::file.key')`.
    - **Usage dans Exceptions** : Les messages d'exceptions doivent systématiquement être des clés de traduction (souvent dans `exceptions.php`).
    - **Langue** : On se concentre exclusivement sur l'anglais (`en/`) pour le moment.
- [x] **Routes** : Déclarations d'endpoints.
    - **Fichier** : Toujours nommé `[module]-routes.php` pour faciliter la navigation.
    - **Préfixe URL** : Toujours `v1/[module]` (ex: `v1/catalog`).
    - **Middleware** : 
        - `api` obligatoire pour toutes les routes (inclut `throttle:api` par défaut via `bootstrap/app.php`).
        - `auth.api` pour les routes nécessitant une authentification.
        - `throttle:[name]` pour les limites spécifiques définies dans `RateLimitServiceProvider`.
    - **Nommage** : Toujours préfixé par `lahatre.[module].` (ex: `lahatre.catalog.categories.index`). Noms en minuscule avec points uniquement.
    - **Syntaxe** : Privilégier `Route::group(['as' => '...', 'prefix' => '...', 'middleware' => '...'], ...)`.
    - **Ressources & URIs** : 
        - Utiliser `Route::apiResources([...])` en tableau pour les CRUD simples.
        - Les URIs doivent être strictement RESTful : `kebab-case`, au pluriel, sans verbes d'action (ex: `categories/{category}/products` et non `categories/{category}/view-products`).
        - Si `except` ou `only` sont nécessaires, détacher vers `apiResource` ou routes manuelles.
        - Les ressources imbriquées (ex: `product.images`) sont autorisées.
    - **Formatage** : URIs en `kebab-case`. Espacement clair entre les groupes logiques de routes. Tout doit être nommé.

### 3. Src (Logique Métier & Architecture)
- [x] **Models** : Définition des relations, des casts et de la structure de la donnée.
    - **Table** : Toujours préfixée par le nom du module (ex: `catalog_`). La propriété `$table` est obligatoire.
    - **Casts** : **Obligatoire sur toutes les colonnes**.
        - `id` et `*_id` => `string`.
        - Dates => `immutable_datetime` (pour forcer `CarbonImmutable`).
        - Choix multiples => Utiliser des **Enums PHP**.
    - **Fillable** : Strictement limité aux champs modifiables par l'utilisateur/système.
    - **Relations** : Toujours typées et explicites (clés étrangères/locales spécifiées).
    - **Attributes** : Utiliser exclusivement `Illuminate\Database\Eloquent\Casts\Attribute` (syntaxe PHP 8). L'ancienne syntaxe `getXAttribute` est proscrite.
    - **Factories** : Si la factory est hors namespace standard, définir `protected static function newFactory()`.
    - **Scopes** : Utiliser des `Builder` personnalisés ou des méthodes `scopeName(Builder $query)`.
    - **Ordre de Déclaration (Strict)** :
        1. Traits (`use ...`)
        2. Propriété `$table`
        3. Propriété `$primaryKey` (si applicable)
        4. Propriété `$incrementing`
        5. Propriété `$fillable`
        6. Propriété `$casts`
        7. Méthodes `Attribute` (Getters/Setters)
        8. Méthodes de Relations
        9. Méthodes de Scopes
- [x] **Pivots** : Modèles pour les tables de liaison.
    - Doit étendre `Illuminate\Database\Eloquent\Relations\Pivot`.
    - Utilise obligatoirement `SharedTraits`.
    - **Propriétés** : Doit définir `$table`, `$casts` et `public $incrementing = false;`.
    - **Ordre** : Identique aux Models.
- [x] **Assertions** : **Unique responsabilité** : Valider une règle métier et lever une `AssertionException` si elle échoue.
    - **Langue** : Code et documentation exclusivement en **Anglais**.
    - **Nommage** : Classe `[Entity]Assertion`, méthodes commençant par `assert`.
    - **PHPDoc (Obligatoire)** :
        - **Description** : Expliquer l'intention métier (ex: "A category can only be deleted if it does not have any children").
        - **Params** : Obligatoire pour les types complexes (ex: `Collection<int, DTO>`, `array<string, mixed>`).
        - **@throws** : Lister systématiquement toutes les exceptions métier levées.
    - **Mapping Service** : Les méthodes publiques doivent généralement correspondre à une intention d'action d'un Service (ex: `assertCanSync` pour le `UnitService`).
    - **Structure** : Utiliser des méthodes `protected` pour découper les validations complexes et garder les méthodes publiques lisibles.
- [x] **Exceptions** : Définition d'erreurs métier spécifiques.
    - **Héritage** : Doit obligatoirement étendre `Lahatre\Shared\Exceptions\AssertionException`.
    - **Organisation (Strict)** : Doit être placé dans un sous-dossier lié à l'entité (ex: `Exceptions/Category/MyException.php`).
    - **Contenu** : Le constructeur doit passer un message traduit et un tableau de contexte optionnel au parent.
- [x] **DTO (Data Transfer Objects)** : **Unique responsabilité** : Transporter, typer et valider les données entrantes.
    - **Multiplicité & Contexte** : Tout comme les Resources, un modèle peut avoir plusieurs DTOs selon l'action (ex: `ProductCreateDTO` vs `ProductPriceUpdateDTO`). On évite les DTOs génériques avec trop de champs optionnels.
    - **Héritage** : Doit étendre `Lahatre\Shared\DTO\LahatreDTO`.
    - **Catégories Types** :
        1. **`[Entity][Action]DTO`** : Pour des actions spécifiques (ex: `CategorySyncDTO`).
        2. **`[Entity]FilterDTO`** : Pour les paramètres de requête (filtres, pagination, tri).
        3. **`[Entity]DataDTO`** : Suffixe utilisé pour les structures imbriquées (ex: `UnitDataDTO` dans `UnitSyncDTO`).
    - **Performance & Validation** :
        - Les `DataDTO` font la validation de format basique.
        - Le DTO parent utilise des règles personnalisées (ex: `BulkExists`) pour optimiser les vérifications en base de données.
    - **Méthodes & Casting** :
        - `casts()` : Obligatoire pour tout ce qui n'est pas `string`.
        - `defaults()` : Définir les valeurs par défaut (ex: `per_page => 15`).
        - `beforeValidation()` : Transformer/nettoyer les données brutes avant la validation.
        - **Macros `Str` (Normalisation)** : Utiliser les macros définies dans `AppServiceProvider` (via `str($val)->...`) pour normaliser les données :
            - `sanitize()` : `trim()` + `squish()` (supprime les espaces superflus).
            - `normalize()` : `sanitize()` + `lower()`.
            - `toUpper()`, `toTitle()`, `toHeadline()`, `toKebab()` : Basés sur `normalize()` avant transformation.
        - `rules()` : Règles de validation Laravel strictes.
        - `after()` : Logique de validation complexe après le passage des règles initiales.
- [x] **Rules (Validation Personnalisée)** : Logique de validation réutilisable ou complexe liée à un champ.
    - **Usage** : À privilégier si la logique de validation nécessite des requêtes SQL (ex: `BulkExists`) ou est partagée entre plusieurs DTOs.
    - **Héritage** : Doit implémenter `Illuminate\Contracts\Validation\ValidationRule`.
    - **Traduction** : Les messages d'échec doivent systématiquement être des clés de traduction (ex: `__('shared::validation.bulk_exists')`).
- [x] **Policies** : Autorisation (Gate). Vérifie si un utilisateur a le droit d'effectuer une action.
    - **Nommage** : `[Model]Policy` pour l'auto-discovery de Laravel.
    - **Simplicité Absolue** : Pas de requêtes SQL, pas de logique complexe. Uniquement des conditions booléennes simples (`&&`, `||`).
    - **Arguments** : Le premier argument est toujours `Illuminate\Contracts\Auth\Access\Authorizable $user`.
    - **Méthodes Standards** : `list`, `retrieve`, `create`, `update`, `delete`. 
    - **Soft Deletes** : Les méthodes `restore` et `forceDelete` doivent retourner `false` par défaut (considéré comme DX/Interdit sauf exception rare).
    - **Usage** : Utiliser `$user->can('permission.name')` pour mapper sur le système de permissions.
- [x] **Providers** : Enregistrement des services et configurations du module.
    - **Note** : Le chargement des routes, migrations, traductions et l'auto-discovery des policies est géré par `internachi/modular`.
    - **Responsabilités** :
        - Bindings d'interfaces ou Singletons (`app->bind`, `app->scoped`).
        - Fusion de configurations spécifiques au module (`mergeConfigFrom`).
        - Configuration de packages tiers (ex: `Sanctum::usePersonalAccessTokenModel`).
        - Enregistrement de Morph Maps pour les relations polymorphes.
        - Enregistrement de Facades ou de Subscribers d'événements.
    - **Structure** : Un seul `ServiceProvider` par module est la norme.
- [x] **Http/Controllers** : **Unique responsabilité** : Orchestration. Reçoit la requête -> Appelle le Service -> Retourne la Resource.
    - **Thin & Happy Path** : Le contrôleur doit être minimaliste. Il ne contient aucune logique métier ni validation (déléguée aux DTOs).
    - **Injection** : Le service principal du module est systématiquement injecté via le constructeur.
    - **Flux Standard** :
        1. **Autorisation** : `Gate::authorize('action', $model)`.
        2. **Validation/Input** : `DTO::fromRequest($request)` ou `DTO::forUpdate($request, $model)`.
        3. **Action** : Appel du service.
        4. **Output** : Retourne une `JsonResponse` ou une `ResourceCollection`.
    - **Statuts HTTP** : `201` pour la création, `204` pour la suppression, `200` par défaut.
- [x] **Http/Resources** : **Unique responsabilité** : Transformation et formatage final du résultat des Services.
    - **Multiplicité & Contexte** : Un modèle peut avoir plusieurs ressources selon le besoin (ex: `ProductListResource` sans variants vs `ProductDetailResource` avec variants).
    - **IDE Support** : Annotation `@mixin ModelName` obligatoire pour la PHPDoc.
    - **Relations** : 
        - Utiliser `$this->whenLoaded()` par défaut.
        - Possibilité de forcer l'inclusion de clés pour des ressources spécialisées (ex: `VariantWithUnitResource`), mais l'eager loading reste la responsabilité du Service/Controller.
    - **Collections** : Doit obligatoirement hériter de `App\Http\Resources\BaseCollection` pour uniformiser la pagination par curseur.
    - **Design Granulaire (cf. TODO)** :
        - Listes : Généralement "light" (ex: pas de variants dans `products.index`).
        - Détails : Complets (ex: variants + options dans `products.show`).
        - Éviter les boucles infinies (ex: un variant affiche son produit sans ré-afficher tous les autres variants).
- [x] **Services** : **Cœur du module**. Orchestre les Assertions, les Models et les DTOs pour réaliser une action métier.
    - **Types de Services (Distinction DX via Interfaces)** :
        1. **`StandaloneService`** (Interface) : Service principal pilotant l'action. **Responsable de la transaction** (`DB::transaction`) et des verrous (`lockForUpdate`).
        2. **`TransactionalService`** (Interface) : Logique partagée ou complexe (ex: gestion des variants au sein d'un produit). **Ne gère jamais de transaction** (déléguée au parent). Placé souvent en sous-dossier.
    - **Règles d'Or** :
        - **Explicite** : Remplissage des modèles champ par champ (interdiction formelle du `$dto->toArray()`). On veut une traçabilité claire des assignations.
        - **Optimisé** : Eager loading obligatoire pour prévenir le N+1. Utilisation privilégiée des actions Bulk (`upsert`, `updateOrCreate`) et des transactions SQL.
        - **Retour** : Retourne systématiquement une `JsonResource` ou une `ResourceCollection`.
        - **Injection** : S'injecte les `Assertions` et les `TransactionalServices` nécessaires.
- [x] **Integrations** : Communication avec l'extérieur (Paiements, Storage, API tierces).
    - **Responsabilité** : Encapsuler la complexité technique (SDK, auth, parsing) d'un service externe.
    - **Isolation** : Ne contient aucune logique métier propre à l'application. Traduit les besoins métier en appels techniques.
- [x] **Support** : Helpers agnostiques au métier (ex: `HandleGenerator`).
    - **Usage** : Utilitaires purs, statiques ou instanciables, appelables partout (Shared ou Modules). Aucune connaissance des modèles ou de la logique métier.
- [x] **Tests/Feature** : Validation du contrat de l'API et de la logique métier (Priorité n°1).
    - **Localisation** : Doit rester strictement dans le module concerné (ex: `app-modules/catalog/tests/Feature/`).
    - **Focus de Test** :
        1. **Validation (DTO)** : Tester que les données incorrectes sont rejetées (ex: `ValidationException` via les DTOs).
        2. **Assertions & Exceptions** : Vérifier que les règles métier bloquent les actions invalides et lèvent les bonnes exceptions.
        3. **Logique Service** : S'assurer que le Service effectue les transformations attendues en base de données.
        4. **Résultat** : Vérifier que le format de réponse est correct et conforme aux attentes.
    - **Framework** : **Pest** exclusivement (`it(...)`).
    - **Setup** : Utiliser `uses(TestCase::class, RefreshDatabase::class);`.
    - **Organisation** : Un fichier de test par action majeure ou groupe de routes cohérent (ex: `UnitSyncTest.php`).

---

## 📐 Manifeste de Responsabilité
1. **Un fichier = Une fonction.** Un Controller ne valide pas de données (utiliser un DTO). Un Service ne vérifie pas les permissions (utiliser une Policy).
2. **Le Service est l'orchestrateur.** Il ne connaît pas la Request HTTP, il ne manipule que des DTOs ou des Tableaux.
3. **Zéro Logique dans les Resources.** Si une donnée doit être calculée pour l'affichage, le Service ou un helper s'en charge.
