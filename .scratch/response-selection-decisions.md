# ResponseSelection — décisions de design

Fiche de travail, tenue à jour au fil des échanges. Statuts : `à trancher`, `accepté`, `modifié`, `rejeté`, `reporté`.

## Contexte

Le moteur de `app-modules/shared/src/Http/Response` (commit `d6483e2b`, branche `feat/response-shape`) est un
portage quasi ligne à ligne de celui du projet d'origine (`packages/shared/src/Http/Response`, branche `experimental`).
Il n'est pas branché et aucun test ne le couvre. `.scratch/response-selection-cases/Besoins` regroupe l'usage réel
du projet d'origine par besoin distinct, réécrit dans le nouveau design (stubs dans `Api/`).

| Projet d'origine | Boilerplate |
| --- | --- |
| `ResourceManifest`, `manifest()` | `ResourceDefinition`, `define()` |
| `loads:` / `batch:` | `relations:` / `batchLoaders:` |
| `IncludeLoader` | `VirtualIncludeLoader` |
| `ResponseLoader` / `ResponseMiddleware` | `SelectionLoader` / `ResolveResponseSelection` |
| `ApiResponse::from` / `collection` / `paginated` / `none` | `item` / `list` / `cursorPage` / `noContent` |

Usage mesuré sur le projet d'origine, branche `experimental` (commit `83f19c6`, 2026-10-22) :

| Élément | Volume |
| --- | --- |
| Resources en `ApiResource` / encore en `JsonResource` | 123 / 59 |
| Champs `field('x', columns: ['x'])` (colonne du même nom) | 956, environ 80 % des déclarations |
| Champs avec `loads:` / avec `batch:` | 76 / 34 (22 `BatchLoader`) |
| Includes : total / avec `loader:` / avec `scope:` / polymorphes | 114 / 19 / 11 / 1 |
| `when:` dans les `toArray()` | 51 |
| Appels à `unmigratedRelation()` | 0 |
| `ApiResponse::from` / `paginated` / `none` / `collection` | 244 / 85 / 62 / 28 |
| `allowedStatuses:` / `meta:` | 2 / 0 |
| `new XResource(` hors Resources | 150 |

## Suivi

Reste : le lot 3.

| # | Lot | Décision | Recommandation | Statut |
| --- | --- | --- | --- | --- |
| 21 | 3 | Nom inconnu dans `fields` / `include` | 422 avant le contrôleur (`unknown_field`, `unknown_include`, chemin pointé), pour toutes les méthodes, écritures comprises ; sous un include polymorphe, nom valide si au moins une Resource possible le déclare, enfants d'une union vérifiés contre l'union | tranché (2026-10-23) |

## A. Forme de la déclaration

**4. Champ du parent redéclaré.** Tranché (2026-10-23) : exception au gel (`memberDeclaredTwice`, avec la Resource),
pour un champ comme pour un include, venu ou non de `parent::define()` ; une Resource enfant ajoute, ne remplace
jamais. Dans le projet d'origine, aucune des 9 Resources enfants ne redéclarait un membre dans `manifest()` ; trois
répétaient des clés du parent dans `toArray()` pour garder l'ordre JSON (`CashRegisterDetailResource` : `balance`
entre `cash_account_id` et `last_closed_at`, à cause des snapshots). L'ordre suit désormais la déclaration : les clés
de l'enfant viennent après celles du parent, sans option de placement.

