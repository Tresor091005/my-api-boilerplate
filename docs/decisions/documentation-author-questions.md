# Lahatre ERP · Questions à l’auteur

Document de travail interne, exclu du site public. Les guides publics décrivent
les contrats actuels ; les motivations personnelles et les règles cibles restent
à confirmer ici. Les écarts de code sont séparés dans le rapport d’audit.

## Décisions confirmées

- **Nom : Lahatre ERP.** Les namespaces Lahatre restent cohérents avec cette identité.
- **Objectif : un ERP fonctionnel et cohérent.** Ses modules répondent à des besoins
  métier et peuvent aussi être repris ou adaptés pour d’autres applications et SaaS.
- **Premier parcours : vente et livraison.** Ce parcours reste à implémenter ; il
  reliera les composants clients, catalogue et stock déjà présents.
- **Licence : MIT pour tout le projet et les huit modules locaux.** Les manifests
  ont été harmonisés et un fichier LICENSE ajouté.

## Comment répondre

Tu peux répondre par groupes en citant les numéros. Une réponse courte suffit :
besoin concret, exemple métier, alternative considérée et compromis accepté.
« Limite de cette première version » est aussi une réponse utile. Rien ne demande
de justifier chaque ligne de code. Les réponses validées serviront aux pages de
décisions et aux exemples publics.

### Q01. Quel type d’entreprise vise le parcours vente et livraison ?

Le parcours est confirmé. Reste à préciser les utilisateurs : distributeur,
commerce, fabricant, prestataire mixte ou autre, taille d’équipe et contraintes
particulières (périssables, préparation, livraison partielle, multi-entrepôts).
Quel scénario réel doit définir la première commande et sa livraison ?

