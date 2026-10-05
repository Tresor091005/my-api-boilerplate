# Lahatre ERP · Bilan de relecture documentaire

Audit du 5 octobre 2026. La documentation publique décrit le code et les contrats
actuels. Les tests ont été lus comme preuves des attentes ; la suite Pest n’a pas
été exécutée sur la base de développement. Ce rapport reste hors du site public.

## Livrables

- Site Fumadocs MDX : 72 pages par langue, huit modules, architecture, installation,
  contrats API, 161 entrées de routes et développement.
- [79 questions à l’auteur](../documentation-author-questions.md), hors publication.
- Collection Bruno existante : 69 requêtes manuelles, variables portables, captures
  après succès et corps corrigés. Les anciennes réponses locales ont été retirées. Le dernier contrôle a aussi ajouté une garde de type au nonce Google et désactivé le filtre bottle qui masquait les unités du parcours.
- Nom public Lahatre ERP et licence MIT uniformisés. Vente et livraison est le
  premier parcours cible, sans présenter des commandes/livraisons comme disponibles.

## Méthode et couverture

La relecture a été répartie entre trois agents puis réunie avec le contrôle de
l’application hôte, des routes, de Docker, du frontend de démonstration et de
Bruno. Les manifests conservent les fichiers réellement inspectés et leurs
empreintes à la lecture. Ils ne certifient pas l’exécution de leurs comportements.
Les annotations IDE générées de certains modèles ont été parcourues ; le corps
applicatif a été lu. Les fichiers binaires sont inventoriés sans prétendre une
lecture de leur format interne. Les valeurs `.env` et de l’environnement Bruno
local ne sont pas publiées.

- [IAM, Organization et Master](identity-report.md), [manifeste](identity-coverage.json).
- [Catalog, Customer et Library](business-report.md), [manifeste](business-coverage.json).
- [Inventory, Shared, architecture et stubs](foundation-report.md), [manifeste](foundation-coverage.json).
- [Configuration, schéma et tests racine](root-supplement-report.md), [manifeste](root-supplement-coverage.json).
- [Runtime, Docker et références racine](root-final-report.md), [manifeste](root-final-coverage.json).
- [Bruno et frontend de démonstration](final-client-report.md), [manifeste](final-client-coverage.json).

Ces rapports sont des snapshots de l’audit. Les demandes de licence qui y figurent
ont depuis été tranchées : MIT pour l’ensemble. Les questions redondantes ont été
réunies dans le questionnaire. Les anciens documents gardent leur contexte, mais
les README orientent désormais vers le guide public actuel.

## Écarts de code à reprendre séparément

Les constats suivants proviennent de la lecture. Ils n’ont pas été corrigés dans
ce travail de documentation et doivent être établis par un test ciblé sur une
base jetable avant modification.

| Point | Conséquence et source |
| --- | --- |
| Validation Inventory sur création produit | ProductRequest ne valide pas `variants.*.inventory`, contrairement à l’endpoint dédié de variante. Les guides utilisent le contrat accepté. [Request](../../../app-modules/catalog/src/Http/Requests/ProductRequest.php) |
| PATCH entreprise sans type | `identification_number:null` peut contourner le contrôle Request d’un client company puis violer le CHECK SQL. [Request](../../../app-modules/customer/src/Http/Requests/CustomerRequest.php) |
| ID null d’un livrable | La validation accepte null tandis que le Data est `string|MissingValue`. Le guide demande l’omission. [Data](../../../app-modules/catalog/src/Data/ServiceDeliverableTemplateData.php) |
| Filtre de dossier en corbeille | `folder_id` est accepté mais non appliqué par paginateTrash. [Service](../../../app-modules/library/src/Services/FileService.php) |
| Effacement de métadonnées HTTP | `required|nullable|array` refuse null et [] ; le service interne accepte null. [Request](../../../app-modules/inventory/src/Http/Requests/UpdateInventoryStockMetadataRequest.php) |
| Snapshot FX d’une compensation OUT | Le payload de reversal fournit exchange_metadata, absent de la whitelist validée ensuite. La conservation exacte de ce snapshot n’est pas promise. [Service](../../../app-modules/inventory/src/Services/InventoryService.php) |
| Note sur une cible / réponse privée | Le resolver vérifie tenant et suppression, sans droit de lecture de la cible ou visibilité de la racine dans resolveParent. Décider le contrat d’accès avant correction. [Service](../../../app-modules/master/src/Services/NoteService.php) |
| Scaffolds de modèles | Les stubs redeclarent incrementing/keyType malgré HasUuids, en contradiction avec ModelIntegrityTest. [Stub](../../../stubs/model.stub) |
| Scaffold Request dans controllers | Le token IlluminateHttpRequest n’est pas résolu par le générateur custom pour les formes concernées. [Générateur](../../../app-modules/shared/src/Console/Commands/Make/MakeController.php) |
| Garde des noms de routes | `[a-z0-0.-]` permet le chiffre 0 seulement, malgré le message alphanumérique. [Test](../../../tests/Feature/Architecture/RouteNamingTest.php) |
| Canal broadcasting d’exemple | Comparer deux UUID après cast int peut autoriser une identité différente. Le canal n’est pas un parcours métier implémenté ; reprendre le callback avant utilisation. [Canal](../../../routes/channels.php) |

Les questions sur arrondis, lots expirés, ajustement à zéro, numérotation,
activations, contacts principaux et rétention ne sont pas automatiquement des
bugs : elles distinguent règles volontaires et limites de la première version.

## Réconciliation des références anciennes

Le nouveau site corrige les enveloppes avec message universel et la pagination
offset anciennes ; les Resources et la pagination par curseur font foi. Il décrit
Inventory comme explicitement tenant-scoped, avec méthodes de lecture actuelles.
Les associations Library, services Catalog, UUID Inventory distincts et relation
BundleItem.component sont présents. Les claims de diffusion Reverb active, alertes
planifiées, packages autonomes et conformité production ne sont pas repris.

La suppression d’un rôle bloque toute attribution non supprimée, même inactive.
Les unités et leurs ratios relèvent de Master. La portée des tests d’architecture,
les exceptions de middleware dans certains tests et les placeholders sont expliqués.

## Vérifications réalisées

Compilation MDX/export statique, TypeScript, parité FR/EN et liens/ancres exportés. Les 50 exemples JSON sont analysables et les 494 liens de sources du dépôt désignent des chemins existants. Recherche, sélection de résultat, navigation mobile, langue conservant le chemin et captures desktop/mobile ont été contrôlées dans le navigateur collaboratif.
Les requêtes Bruno ont été vérifiées statiquement contre les routes, leurs corps
JSON et leurs scripts ; les 14 scripts passent aussi 89 contrôles purs de capture sur les versions corrigées, sans appel API. Aucune mutation API n’a été lancée. Les cinq tests Node
existants du serveur de démonstration passent. Composer validate passe, avec les
warnings préexistants sur contraintes non bornées. Le lock PHP ne change que les
licences des huit packages locaux, sans changer leurs versions.

L’export Scramble dans Docker termine sans erreur de génération, avec 54 warnings
liés notamment au contexte tenant indisponible pendant l’analyse. Il complète les
guides sans remplacer les invariants métier. Aucun déploiement ni URL publique n’a
été créé. Les réponses auteur restantes enrichiront les décisions publiques.
