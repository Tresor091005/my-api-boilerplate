## Priorité haute — sécurisation du tenant scoping des tags

- [x] Revoir `TagService::resolveOrganizationId()` : l'`organization_id` présent
  sur le modèle fourni ne doit jamais être considéré comme l'autorité de sécurité.
  Un modèle peut être mal hydraté, modifié en mémoire, construit manuellement ou
  appartenir à une autre organisation. La référence doit être l'organisation
  active (`currentOrganizationId()`), avec validation explicite de la valeur du
  modèle lorsqu'elle est disponible.
- [x] Définir le comportement des modèles qui possèdent un `organization_id` nul,
  ainsi que des modèles partiellement hydratés. Ne pas transformer silencieusement
  un modèle global ou incomplet en modèle tenant-scoped sans règle métier explicite.
  Règle retenue : un modèle taggable doit obligatoirement posséder une colonne
  `getOrganizationId(): string`; les modèles globaux ne sont pas taggables.
- [x] Refuser explicitement tout mismatch entre l'organisation active et celle du
  modèle avant toute lecture, création, modification ou association de tag.
  Couvrir ce cas par une exception métier localisée, et non par une
  `InvalidArgumentException` générique.
- [x] Vérifier l'isolation de toutes les opérations : `attach`, `detach`, `sync`
  et `syncForType`, y compris lors d'un appel direct au service hors du trait
  `InteractsWithTags` et le contrat `HasTags`. Le caller doit autoriser l'opération, mais le service doit quand même
  valider le tenant avant sa première requête tenant-owned.
- [x] Sécuriser la relation polymorphique `master_taggables`. Elle ne porte
  actuellement pas d'`organization_id` : un lien mal formé pourrait associer un
  tag de B à un modèle de A, ou exposer ce tag via `tags()`. Ajouter une preuve
  locale du tenant dans les lectures et écritures de la relation, puis évaluer
  une contrainte de schéma ou une structure de pivot permettant d'empêcher ce
  mismatch au niveau de la base.
- [x] Vérifier les scopes `withAnyTagsOfType`, `withAllTagsOfType` et
  `withoutTagsOfType`. Ils doivent rester bornés à l'organisation du modèle et
  ne pas dépendre uniquement de la confiance accordée aux liens existants dans
  le pivot.
- [x] Rendre les opérations d'écriture atomiques et sûres en concurrence : la
  séquence « rechercher puis insérer » des tags peut être exécutée par deux
  transactions simultanément. Préserver la contrainte d'unicité tenant-aware,
  utiliser une écriture atomique ou gérer proprement la collision.
- [x] Clarifier la propriété des transactions. Les méthodes publiques du service
  doivent rester sûres lorsqu'elles sont appelées sans `DB::transaction()` par
  `InteractsWithTags`, un job ou une commande; `sync` ne doit notamment pas détacher des
  liens avant d'avoir validé toutes ses préconditions.
- [ ] Ajouter des tests d'intégration couvrant : modèle d'une autre organisation,
  `organization_id` falsifié en mémoire, modèle partiellement hydraté,
  `organization_id` nul, contexte absent, appel direct au service, lien pivot
  inter-tenant, lecture par scopes et concurrence sur la création d'un même tag.
  Les cas de sécurité et de scopes sont couverts ; le scénario de concurrence
  doit encore être exécuté avec PostgreSQL disponible.

## Prochaine priorité haute — audit du tenant scoping polymorphique Inventory

- [ ] Auditer `HasInventoryItem` et `InteractsWithInventoryItem` : vérifier que
  `getOrganizationId()` est la seule source d'appartenance du modèle hôte et
  qu'aucune valeur falsifiée, partiellement hydratée ou construite en mémoire
  ne peut contourner le tenant actif.
- [ ] Auditer `HasInventoryLocation` et `InteractsWithInventoryLocation` avec
  les mêmes règles, notamment pour les modèles d'emplacement externes.
- [ ] Vérifier les opérations `create`, `createMany`, `resolve`, `update` et
  `delete`, y compris les appels directs aux services hors HTTP : contexte
  d'organisation requis, mismatch refusé avant toute requête tenant-owned et
  exception métier localisée.
- [ ] Vérifier les relations polymorphiques `inventoryItem()` et
  `inventoryLocation()` en lazy loading, eager loading et chargement par lots.
  Elles doivent rester bornées à l'organisation courante sans casser les
  relations préparées par `with()` ou `load()`.
- [ ] Vérifier l'isolation des tables `inventory_items` et
  `inventory_locations`, ainsi que des stocks, mouvements et transactions
  atteints par ces relations. Aucun lien inter-tenant ne doit être résolu ou
  exposé par un identifiant polymorphique seul.
- [ ] Clarifier la propriété des transactions et la sûreté en concurrence pour
  la création, la résolution par lots, les mises à jour et les suppressions.
  Préserver les contraintes tenant-aware et l'atomicité des opérations.
- [ ] Ajouter les tests d'intégration pour : modèle d'une autre organisation,
  organisation falsifiée en mémoire, modèle partiellement hydraté,
  organisation nulle, contexte absent, appel direct au service, lien
  polymorphique inter-tenant, eager loading, batch resolution et concurrence.

