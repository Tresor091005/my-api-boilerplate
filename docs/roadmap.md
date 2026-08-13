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
  Lecture + synchronisation de groupes/unités dans `master`, avec cache et tests.
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
