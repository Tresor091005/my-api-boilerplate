> Rapport source de la relecture. Les décisions ultérieures (Lahatre ERP, vente et livraison, MIT uniforme) et les corrections documentaires sont réconciliées dans le [bilan](README.md).

# Audit Catalogue, Customer et Library

32 brouillons MDX prêts pour Lahatre ERP : 16 pages par langue, dont 7 Catalogue, 4 Customer et 5 Library. Les pages publiques décrivent les contrats implémentés et leurs limites. Elles n’attribuent pas de motivations non confirmées à l’auteur. Le dossier `content` contient uniquement les guides ; le présent rapport reste destiné au travail éditorial interne.

## Périmètre lu

Tous les fichiers propres aux trois modules ont été inspectés : sources, routes, configurations, migrations, ressources, tests, factories, seeders, métadonnées Composer et README existants. Les annotations générées d’aide IDE de certains modèles ont été parcourues sans analyse sémantique individuelle ; les méthodes, propriétés applicatives et relations ont été lues.

Les intégrations Catalogue/Customer/Library, BundleEndpoints et ServiceEndpoints ont été lues, ainsi que les services et règles Master nécessaires aux adresses et contacts. La liste précise, avec empreintes des fichiers, est dans `coverage.json`. Les fichiers vides `.gitkeep` sont distingués des contenus. Les versions de dépendances ne sont pas modifiées. Aucun test d’application n’a été exécuté par cet agent et aucun accès à la base n’a été nécessaire : les scénarios sont documentés à partir du code et des tests existants, sans affirmer leur réussite dans cette session.

## Points à résoudre ou confirmer avant publication

1. **La configuration Inventory de création produit diffère de celle de création de variantes.** `ProductRequest` ne définit pas de règles `variants.*.inventory`, contrairement à `ProductVariantCreateRequest`. Le service et le seeder acceptent pourtant ce champ via les Data. Les guides utilisent le PATCH de variante ou son endpoint dédié, et indiquent la limite HTTP. Décider si la création du produit doit exposer la même configuration avant d’unifier les exemples Bruno.
   Sources : `app-modules/catalog/src/Http/Requests/ProductRequest.php`, `ProductVariantCreateRequest.php`, `app-modules/catalog/database/seeders/ProductSeeder.php`.
2. **Retirer l’identifiant d’une entreprise peut contourner la validation HTTP.** `CustomerRequest::after()` vérifie seulement `input('type') === company`. Sur PATCH d’une entreprise avec uniquement `identification_number: null`, cette vérification n’intervient pas, alors que le CHECK SQL interdit une valeur nulle. Autre conséquence : envoyer explicitement `type: company` exige de renvoyer le numéro déjà stocké. La page identité décrit la limite ; ne pas promettre une réponse de validation maîtrisée sur tous ces cas.
   Sources : `app-modules/customer/src/Http/Requests/CustomerRequest.php`, `database/migrations/2026_08_28_172940_create_customer_customers_table.php`.
3. **Un nouveau modèle de livrable avec `id: null` est incohérent entre validation et Data.** `ServiceUpdateRequest` accepte un ID nullable, mais `ServiceDeliverableTemplateData` porte `string|MissingValue`, sans null. Le guide demande d’omettre l’ID entièrement pour une nouvelle entrée. Vérifier le comportement de validation/runtime dans un test ciblé avant d’annoncer null comme équivalent à l’omission.
   Sources : `app-modules/catalog/src/Http/Requests/ServiceUpdateRequest.php`, `src/Data/ServiceDeliverableTemplateData.php`.
4. **Le filtre de dossier est ignoré dans la corbeille.** `FileFilterRequest` accepte `folder_id` actif et `FileFilterData` le conserve, mais la requête de `FileService::paginateTrash()` ne l’applique pas. Le guide le dit explicitement. Décider entre retirer ce filtre du contrat de corbeille et l’implémenter.
   Sources : `app-modules/library/src/Http/Requests/FileFilterRequest.php`, `src/Services/FileService.php`.
5. **L’autorisation et les règles techniques Library sont séparées des restrictions métier.** Le contrat Library permet techniquement les liens sur des parents inactifs ; Customer/Catalog ajoutent cette restriction pour leurs routes. Les tests d’intégration couvrent cette séparation. Conserver ce point dans les exemples d’intégration afin qu’un nouveau module ne présume pas une autorisation automatique.
   Sources : `app-modules/library/src/Services/AttachmentService.php`, `app-modules/customer/src/Services/CustomerFileService.php`, `app-modules/catalog/src/Services/CatalogFileService.php`, `tests/Feature/Integration/FileAttachmentsIntegrationTest.php`.

## Questions pour l’auteur

Ces questions portent sur le pourquoi métier, pas sur un comportement déjà lisible dans le code.