## État actuel

### Déjà exploitable

- [x] `category`
  CRUD exposé par API dans `catalog`, avec filtres, hiérarchie, assertions métier et tests de service.
- [x] `product`
  CRUD exposé par API dans `catalog`, avec catégories, variantes et chargement des relations principales.
- [x] `variant`
  CRUD exposed under `products.variants`; every variant has an inventory item,
  while inventory-owned tracking configuration remains on that item.
- [x] `option`
  CRUD exposé par API dans `catalog`, avec gestion des valeurs.
- [x] `option value`
  CRUD exposé par API sous `options.values`.
- [x] `unit`
  Lecture + upsert de groupes/unités dans `master`, avec cache et tests.
- [x] `currency`
  Lecture listée dans `master`.
- [x] `tag`
  Attachement/détachement/synchronisation par type + scopes de lecture mono-type + tests.
- [x] `inventory item`
  Création, update, suppression et lecture disponibles via `inventory`.
- [x] `inventory stock`
  Transactions `in`, `out`, `transfer`, `adjustment` + vues de stock, lots, mouvements, valeur.
- [x] `permission`
  Disponible pour la lecture du contexte courant via `current-permissions`.
- [x] `user`
  Disponible côté auth pour `login`, `me`, `logout`, reset password, switch de rôle membre.

### Partiellement exploitable

- [~] `organization`
  Service minimal disponible, lookup par id disponible, mais pas de vrai CRUD métier exposé.
- [~] `member`
  Concept présent dans IAM via `organizationMemberships` et `MemberRole`, mais pas de service dédié ni d'API de gestion.
- [~] `role`
  Concept présent dans IAM et exploité pour l'auth context, mais pas de service CRUD métier dédié.

### Pas encore industrialisé comme brique métier autonome

- [ ] `price`
  Le modèle et les relations existent déjà, mais il n'y a pas encore de service/API générique pour assigner des prix à n'importe quel modèle.
- [ ] `customer`
- [ ] `supplier`
- [ ] `procurement`
- [ ] `order`
- [ ] concept métier de location de stock
  Le moteur inventory sait gérer un `HasInventoryLocation`, mais il manque encore le domaine métier concret à exposer.


## TODO Structure Produit / Prix

- [ ] Créer une brique de prix réutilisable sur le même principe que `inventory`, pour pouvoir attacher des prix à n'importe quel modèle.
- [ ] Définir le contrat technique du modèle "pricable" : interface, trait, relations et service transverse.
- [ ] Gérer plusieurs prix par modèle selon un type ou une finalité métier claire.
- [ ] Clarifier le périmètre autour des prix:
  prix de vente, prix d'achat, prix promo, prix par canal, prix par devise, prix datés si nécessaire.
- [ ] Décider si le référentiel prix doit vivre dans `catalog`, `master` ou un module dédié.
- [ ] Ajouter l'API et les tests de cette brique avant de la consommer dans d'autres domaines.

## TODO Partenaires & Flux

- [ ] Introduire `customer`.
- [ ] Introduire `supplier`.
- [ ] Introduire `procurement`.
- [ ] Introduire `order`.
- [ ] Définir les liens métier:
  `supplier -> procurement`, `customer -> order`.
- [ ] Décider à quel moment ces flux commencent à impacter officiellement inventory et pricing.

## TODO Location de Stock

- [ ] Nommer correctement le concept métier.
- [ ] Comparer les options de nommage:
  `warehouse`, `store`, `location`, `site`, `depot`, `stock_location`.
- [ ] Éviter le mot `store` si le risque de confusion avec shop / POS / storefront est trop élevé.
- [ ] Éviter le mot `warehouse` si le concept doit aussi couvrir des lieux plus petits:
  rayon, van, réserve, point de retrait, atelier, boutique.
- [ ] Si le concept reste générique, privilégier un terme métier neutre du type `stock location`.
- [ ] Une fois le nom choisi, créer le domaine métier qui implémente `HasInventoryLocation` proprement au-dessus du moteur inventory.

## Points déjà repérés dans le code

- [ ] `product`: ajouter le filtre par catégorie.
- [x] `product variant`: stock tracking and deduction configuration are owned by
  `inventory_items`; disabling tracking is blocked while active stock remains.
- [ ] `inventory`: review HTTP authorization coverage for every exposed route,
  including read endpoints, nested resources, policies, permissions, and
  organization boundaries.
- [ ] `inventory`: define the update pattern for `InventoryItem` and
  `InventoryLocation`, including parent-owned updates versus standalone
  inventory endpoints.
- [ ] `product variant`: define the sales/order integration that will trigger
  inventory movements.
- [ ] `unit`: couvrir la suppression sécurisée de groupes/unités déjà utilisés.
- [ ] `inventory`: ajouter les événements métier prévus dans `plan.md`.
- [ ] `inventory`: décider si la distribution d'un transfert doit être imposée par l'utilisateur.
- [ ] `iam`: compléter ce qui manque pour aller au-delà de l'auth pure si on veut gérer `user/member/role/permission` comme un vrai domaine applicatif.



--- 

# Points importants 

- activity logging global