- ~~L'ordre de `define()` ne fixe pas l'ordre JSON, seul `toArray()` le fait.~~ Remplacé : plus de `toArray()`
  dans les Resources, l'ordre JSON est l'ordre de déclaration (`parent::define()` d'abord).

## C. Includes

**13. Sous-chemin polymorphe inconnu.** Tranché (2026-10-23) : sous un include polymorphe, un nom vaut dès qu'au
moins une des Resources de `resources()` le déclare ; il s'applique aux enfants dont la Resource le déclare et est
ignoré sur les autres (`?include=subject(options)` : les variantes rendent `options`, les bundles non). Le client ne
connaît pas d'avance le type de chaque enfant. Compatible avec le point 21 : en cas de refus des noms inconnus, seul
un nom qu'aucune Resource possible ne déclare serait refusé.

**14. Entrée de l'action vers un filtre.** Tranché (2026-10-23) : rien dans le moteur. Le service, qui reçoit déjà le
filtre pour choisir les niveaux, charge aussi la relation filtrée (`->with(['stocks' => $labelled])`) ; `include('stocks')`
la reprend telle quelle, puisqu'une relation déjà chargée n'est pas rechargée. Sans filtre, le moteur charge la
relation entière, seulement si elle est demandée. `LotLabelFilter`, l'attribut de requête et le loader `ListableLots`
disparaissent. Coût accepté : avec un filtre mais sans `?include=stocks`, une requête de lots pour rien ; pour l'éviter,
on retirerait simplement le `with`.

## E. ApiResponse

**17. `meta:` de `cursorPage()`.** Retiré (2026-10-23). Seul usage connu : les comptes par `accounting_status` de
`ChequeService::list()` / `ChequeCollection`, sans route appelante, supprimés pendant la migration du projet d'origine.
Un total ou un compte sur la requête filtrée passe par un endpoint dédié au contrat typé.

**18. Attribut manquant en production.** Tranché (2026-10-23) : même règle que Lahatre
(`Model::shouldBeStrict(!app()->isProduction())`). En local et en test, attribut manquant et lazy loading lèvent
l'exception. En production, `ModelViolationReporter` (scoped, installé par `SharedServiceProvider`) signale chaque
violation une fois par requête, classe de modèle et clé, puis la laisse passer : `null` pour l'attribut manquant, la
relation chargée quand même pour le lazy loading (donnée juste, une requête de plus). Hors du moteur, la production
laisse ces protections éteintes : les gestionnaires ne touchent rien d'autre.

- Notre portage a perdu ce gestionnaire : aujourd'hui, l'exception est levée dans tous les environnements.

**21. Nom inconnu dans `fields` / `include`.** Aujourd'hui ignoré ; JSON:API impose 400 pour un include inconnu.
Voulu : 422 quand aucune Resource possible à ce niveau ne déclare le nom (compatible morph), **avant** le code
métier. Bloquant : le middleware ne connaît pas la Resource de l'action (elle n'apparaît qu'à l'appel
`ApiResponse`) ; rejeter dans `ApiResponse` arriverait après une écriture. Pistes : l'analyseur statique trouve la
Resource de la route (avec cache), ou la Resource passée en paramètre du middleware.

## F. Repris du projet d'origine, à porter

À porter avant de changer la sémantique, pour garder un filet :

- [ ] Helpers de test : `tests/Support/AssertsResponseFieldIsolation.php`, `AssertsBoundedQueries.php`,
      `AssertsResponseSnapshots.php`.
- [ ] Les 14 tests de `tests/Feature/Shared/Response` : `ApiResponseShapeTest`, `ConditionalRenderingTest`,
      `ControllerResponseContractTest`, `FieldIsolationTest`, `LoaderRobustnessTest`, `NullableResolverTest`,
      `RenderingContractHardeningTest`, `RenderingValueGuardsTest`, `ResponseContractValidationTest`,
      `ResponseControlDocumentationTest`, `ScopedIncludeFailClosedTest`, `ScopedIncludeTest`,
      `SnapshotFallbackTest`, `VirtualIncludeDependenciesTest`.
- [ ] Script `composer check:response-contracts` (validation des contrats, architecture, doc).
- [ ] Gestionnaire d'attribut manquant de `SharedServiceProvider` (point 18).

## Journal