Source : [état du projet](https://lahatre-erp-docs.vercel.app/fr/docs/project-status).

## Questions de cadrage et choix principaux

### Q02. Comment veux-tu proposer les modules hors de cet ERP ?

Le dépôt utilise des packages Composer locaux. Souhaites-tu surtout permettre
de reprendre du code, d’installer des packages séparément ou de créer une nouvelle
application à partir de l’ERP entier ? Quels modules doivent fonctionner seuls ?

Sources : [carte des modules](../modules.md), [Composer racine](../../composer.json).

Le nom public **Lahatre ERP** est confirmé. Les noms techniques du dépôt et des packages restent stables dans cette livraison.

### Q03. Pourquoi un monolithe modulaire avec des packages Composer locaux ?

Quel besoin a motivé les frontières actuelles ? Quelles contraintes veux-tu
préserver lors de l’ajout des ventes, achats ou autres domaines ERP ?

Source : [architecture modulaire](../../.ai/rules/module-architecture.md).

### Q04. Pourquoi les mutations renvoient-elles `204` par défaut ?

`POST`, `PUT` et `PATCH` renvoient normalement un corps seulement avec
`?response=resource`. Les endpoints d’authentification ont des exceptions.
Quel usage client motive ce comportement ? Comment un client doit-il obtenir
l’identifiant créé lorsqu’il souhaite enchaîner des opérations ?

Sources : [ResponseContract](../../app-modules/shared/src/Http/Responses/ResponseContract.php),
[ResponseResponder](../../app-modules/shared/src/Http/Responses/ResponseResponder.php).

### Q05. Quel problème veux-tu résoudre avec les shapes et includes ?

Les contrats déclarent les relations obligatoires et autorisées ; le client
sélectionne un `shape` et des `include`. La sélection de champs reste prévue.
Quels écrans ou usages ont motivé cette distinction ? Quel contrôle doit rester
au serveur lorsque plusieurs clients ont des besoins différents ?

Sources : [contrats de réponse](../api/responses-and-errors.md),
[ResponseContext](../../app-modules/shared/src/Http/Responses/ResponseContext.php).

### Q06. Pourquoi rendre le tenant explicite dans les requêtes ?

Il n’existe pas de scope global universel `organization_id`. Services, assertions
et requêtes doivent rendre leur frontière d’organisation vérifiable. Quels usages
hors HTTP ou cas de données système ont conduit à ce choix ?

Source : [persistance et tenancy](../../.ai/rules/persistence-tenancy.md).

### Q07. Pourquoi sélectionner un seul MemberRole par session ?

Le parcours est `User → OrganizationMember → MemberRole`. Une session choisit
un rôle et une organisation. Pourquoi ne pas cumuler les permissions de tous les
rôles du membre ? Quel exemple métier montre l’intérêt du rôle actif ?

Source : [AuthService](../../app-modules/iam/src/Services/AuthService.php).

### Q08. Pourquoi une connexion sans mot de passe peut-elle créer un compte ?

L’OTP email sert à la connexion et à la création de compte, tandis que la création
publique d’organisation utilise un lien email distinct. Quels usages ont motivé
ces deux parcours ? Le compte sans organisation est-il une étape durable prévue ?

Sources : [EmailLoginService](../../app-modules/iam/src/Services/EmailLoginService.php),
[OrganizationOnboardingService](../../app-modules/iam/src/Services/OrganizationOnboardingService.php).

### Q09. Pourquoi le rôle par défaut reste-t-il une préférence client ?

Le token émis à la connexion ne sélectionne pas automatiquement d’organisation.
Le client doit appeler `switch-member-role`. Souhaites-tu présenter ce choix
explicite comme une règle permanente du parcours de connexion ?

Source : [AuthService](../../app-modules/iam/src/Services/AuthService.php).

### Q10. Quel modèle de délégation veux-tu pour le propriétaire et les invitations ?

Le propriétaire et son rôle Administrator sont protégés. Les invitations et
l’attribution de rôles ont des contraintes spécifiques. Quels rôles doivent
pouvoir gérer d’autres membres sans pouvoir retirer le propriétaire ?

Sources : [MemberRoleService](../../app-modules/iam/src/Services/MemberRoleService.php),
[MemberRoleDeletionService](../../app-modules/iam/src/Services/MemberRoleDeletionService.php),
[InvitationService](../../app-modules/iam/src/Services/InvitationService.php).

### Q11. Pourquoi partager l’UUID entre CatalogItem et son objet métier ?

Variantes, bundles et services partagent leur identité avec `CatalogItem`.
Le produit regroupe des variantes. Quel besoin opérationnel motive cette identité
commune ? Quelles futures fonctions ERP devront consommer un CatalogItem ?

Source : [README Catalog](../../app-modules/catalog/README.md).

### Q12. Quels cas métier doivent couvrir les bundles ?

Les composants admis sont actuellement des variantes. Quelles activités motivent
l’assemblage et le désassemblage ? Les bundles imbriqués ou les composants de
service font-ils partie du périmètre futur ?

Source : [BundleStockOperationService](../../app-modules/catalog/src/Services/BundleStockOperationService.php).

### Q13. Pourquoi répartir le coût d’un désassemblage par quantité de base ?

La répartition utilise les quantités de base des composants et affecte le reste
entier au dernier composant. Quel besoin motive cette règle, notamment si les
composants ont des unités différentes ? S’agit-il de la règle cible ou d’une
première version à préciser ?

Source : [BundleStockOperationService](../../app-modules/catalog/src/Services/BundleStockOperationService.php).

### Q14. Pourquoi les opérations bundle n’ont-elles pas le cycle d’annulation des transferts ?

Les transferts de stock peuvent être terminés puis annulés par un mouvement
inverse. Le cycle bundle utilise un brouillon, un snapshot de composition et une
complétion. Quelle politique métier souhaites-tu pour annuler un assemblage ?

Sources : [BundleStockOperationService](../../app-modules/catalog/src/Services/BundleStockOperationService.php),
[StockTransferService](../../app-modules/catalog/src/Services/StockTransferService.php).

### Q15. Pourquoi le reste de coût d’un lot part-il avec la première sortie ?

Une sortie partielle consomme tout `cost_remainder` encore présent sur le lot.
Quel exemple de valorisation motive cette convention plutôt qu’une répartition
progressive ?

Source : [InventoryService](../../app-modules/inventory/src/Services/InventoryService.php), méthode `applyDeduction`.

### Q16. Une régularisation doit-elle pouvoir remettre le stock à zéro ?

Le code actuel traite une quantité cible strictement positive et rejette une
régularisation sans changement. La remise à zéro est-elle volontairement réservée
à une sortie ou constitue-t-elle une limite de cette première version ?

Sources : [TransactionValidator](../../app-modules/inventory/src/Validation/TransactionValidator.php),
[InventoryService](../../app-modules/inventory/src/Services/InventoryService.php).

### Q17. Pourquoi ignorer les mouvements d’articles non suivis ?

Les mouvements dont `stock_tracking_enabled=false` sont retirés avant validation.
Une transaction peut alors rester sans mouvement. Quel flux métier doit pouvoir
mélanger articles suivis et non suivis ? Que représente une transaction vide ?

Source : [TransactionValidator](../../app-modules/inventory/src/Validation/TransactionValidator.php).

### Q18. Pourquoi imposer la compatibilité entre stratégie et péremption ?

Le code ne se contente pas d’un choix par défaut : Manual reste possible. Il impose une compatibilité
entre la stratégie et `is_expirable`. Quel risque métier veux-tu éviter ?

Source : [TransactionValidator](../../app-modules/inventory/src/Validation/TransactionValidator.php).

### Q19. Quelle plus petite unité faut-il choisir pour chaque famille d’articles ?

Le stock est persisté en unités de base entières. Les quantités décimales sont
acceptées seulement si leur conversion produit un entier. Quels exemples réels
doivent guider les utilisateurs pour choisir une unité de base assez fine ?

Source : [introduction Inventory](../../app-modules/inventory/docs/getting-started.md).

### Q20. Pourquoi le quota Library exclut-il les fichiers en corbeille ?

Les fichiers supprimés logiquement ne comptent plus dans le quota utilisateur,
mais leurs octets restent stockés jusqu’à la purge. Quelle politique de capacité
et de rétention souhaites-tu expliquer ?

Sources : [FileService](../../app-modules/library/src/Services/FileService.php),
[configuration Library](../../app-modules/library/config/library.php).

### Q21. Quels volumes et usages motivent l’upload synchrone et les limites Library ?

Les lots d’upload sont traités dans la requête et les objets sont nettoyés en cas
d’échec SQL. Quels volumes cibles ont conduit aux limites de lot, taille, profondeur
et nombre d’enfants ? Quand envisagerais-tu une préparation asynchrone ?

Sources : [FileService](../../app-modules/library/src/Services/FileService.php),
[configuration Library](../../app-modules/library/config/library.php).

### Q22. Pourquoi les images passent-elles par des slots privés typés ?

Catalog expose `main` et `gallery`, Customer une photo de profil. Le contenu lié
est autorisé par le parent métier. Quels besoins justifient ce modèle, et quels
fichiers pourraient éventuellement être publics à l’avenir ?

Sources : [HasFileSlots](../../app-modules/library/src/Contracts/HasFileSlots.php),
[README Catalog](../../app-modules/catalog/README.md).

### Q23. Que représente l’identifiant d’une entreprise cliente ?

`identification_number` est obligatoire pour un client `company`. S’agit-il d’un
identifiant fiscal, administratif ou d’un identifiant métier configurable selon
le pays ? Quels formats et pays doivent être couverts ?

Source : [requête Customer](../../app-modules/customer/src/Http/Requests/CustomerRequest.php).

### Q24. Quel parcours veux-tu rendre immédiatement reproductible dans Bruno ?

La collection actuelle contient 69 requêtes, principalement Auth, IAM et Catalog,
des scripts de récupération de variables, des variables de contexte. Les anciens snapshots et UUID
locaux ont été retirés. Quel scénario métier doit servir de parcours guidé de référence ?

Source : [collection OpenCollection](../../api-client/opencollection.yml).

### Q25. Bruno doit-il devenir une suite automatique ou rester un client d’exploration ?

Les requêtes lues ne déclarent pas encore de tests `runtime.tests`. Les tests
Pest couvrent les contrats applicatifs. Souhaites-tu des assertions Bruno et des
scénarios CI, ou un guide de manipulation manuelle avec résultats attendus ?

Sources : [api-client](../../api-client), [règles de tests](../../.ai/rules/testing.md).

## Identité, organisation et référentiels

### Q26. Les permissions directes sur MemberRole font-elles partie du contrat public ?

Les tests accordent parfois une permission directement à l'attribution pour les Gates, tandis que `AuthService::currentPermissions()` renvoie `getPermissionsViaRoles()`. Faut-il documenter uniquement les permissions héritées, ou permettre aussi des exceptions individuelles ? Sources : `app-modules/iam/src/Services/AuthService.php`, `tests/Feature/Integration/IamPermissionIndexIntegrationTest.php`, `IamMemberRoleManagementIntegrationTest.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q27. Pourquoi garder `is_active` à trois niveaux indépendants ?

Quels usages distinguent suspension du membre, suspension d'une attribution et suspension du rôle partagé ? Le rétablissement de l'accès avec le même token est-il voulu ? Sources : `src/Models/{OrganizationMember,MemberRole,Role}.php`, `src/Auth/AuthContext.php`, `tests/Feature/Integration/IamActiveContextIntegrationTest.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q28. Pourquoi conserver une préférence dont l'accès est suspendu ou supprimé logiquement ?

Le code la conserve pour réactivation et la FK la remet à null uniquement lors de la suppression physique. Est-ce un historique utile ou une préférence à nettoyer ? Source : `tests/Feature/Integration/IamDefaultMemberRoleIntegrationTest.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q29. Pourquoi les rôles intégrés sont-ils réservés au provisioning et exclus des invitations et des attributions ordinaires ?

Comment une organisation accorde-t-elle une administration déléguée complète à une autre personne ? Sources : `src/Enums/SysRole.php`, `src/Services/OrganizationOnboardingService.php`, `src/Http/Requests/{InvitationRolesRequest,MemberRoleCreateRequest}.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q30. Les rôles inactifs doivent-ils pouvoir être proposés et attribués ?

Les filtres de résolution exigent le bon tenant, le bon guard, un rôle custom et non supprimé, sans exiger `is_active=true`. L'attribution créée n'est alors pas sélectionnable jusqu'à réactivation. Sources : `src/Services/{InvitationService,MemberRoleService}.php` et leurs Form Requests.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q31. Le propriétaire constitue-t-il une protection permanente ou une étape avant un transfert de propriété ?

Son appartenance et son attribution Administrator sont protégées ; aucune route de transfert n'est exposée. Quel parcours métier est prévu en cas de départ ? Sources : `src/Services/{OrganizationMemberService,MemberRoleDeletionService}.php`, `app-modules/organization/src/Models/Organization.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q32. Pourquoi l'acceptation publique d'une invitation de sept jours peut-elle directement émettre une session de 24 heures ?

Le code distingue cette preuve `email_invitation` de `email_otp` et exige un OTP récent avant liaison Google. Quel compromis souhaites-tu expliquer ? Sources : `src/Services/InvitationService.php`, `src/Http/Controllers/InvitationAcceptanceController.php`, `tests/Feature/Integration/IamGoogleAuthenticationIntegrationTest.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q33. Pourquoi réutiliser une invitation tenant+email à travers annulations, expirations et départs ?

Le même identifiant porte des cycles successifs et le token tourne ; l'historique n'est pas un journal append-only. Quelle information faut-il conserver ou montrer ? Sources : `src/Services/InvitationService.php`, `database/migrations/2026_09_30_091303_create_iam_invitations_tables.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q34. La liaison Google par OTP récent est-elle stabilisée ?

Le compte doit avoir le même email, un profil complet, une adresse vérifiée et une session OTP de moins de dix minutes. Il n'y a pas de délier d'identité. Quel parcours de récupération ou de changement d'identité envisages-tu ? Sources : `src/Services/GoogleAuthService.php`, `src/Integrations/GoogleIdentityVerifier.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q35. Quel objectif métier donnent les métadonnées de sessions, la détection de l'appareil et la localisation IP approximative ?

Les IP et User-Agent restent stockés en interne mais ne sortent pas dans la ressource. Quelle durée de conservation souhaites-tu annoncer, en tenant compte du pruning différé ? Sources : `src/Services/SessionService.php`, `src/Http/Resources/SessionResource.php`, `src/Integrations/{SessionDeviceDetector,SessionGeoLocator}.php`, `bootstrap/app.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q36. Les TTL et budgets d'authentification sont-ils des valeurs finales ou des réglages à rendre configurables ?

OTP et nonce Google : dix minutes ; session : un jour ; invitation : sept jours ; code email : cinq tentatives et cinq envois par heure, avec cooldown. Sources : les services d'authentification et `app/Providers/RateLimitServiceProvider.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q37. Pourquoi la monnaie fonctionnelle est-elle fixée à la création et non modifiable par settings ?

Quel rôle jouera-t-elle quand arriveront les documents comptables et commerciaux ? Sources : `src/Services/OrganizationService.php`, `src/Http/Requests/OrganizationSettingsRequest.php`, `src/Models/Organization.php` dans `app-modules/organization/`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q38. Quel sens métier aura `ExchangeRateContext` au-delà de `default` ?

Le service contient un fallback vers Default pour un contexte absent, mais l'enum expose une seule valeur aujourd'hui. Souhaites-tu garder cette extension, et quand l'utilisateur doit-il la voir ? Sources : `src/Enums/ExchangeRateContext.php`, `src/Services/ExchangeRateService.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q39. Pourquoi autoriser la création d'un taux déjà effectif tout en interdisant ensuite de l'éditer ou le supprimer ?

Comment corriger une saisie erronée dans le passé : ajouter un nouveau taux daté, annuler un document, ou autre règle ? Source : `src/Services/ExchangeRateService.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q40. Les paires dirigées sans inversion automatique sont-elles une règle voulue ?

Qui prépare les taux et faut-il un historique de leur origine ? Aujourd'hui il n'y a pas d'import de marché ni de fournisseur externe. Source : `src/Services/ExchangeRateService.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q41. Pourquoi deux règles monétaires différentes ?

`PreciseConversion::toMinorUnits()` tronque à l'entier, tandis que la conversion de change arrondit au plus proche avec moitié vers le haut. Quel traitement attends-tu des montants négatifs et des fractions résiduelles dans les futurs documents ? Sources : `app-modules/master/src/Support/PreciseConversion.php`, `app-modules/organization/src/Services/ExchangeRateService.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q42. Quel périmètre souhaites-tu donner au fuseau d'organisation ?

Les API échangent des instants UTC, le fuseau métier se trouve dans settings et les tâches planifiées utilisent celui de l'application. Quand une règle dépend-elle du jour local de l'organisation ? Sources : `src/Models/OrganizationSetting.php`, `src/Services/OrganizationSettingsService.php`, `.ai/rules/date-time-conventions.md`, `bootstrap/app.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q43. Quel domaine impose les ratios d'unités entiers, positifs, distincts et immuables ?

Comment représenter des conversions comme pouces ↔ mètres ou une unité inférieure à la base choisie ? Pourquoi plafonner les unités custom à un million alors que certaines unités intégrées dépassent ce plafond ? Sources : `src/Assertions/UnitAssertion.php`, `src/Http/Requests/UnitUpsertRequest.php`, `src/Models/Unit.php` dans `app-modules/master/`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q44. Pourquoi les codes d'unités sont-ils uniques globalement et stables lors d'un renommage ?

Les groupes et la plupart des données sont tenant-scoped. Qui consomme ces codes en dehors du tenant ? Sources : `src/Services/UnitService.php`, `database/migrations/2026_01_01_000000_create_master_tables.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q45. Les unités intégrées (`bottle`, `bundle`, `packaging`, etc.) représentent-elles un périmètre métier précis ?

Quels exemples de stocks et d'achats rendront ces familles compréhensibles à un lecteur sans connaissance du code ? Source : `database/migrations/2026_03_07_195200_seed_master_initial_data.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q46. Pourquoi normaliser les labels en minuscules tout en gardant le slug stable après renommage ?

Sont-ils un vocabulaire technique ou des libellés affichés aux utilisateurs ? Comment souhaites-tu gérer leur traduction ? Sources : `src/Services/LabelService.php`, `src/Models/Label.php`, `src/Validation/LabelPayloadRules.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q47. Un groupe de labels doit-il être limité à cent valeurs au total ?

La création limite chaque requête, mais plusieurs appels peuvent faire dépasser cent ; le réordonnancement exige ensuite toute la liste avec une limite de cent IDs. Source : `src/Http/Requests/LabelReorderRequest.php`, `src/Services/LabelService.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q48. Pourquoi avoir un unique contact principal par parent, commun à tous les types ?

Une personne peut avoir un email et un téléphone principaux ; le schéma actuel en autorise un seul au total. Le retrait du principal ne désigne pas de successeur automatiquement. Sources : `src/Services/ContactService.php`, `database/migrations/2026_08_28_172942_create_master_contacts_table.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q49. La validation des contacts doit-elle rester générique ?

Les types sont email, phone, social et website, mais `value` est une chaîne sans validation spécifique email/URL/téléphone. Quels formats autorisés doivent être expliqués ? Source : `src/Validation/ContactPayloadRules.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q50. Le verrouillage du parent et les contraintes composites/partielles reflètent-ils des incidents ou contraintes de concurrence rencontrés ?

Un exemple concret aidera à expliquer pourquoi les contrôles sont dans le service et la base en plus des requests. Sources : `src/Services/{AddressService,ContactService,LabelService}.php` et migrations correspondantes.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q51. Les notes doivent-elles pouvoir porter sur toute cible tenant non supprimée sans exiger son droit de lecture ?

`NoteTargetResolver` valide type, ID et tenant ; il ne consulte pas le Gate retrieve de la cible. Quel usage distingue une annotation d'une permission métier sur son document ? Sources : `src/Support/NoteTargetResolver.php`, `src/Policies/NotePolicy.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q52. Pourquoi chaque réponse possède-t-elle sa propre visibilité, et pourquoi le parent n'est-il pas filtré par visibilité lors de la création ?

`resolveParent()` accepte une racine du tenant si son UUID et sa cible correspondent, sans `applyVisibility()`. Il faut décider si répondre à une racine privée connue par ID est volontaire ou doit être refusé. Sources : `src/Services/NoteService.php::resolveParent`, `tests/Feature/NotesIntegrationTest.php`. Aucun scénario testé de ce cas précis n'a été repéré.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q53. Pourquoi le partage d'une note privée est-il irréversible ?

Le code permet promotion vers mentioned ou organization, puis interdit retour au privé et changement de mode. Est-ce une garantie de continuité pour les lecteurs ? Sources : `src/Policies/NotePolicy.php::updateVisibility`, `src/Services/NoteService.php::promoteVisibility`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q54. Pourquoi limiter les fils à un niveau de réponse et préserver des placeholders de réponses supprimées ?

La racine reste non supprimable tant qu'une réponse historique existe. Quel besoin de traçabilité veux-tu montrer ? Sources : `src/Services/NoteService.php`, `src/Http/Resources/NoteResource.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q55. Pourquoi une racine ayant une expiration future ne peut-elle pas recevoir de réponse dès maintenant ?

La présence de `expires_at`, et non son dépassement, bloque les réponses. Quel cycle différencie note temporaire et fil de discussion ? Source : `src/Services/NoteService.php::resolveParent`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q56. L'accès direct à une note expirée avant sa purge est-il voulu ?

La liste exclut l'expiration ; `retrieve()` applique la visibilité mais pas cette exclusion. La purge intervient après trente jours et supprime logiquement. Sources : `src/Services/NoteService.php::{paginate,retrieve,pruneExpiredForOrganization}`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q57. Pourquoi ajouter à nouveau une mention réinitialise-t-il sa date et son état lu ?

La ligne précédente est physiquement remplacée. Est-ce un mécanisme de rappel, et faut-il prévenir le client que ce n'est pas idempotent ? Source : `src/Services/NoteService.php::upsertMentionRows`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q58. Les membres suspendus doivent-ils être mentionnables ?

Le message d'erreur parle de membres « active », mais le resolver vérifie seulement tenant et suppression logique. Source : `src/Support/NoteTargetResolver.php`, `resources/lang/en/exceptions.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

### Q59. `kind=reminder` restera-t-il une catégorie ou aura-t-il un vrai système de rappels ?

Aucun job de notification ou déclencheur temporel lié à cette catégorie n'est présent. Source : `src/Enums/NoteKind.php`, `src/Services/NoteService.php`.

Références et contexte : [rapport identity](documentation-audit/identity-report.md).

## Catalogue, clients et fichiers

### Q60. Quel cas justifie un produit obligatoirement créé avec des options ?

Les requêtes HTTP imposent au moins une option à chaque nouvelle variante ; le seeder crée pourtant un exemple de table sans options par les services. Souhaites-tu soutenir les produits à variante unique sans attribut distinctif ?
   Sources : `app-modules/catalog/src/Http/Requests/ProductRequest.php`, `ProductVariantCreateRequest.php`, `database/seeders/ProductSeeder.php`.

Références et contexte : [rapport business](documentation-audit/business-report.md).

### Q61. Pourquoi normaliser noms et valeurs d’options en minuscules ?

Sont-ils des identifiants machine à afficher tels quels, ou prévois-tu des libellés de présentation/traductions distincts ?
   Sources : `app/Providers/AppServiceProvider.php`, `app-modules/catalog/src/Http/Requests/OptionRequest.php`.

Références et contexte : [rapport business](documentation-audit/business-report.md).

### Q62. Le SKU doit-il rester réservé après suppression logique ?

L’unicité actuelle inclut les articles supprimés. Est-ce une garantie d’identité historique voulue, et quelle procédure prévoir pour corriger ou réutiliser une référence métier erronée ?
   Source : `app-modules/catalog/database/migrations/2026_08_31_091049_create_catalog_items_and_share_product_variant_identity.php`.

Références et contexte : [rapport business](documentation-audit/business-report.md).

### Q63. Pourquoi bloquer toute composition dès qu’un bundle a du stock positif ?

Souhaites-tu préserver une recette unique par identité, ou introduire plus tard des versions de composition et des stocks rattachés à une version ?
   Sources : `app-modules/catalog/src/Services/BundleService.php`, `src/Exceptions/BundleException.php`.

Références et contexte : [rapport business](documentation-audit/business-report.md).

### Q64. Que représente une prestation et un modèle de livrable dans ton ERP ?

Une checklist contractuelle, des documents à produire, des tâches ou des objets livrés ? Quelle suite reliera cette définition à un client et à l’exécution ?
    Sources : `app-modules/catalog/src/Models/Service.php`, `src/Models/ServiceDeliverableTemplate.php`, `src/Services/ServiceService.php`.

Références et contexte : [rapport business](documentation-audit/business-report.md).

### Q65. Pourquoi retirer définitivement les modèles de livrables omis lors d’une modification ?

Les futurs dossiers clients doivent-ils conserver un snapshot indépendant, ou prévoir une conservation/version du modèle d’origine ?
    Source : `app-modules/catalog/src/Services/ServiceService.php` (`synchronizeDeliverableTemplates`).

Références et contexte : [rapport business](documentation-audit/business-report.md).

### Q66. Pourquoi l’activité interdit-elle de changer les images des prestations et clients, même pour retirer un lien ?

Quelle règle métier rattache les pièces jointes à l’état actif ? Le produit n’ayant pas d’activité propre, le parcours images est asymétrique.
    Sources : `app-modules/catalog/src/Services/CatalogFileService.php`, `app-modules/customer/src/Services/CustomerFileService.php`.

Références et contexte : [rapport business](documentation-audit/business-report.md).

### Q67. La restauration automatique de l’arborescence est-elle le parcours métier voulu ?

Doit-on faire apparaître la reconstruction des dossiers et les éventuels suffixes de noms dans l’interface, ou proposer un choix de dossier destination ?
    Source : `app-modules/library/src/Services/FileService.php` (`restoreFolderChain`).

Références et contexte : [rapport business](documentation-audit/business-report.md).

### Q68. Quels types de pièces jointes veux-tu étendre après les images ?

Les slots peuvent imposer limites et MIME. Quels besoins concrets prévoir pour justificatifs, contrats, factures ou documents de livraison et leurs permissions ?
    Sources : `app-modules/library/src/Contracts/HasFileSlots.php`, `src/Contracts/LibraryInterface.php`.

Références et contexte : [rapport business](documentation-audit/business-report.md).

## Stock et composants partagés

### Q69. Lots déjà expirés.

Constat : FEFO les place en premier mais ne bloque pas leur consommation ; les filtres « expire avant » les incluent. Souhaites-tu laisser volontairement l'interdiction à chaque processus métier ? Quelle règle utiliserait un module de vente ou de préparation ? Source : `Services/InventoryService.php:1067`.

Références et contexte : [rapport foundation](documentation-audit/foundation-report.md).

### Q70. Valorisation des hausses d'inventaire.

Constat : il faut du stock fonctionnel existant pour calculer la moyenne, caller coût/devise sont supprimés. Comment souhaites-tu traiter la première découverte d'un stock physique ou une correction quand il ne reste aucun lot ? Pourquoi ignorer les champs plutôt que les rejeter ? Sources : `Services/InventoryService.php:1035`, `Validation/TransactionValidator.php:94`.

Références et contexte : [rapport foundation](documentation-audit/foundation-report.md).

### Q71. Idempotence et configuration courante.

Constat : la validation précède la recherche de transaction existante. Une reprise peut donc échouer si l'article ou l'emplacement change depuis le succès initial. Ce comportement est-il intentionnel ? La garantie attendue est-elle « même opération acceptée dans le contexte actuel » ou « restituer tout succès historique connu » ? Source : `Services/InventoryService.php:274`.

Références et contexte : [rapport foundation](documentation-audit/foundation-report.md).

### Q72. Équivalence des reprises.

Constat : « 1 » et « 1.0 » pour quantity peuvent donner des hash différents ; l'ordre des lignes et lots reste significatif. Veux-tu privilégier la fidélité au payload original ? Quels changements doivent être considérés comme une nouvelle opération ? Source : `Services/TransactionPayloadHasher.php` et `Data/MovementData.php`.

Références et contexte : [rapport foundation](documentation-audit/foundation-report.md).

### Q73. Compensations et immutabilité.

Constat : les services exposent des compensations, mais les modèles journaux ont des champs remplissables et aucune interdiction SQL générale de mise à jour. Le contrat est-il seulement de ne pas modifier le journal par les services publics, ou souhaites-tu une immutabilité plus forte à terme ? Sources : Inventory modèles transaction/movement et service de reversal.

Références et contexte : [rapport foundation](documentation-audit/foundation-report.md).

### Q74. Identité visible de numérotation.

Constat : deux clés de configuration ayant le même format rendu partagent un compteur ; restaurer un ancien format reprend son compteur. Quel usage concret motive cette identité plutôt qu'un compteur par clé/document ? Source : `app-modules/shared/src/Services/BusinessNumberService.php`.

Références et contexte : [rapport foundation](documentation-audit/foundation-report.md).

### Q75. Signification de reset.

Constat : reset valide les tokens nécessaires mais le format peut produire une période plus fréquente (yearly + MONTH), et never + YEAR change chaque année. Veux-tu expliquer reset comme une exigence minimale du format, ou devrait-il à terme représenter une cadence exacte ? Source : `BusinessNumberService.php:154`.

Références et contexte : [rapport foundation](documentation-audit/foundation-report.md).

### Q76. Alias dérivés des tables.

Constat : la même identité singulière sert aux morphs et préfixes de permissions. Pourquoi lier ces identités au nom de table plutôt qu'à un alias métier explicitement déclaré ? Comment souhaites-tu gérer un renommage de module/table ? Sources : Shared MorphMapRegistry et BasePolicy.

Références et contexte : [rapport foundation](documentation-audit/foundation-report.md).

## Installation et exploitation à préciser

### Q77. Quel déploiement cible pour partager les fichiers privés ?

L’image Docker runtime et le Compose de développement n’établissent pas à eux
seuls un stockage privé persistant commun à HTTP, workers et scheduler. Quel
hébergement et quel stockage vises-tu pour la première installation réelle ?
Le build doit-il exclure explicitement storage/app/private ?

Sources : [Dockerfile](../../docker/app/Dockerfile),
[rapport runtime](documentation-audit/root-final-report.md).

### Q78. Les diagnostics doivent-ils accepter les intégrations optionnelles non configurées ?

EnvironmentFileInspector considère les valeurs vides de l’exemple comme des
écarts, y compris Google ou MaxMind. Doctor peut donc signaler un échec alors que
la connexion email fonctionne. Souhaites-tu un contrôle de forme strict ou une
validation tenant compte des fonctions réellement activées ?

Source : [EnvironmentFileInspector](../../app/Support/EnvironmentFileInspector.php).

### Q79. Quels éléments du scaffolding Laravel veux-tu conserver ?

Les tables users/password_reset_tokens et la page welcome initiales ne sont pas
le compte IAM ni une interface ERP. Les outils Rector ciblent encore un set
Laravel 12 et PHP 8.4, avec Laravel 13 installé et PHP 8.5 sur l’hôte. Souhaites-tu
une base technique minimale assumée, ou un nettoyage distinct après ce rebranding ?
Quel runtime PHP doit définir la compatibilité annoncée ?

Sources : [Rector](../../rector.php), [schéma racine](../../database/migrations/0001_01_01_000000_create_users_table.php),
[page d’accueil API](../../resources/views/welcome.blade.php).

## Suivi

Les observations et les sources des trois audits sont conservées dans
[documentation-audit](documentation-audit/). Les réponses ne sont pas publiées
automatiquement. Elles doivent être reliées à un contrat, un exemple ou une
décision stable, puis traduites dans les deux langues.