1. **Pourquoi partager l’UUID entre CatalogItem et chaque variante, bundle ou prestation ?** Quels futurs parcours doivent utiliser cette identité commune : commande, tarification, recherche d’offre, facturation, autre ? Quel avantage recherches-tu par rapport à une référence polymorphe séparée ?
   Source : `app-modules/catalog/src/Services/TransactionalCatalogItemService.php`, `src/Enums/CatalogItemType.php`, migration `2026_08_31_091049_create_catalog_items_and_share_product_variant_identity.php`.
2. **Que signifie la réutilisation des modules ?** Doit-on documenter l’adaptation dans un autre projet Laravel, l’extraction en packages installables, ou les deux ? Les `composer.json` des trois modules portent `license: proprietary` et des dépendances vides ; quelle licence publique et quel niveau de compatibilité comptes-tu garantir ?
   Sources : `app-modules/{catalog,customer,library}/composer.json`.
3. **Quel cas justifie un produit obligatoirement créé avec des options ?** Les requêtes HTTP imposent au moins une option à chaque nouvelle variante ; le seeder crée pourtant un exemple de table sans options par les services. Souhaites-tu soutenir les produits à variante unique sans attribut distinctif ?
   Sources : `app-modules/catalog/src/Http/Requests/ProductRequest.php`, `ProductVariantCreateRequest.php`, `database/seeders/ProductSeeder.php`.
4. **Pourquoi normaliser noms et valeurs d’options en minuscules ?** Sont-ils des identifiants machine à afficher tels quels, ou prévois-tu des libellés de présentation/traductions distincts ?
   Sources : `app/Providers/AppServiceProvider.php`, `app-modules/catalog/src/Http/Requests/OptionRequest.php`.
5. **Le SKU doit-il rester réservé après suppression logique ?** L’unicité actuelle inclut les articles supprimés. Est-ce une garantie d’identité historique voulue, et quelle procédure prévoir pour corriger ou réutiliser une référence métier erronée ?
   Source : `app-modules/catalog/database/migrations/2026_08_31_091049_create_catalog_items_and_share_product_variant_identity.php`.
6. **Pourquoi au moins deux composants dans un bundle, et pourquoi uniquement des variantes ?** Quels kits, assemblages ou transformations veux-tu couvrir ? Les bundles imbriqués et prestations associées sont-ils exclus par métier ou simplement hors première version ?
   Sources : `app-modules/catalog/src/Assertions/BundleAssertion.php`, `src/Services/BundleService.php`, `src/Rules/ValidBundleItems.php`.
7. **Pourquoi pondérer le coût du désassemblage par quantité en unité de base ?** Pour des composants de groupes différents, un gramme et une pièce entrent dans le même dénominateur. Quels cas rendent cette répartition acceptable et quelles alternatives sont prévues pour des composants hétérogènes ?
   Source : `app-modules/catalog/src/Services/BundleStockOperationService.php` (`detachIncomingMovements`).
8. **Pourquoi les bundles n’ont-ils pas la contre-passation des transferts ?** L’assemblage a un brouillon avec snapshot puis un état terminé, sans annulation ; le transfert peut être annulé exactement. Comment traiter une erreur opérationnelle d’assemblage ou de désassemblage ?
   Sources : `app-modules/catalog/src/Services/BundleStockOperationService.php`, `src/Services/StockTransferService.php`.
9. **Pourquoi bloquer toute composition dès qu’un bundle a du stock positif ?** Souhaites-tu préserver une recette unique par identité, ou introduire plus tard des versions de composition et des stocks rattachés à une version ?
   Sources : `app-modules/catalog/src/Services/BundleService.php`, `src/Exceptions/BundleException.php`.
10. **Que représente une prestation et un modèle de livrable dans ton ERP ?** Une checklist contractuelle, des documents à produire, des tâches ou des objets livrés ? Quelle suite reliera cette définition à un client et à l’exécution ?
    Sources : `app-modules/catalog/src/Models/Service.php`, `src/Models/ServiceDeliverableTemplate.php`, `src/Services/ServiceService.php`.
11. **Pourquoi retirer définitivement les modèles de livrables omis lors d’une modification ?** Les futurs dossiers clients doivent-ils conserver un snapshot indépendant, ou prévoir une conservation/version du modèle d’origine ?
    Source : `app-modules/catalog/src/Services/ServiceService.php` (`synchronizeDeliverableTemplates`).
12. **Pourquoi l’activité interdit-elle de changer les images des prestations et clients, même pour retirer un lien ?** Quelle règle métier rattache les pièces jointes à l’état actif ? Le produit n’ayant pas d’activité propre, le parcours images est asymétrique.
    Sources : `app-modules/catalog/src/Services/CatalogFileService.php`, `app-modules/customer/src/Services/CustomerFileService.php`.
13. **Quel identifiant représente `identification_number` pour une entreprise ?** Numéro fiscal, registre légal, référence interne ? Faut-il une unicité par organisation, une normalisation par pays ou plusieurs identifiants ?
    Sources : `app-modules/customer/src/Http/Requests/CustomerRequest.php`, migration Customer.