| Date | # | Décision | Note |
| --- | --- | --- | --- |
| 2026-10-23 | — | Registre statique `ResponseDeclarationRegistry` (`Controller@method` → déclaration lue dans le source par `ResponseDeclarationAnalyzer::forAction()`, ou aucune) ; cache `bootstrap/cache/response-declarations.php` lu en production seulement, commandes `response-declarations:cache` / `:clear` branchées sur `optimize` ; analyse paresseuse ailleurs ; `ResolveResponseSelection` unique, global sur le groupe `api`, laisse passer une action sans appel `ApiResponse` ; `ApiResponse` vérifie que l'appel correspond à la déclaration | Appliqué |
| 2026-10-23 | — | `HasMiddleware` et paramètre `':no-content'` supprimés : action « 204 seulement » = seul appel `ApiResponse::noContent()` (`ResponseDeclaration::noContentOnly()`) ; règles imposées aux actions : un appel dans le corps, `XResource::class` littéral, statut littéral ou `possibleStatuses` | Appliqué |
| 2026-10-23 | — | Moteur limité aux modèles Eloquent ; objets résultat rendus par un JsonResource simple, sans sélection | Appliqué |
| 2026-10-23 | — | Absence propagée par fields: : un membre dont une dépendance manque pour un modèle est omis pour ce modèle ; BuiltValues::has() supprimé | Appliqué |
| 2026-10-23 | — | Cas regroupés par besoin distinct (`.scratch/response-selection-cases/Besoins`) ; plus de `toArray()` dans les Resources | Appliqué |
| 2026-10-23 | 1, 4 | `define()` seule source de la Resource ; rendu généré par le moteur (`toArray()` final), clés dans l'ordre de déclaration, `parent::define()` d'abord ; `field()` / `relation()` de rendu supprimés | Appliqué |
| 2026-10-23 | 19 | `when:` déclaré sur le membre (`field()`, `include()`, `virtualInclude()`), évalué avant la construction : modèle refusé ni calculé ni passé au loader, membre omis ; colonnes lues par `when:` déclarées sur le membre | Appliqué |
| 2026-10-23 | 19 | `when:` limité à la requête (`fn (Request $request)`) évalué avant le chargement ; `when:` par modèle (`fn (Model $model, Request $request)`) après le chargement | Appliqué |
| 2026-10-23 | 5, 9 | `computedBy:` et `loader:` acceptent une classe (groupée) ou une closure par modèle (pure, aucune requête) | Appliqué |
| 2026-10-23 | — | `virtualInclude()` accepte un `ResourceResolver` comme `include()` | Appliqué |
| 2026-10-23 | — | `field(morph:)` et `MorphRelations` retirés : relations par type d'un MorphTo chargées explicitement par la classe de calcul (`StockableSubjectFields`) | Appliqué |
| 2026-10-22 | 16 | `{data}` partout, `wrap` supprimé | Appliqué |
| 2026-10-23 | 5, 6, 7 | `FieldComputer::compute()` renvoie `[clé modèle => [champ => valeur]]` ; stockage dans `ResponseContext` ; pas de résolveur ; champ absent = omis | Appliqué |
| 2026-10-23 | — | `relations: ['campaign:currency_exponent']` garantit les colonnes lues sur une relation | Remplacé |
| 2026-10-23 | — | Syntaxe `relation:colonnes` et complément de colonnes retirés : une relation lue par un champ se charge entière, une relation déjà chargée est reprise telle quelle | Appliqué |
| 2026-10-23 | — | Relation déjà chargée : confiance (`loadMissing()`), pas de rechargement ; une colonne manquante échoue bruyamment au rendu ; le code métier qui précharge une relation lue par un champ la charge entière, le filtrage passe par `scope:` | Remplacé |
| 2026-10-23 | 2, 3 | Champ nu (`null` partout) = colonne de sa clé ; une dépendance déclarée = liste exhaustive ; `columns: []` = ne lit rien | Appliqué |
| 2026-10-23 | — | `many` renommé `asList` | Appliqué |
| 2026-10-23 | 1 | Arguments nommés ; `field(key, columns, relations, computedBy)`, `include()`, `morphInclude()`, `virtualInclude()` | Remplacé |
| 2026-10-23 | — | `morphInclude()` fusionné dans `include()` : un `ResourceResolver` passé à la place de la Resource rend une relation polymorphe ; même chargement Eloquent, même `relation:` et `scope:` | Remplacé |
| 2026-10-23 | 8 | Pas d'attributs PHP : les entrées d'un calcul se déclarent sur chaque champ (`columns:`, `relations:`) | Effacé |
| 2026-10-23 | 9 | Loader virtuel : valeurs par clé de parent, le moteur attache ; `asList:` | Remplacé |
| 2026-10-23 | 10 | `relation:` + `scope:` combinables | Effacé |
| 2026-10-23 | 12 | Plus de batch loader sur un include ; `chaperone()` sur la relation du modèle (références circulaires à vérifier au premier usage) | Remplacé |
| 2026-10-23 | 12 | Pas de `chaperone()` ni de traitement particulier : l'enfant déclare `relations: [parent]`, chargée entière en une requête bornée ; `chaperone()` seulement si l'enfant ne lit rien du parent au-delà de sa clé | Appliqué |
| 2026-10-23 | 5 | `FieldComputer::compute(Collection $models, array $fields, BuiltValues $built)` : `$fields` = clés demandées à ce nœud parmi celles de la classe (ou requises par `fields:`) ; seules leurs dépendances sont chargées, le calcul ne sert que `$fields` ; le moteur retient par modèle les champs calculés et ne calcule que les manquants | Appliqué |
| 2026-10-23 | 10, 19 | `scope:` et `IncludeScope` supprimés : filtre fixe ou lu dans le contexte (organisation, visibilité) = relation dédiée du modèle servie par `relation:`, contexte lu à l'appel de la relation ; contexte pas toujours valable pour le modèle = `virtualInclude()` ; droit via `when:` ; `include()` (Resource ou `ResourceResolver`) ne prend plus que `relation:` | Remplacé |
| 2026-10-23 | 1, 9 | Membres construits : `field(computedBy:)` et `virtualInclude()` partagent `columns:`, `relations:` et `fields:` (autres champs de la Resource, calculés ou non) ; construits si demandés ou requis par un `fields:` (construits, pas rendus) ; résultats rangés dans `ResponseContext`, plus de `setRelation()`, lus par `field()` / `relation()` ; ordre fixé par `fields:`, cycle = exception au gel ; champ calculé lu par `BuiltValues::get(Model, champ)` / `has()`, champ non calculé lu sur le modèle ; `field(key, columns, relations, fields, computedBy, morph)` | Remplacé |
| 2026-10-23 | — | Lazy loading interdit par le moteur pendant le chargement et le rendu, dans tous les environnements (`Model::preventLazyLoading()`, basculé comme `preventAccessingMissingAttributes()`) ; requêtes groupées explicites permises dans les classes de construction | Appliqué |
| 2026-10-23 | — | Relations nues confirmées : pas de liste de colonnes ; une relation déjà chargée reste reprise telle quelle, le filtrage passe par une relation dédiée ou un `virtualInclude()` | Appliqué |
| 2026-10-22 | 20 | Objet résultat ou endpoint composite (plusieurs collections) : `JsonResource` fixe, sans sélection ; listes autonomes = routes séparées | Effacé |
| 2026-10-22 | 15 | Mode sans sélection et `legacyRelation()` supprimés, pas de `render()` : hors `ApiResponse` = `JsonResource` | Appliqué |
| 2026-10-22 | 19 | Pas d'`authorizedBy` : droit via `when:`, filtrage via `scope:` | Remplacé |
| 2026-10-22 | 11 | Nom d'include = clé JSON snake_case, relation `Str::camel(nom)` ou `relation:` | Appliqué |
| 2026-10-23 | 17 | `meta:` retiré de `cursorPage()` : `meta` ne contient que `per_page`, `next_cursor`, `prev_cursor` ; comptes et totaux par un endpoint dédié | Appliqué |
| 2026-10-23 | 1, 9 | Deux champs servis par la même classe ne se déclarent pas dans `fields:` : la classe calcule en interne ce qu'ils partagent (`LocationVolumes` sert `occupied_volume` et `fill_rate` à partir d'une requête) | Appliqué |
| 2026-10-23 | 4 | Membre déclaré deux fois (champ ou include, `parent::define()` compris) : exception au gel avec la Resource ; plus d'écrasement silencieux | Appliqué |
| 2026-10-23 | 13 | Nom sous un include polymorphe : appliqué aux Resources possibles qui le déclarent, ignoré sur les autres ; guide et test unitaire | Appliqué |
| 2026-10-23 | 14 | Filtre venu de l'action : le service précharge la relation filtrée, l'include la reprend ; plus d'attribut de requête ni de loader virtuel | Appliqué |
| 2026-10-23 | 18 | `ModelViolationReporter` : en production, attribut manquant et lazy loading signalés une fois par requête puis tolérés ; exception en local et en test | Appliqué |