14. **Un seul contact principal tous types confondus est-il voulu ?** Le service permet un email OU un téléphone principal, sans promotion après suppression. Qu’attend le parcours utilisateur pour les préférences de communication ?
    Source : `app-modules/master/src/Services/ContactService.php`.
15. **Pourquoi le quota mesure-t-il uniquement les fichiers actifs ?** La corbeille conserve les octets 30 jours et peut dépasser la capacité logique. Quelle politique de capacité physique, de facturation et de restauration faut-il expliquer ?
    Sources : `app-modules/library/src/Services/FileService.php`, `config/library.php`.
16. **Quel volume cible justifie des téléversements synchrones jusqu’à 100 Mio par lot ?** Le déploiement cible, les timeouts et les étapes de traitement futures détermineront si une file d’attente ou un upload direct devient nécessaire.
    Sources : `app-modules/library/src/Services/FileService.php`, `config/library.php`.
17. **La restauration automatique de l’arborescence est-elle le parcours métier voulu ?** Doit-on faire apparaître la reconstruction des dossiers et les éventuels suffixes de noms dans l’interface, ou proposer un choix de dossier destination ?
    Source : `app-modules/library/src/Services/FileService.php` (`restoreFolderChain`).
18. **Quels types de pièces jointes veux-tu étendre après les images ?** Les slots peuvent imposer limites et MIME. Quels besoins concrets prévoir pour justificatifs, contrats, factures ou documents de livraison et leurs permissions ?
    Sources : `app-modules/library/src/Contracts/HasFileSlots.php`, `src/Contracts/LibraryInterface.php`.

## Documentation existante devenue inexacte

- `docs/modules.md` indique que les associations Library seront implémentées plus tard. Les tables, contrat, service et intégrations sont maintenant présents.
- `docs/architecture/data-model.md` ne liste que variante et bundle dans CatalogItemType, alors que `catalog_service` est implémenté. Il décrit une identité Inventory partagée avec le bundle ; l’InventoryItem a en réalité son propre UUID.
- `docs/architecture/data-model.md` et `app-modules/catalog/README.md` disent que BundleItem n’expose pas de relation de composant ; `component(): MorphTo` existe et est testé.
- `docs/features/catalog-categories.md` cite des exceptions séparées qui n’existent plus et des permissions `categories.create` au lieu de `catalog_category.create`. La prose affirme une aptitude production qui ne découle pas des contrats lus.
- `docs/features/catalog-units.md` cite un namespace d’exceptions Catalogue alors que les unités appartiennent à Master. Ses détails sont du périmètre de l’agent Master.
- `docs/api/endpoints.md` couvre les images et une partie de Library, mais ne liste pas la corbeille/restauration de fichiers et laisse Customer sans véritable guide identité/coordonnées.
- `docs/roadmap.md` contient des états anciens pour les membres et d’autres domaines. La partie Catalogue manque des prestations et de leur cycle de templates ; Library et liens réutilisables méritent une ligne de statut dédiée.

## Contrôles des brouillons

Frontmatter présent, parité des slugs EN/FR, blocs JSON analysables, liens de sources correspondant à des fichiers réels et liens locaux entre brouillons contrôlés programmatiquement. Les exemples utilisent des UUID syntaxiquement valides et demandent de les remplacer par des identifiants accessibles. Aucun résultat d’exécution d’API, test de base ou certification de sécurité n’est revendiqué.


## Relecture d’intégration demandée

Les quatre pages globales EN/FR `index.mdx` et `project-status.mdx` concordent avec les contrats des trois domaines audités. L’identité ERP en développement, les limites des ventes/achats, la non-publication de packages autonomes et la distinction entre parcours Catalogue publics et écritures Inventory par services sont cohérentes. Les affirmations IAM/Organization/Infrastructure restent à vérifier par les agents responsables.

30 fichiers YAML Catalogue ont été relus, en limitant l’examen aux variables, routes, corps et scripts. Les anciennes réponses enregistrées ont été ignorées et les valeurs de l’environnement dev n’ont pas été consultées. L’environnement example contient les placeholders nécessaires. Les neuf corps JSON sont analysables ; les cinq scripts de réponse sont syntaxiquement valides. Les chemins et modes ressource correspondent aux contrôleurs. Les extractions utilisent correctement `data.id`, `data[0].id` ou `data.variants[0].id`.

Deux limites pour le guide Bruno : recréer l’option au nom fixe produit un conflit d’unicité ; appliquer le même SKU fixe de modification à plusieurs variantes produit aussi un conflit. Un lancement intégral répétable de la collection ne peut pas être promis sans stratégie de nettoyage ou choix explicite des identifiants. Les exemples existants de noms MINA/Kali/ee/sezaz/c-up/cinderella sont valides, mais une harmonisation métier faciliterait le parcours public.

Aucune modification n’a été faite aux pages intégrées ou au client Bruno. Aucun endpoint n’a été appelé. Les fichiers relus figurent aussi dans `bruno-reviewed.json`.
