> Rapport source de la relecture. Les décisions ultérieures (Lahatre ERP, vente et livraison, MIT uniforme) et les corrections documentaires sont réconciliées dans le [bilan](README.md).

# Audit documentaire — IAM, Organization et Master

Identité publique retenue : **Lahatre ERP**, ERP fonctionnel en développement dont les modules peuvent être réutilisés dans d'autres applications. Les pages décrivent les comportements présents dans le code. Les raisons personnelles de ces choix restent dans le questionnaire ci-dessous jusqu'à confirmation de l'auteur.

## Livraison

32 pages Markdown avec frontmatter, réparties entre français et anglais, sous `content/{en,fr}/modules/` :

- IAM : `index`, `email-login`, `google-login`, `organization-onboarding`, `invitations`, `roles-and-members`, `sessions`.
- Organization : `index`, `settings`, `exchange-rates`.
- Master : `index`, `units`, `currencies`, `labels`, `notes`, `addresses-and-contacts`.

Les exemples utilisent les routes `/v1/auth`, `/v1/iam`, `/v1/organization` et `/v1/master` déclarées. Les pages distinguent les contrats PHP des endpoints HTTP et signalent les fonctionnalités absentes sans les présenter comme réalisées. Les liens sont relatifs pour l'intégration au site bilingue.

## Lecture et vérification

L'audit couvre les fichiers propres aux trois modules : services, politiques, middleware, intégrations, jobs, notifications, DTO, Form Requests, ressources, modèles, configuration, routes, migrations, factories, traductions, tests et documentation locale. Les tests d'intégration IAM à la racine et `NotesIntegrationTest.php` ont aussi été lus. Le manifeste `coverage.json` distingue lecture complète, lecture du corps exécutable et lecture ciblée des migrations.

La lecture a confirmé plusieurs contrats qui ne sont pas évidents au premier regard : compte global séparé de l'appartenance, un seul contexte actif par token, indépendance des trois indicateurs d'activation, préférence de rôle sans sélection automatique, liens d'invitation réutilisant un enregistrement historique, taux immuables une fois effectifs, unités à ratios immuables, visibilité propre à chaque réponse de note, validation de l'appartenance persistée pour les composants polymorphiques.

Aucun Composer, migration, écriture en base ni test PHP n'a été exécuté par cet agent. Les scénarios de tests sont de l'évidence sur les contrats attendus, pas un résultat d'exécution actuel. Les brouillons ont été vérifiés structurellement pour les frontmatters, les blocs JSON et les liens locaux.

## Questionnaire à l'auteur

Pour chaque réponse : préciser le besoin concret, le compromis accepté et si le comportement est stabilisé ou provisoire. Un exemple métier court sera utile pour les futures pages « décisions ».

### IAM : identité et droits

1. **Quel besoin t'a conduit au triplet User → OrganizationMember → MemberRole, avec un rôle sélectionné par session ?** Quel exemple montre l'intérêt d'un contexte unique plutôt que de cumuler tous les rôles d'une personne ? Sources : `app-modules/iam/src/Auth/AuthContext.php`, `src/Services/AuthService.php`, `src/Models/MemberRole.php`.
2. **Les permissions directes sur MemberRole font-elles partie du contrat public ?** Les tests accordent parfois une permission directement à l'attribution pour les Gates, tandis que `AuthService::currentPermissions()` renvoie `getPermissionsViaRoles()`. Faut-il documenter uniquement les permissions héritées, ou permettre aussi des exceptions individuelles ? Sources : `app-modules/iam/src/Services/AuthService.php`, `tests/Feature/Integration/IamPermissionIndexIntegrationTest.php`, `IamMemberRoleManagementIntegrationTest.php`.
3. **Pourquoi garder `is_active` à trois niveaux indépendants ?** Quels usages distinguent suspension du membre, suspension d'une attribution et suspension du rôle partagé ? Le rétablissement de l'accès avec le même token est-il voulu ? Sources : `src/Models/{OrganizationMember,MemberRole,Role}.php`, `src/Auth/AuthContext.php`, `tests/Feature/Integration/IamActiveContextIntegrationTest.php`.
4. **Pourquoi la préférence `default_member_role_id` ne sélectionne-t-elle pas automatiquement le contexte ?** Le client doit-il toujours demander un choix explicite, ou peut-il activer cette préférence sans interaction ? Sources : `src/Services/AuthService.php`, `src/Http/Resources/UserResource.php`, `tests/Feature/Integration/IamDefaultMemberRoleIntegrationTest.php`.
5. **Pourquoi conserver une préférence dont l'accès est suspendu ou supprimé logiquement ?** Le code la conserve pour réactivation et la FK la remet à null uniquement lors de la suppression physique. Est-ce un historique utile ou une préférence à nettoyer ? Source : `tests/Feature/Integration/IamDefaultMemberRoleIntegrationTest.php`.
6. **Pourquoi les rôles intégrés sont-ils réservés au provisioning et exclus des invitations et des attributions ordinaires ?** Comment une organisation accorde-t-elle une administration déléguée complète à une autre personne ? Sources : `src/Enums/SysRole.php`, `src/Services/OrganizationOnboardingService.php`, `src/Http/Requests/{InvitationRolesRequest,MemberRoleCreateRequest}.php`.
7. **Les rôles inactifs doivent-ils pouvoir être proposés et attribués ?** Les filtres de résolution exigent le bon tenant, le bon guard, un rôle custom et non supprimé, sans exiger `is_active=true`. L'attribution créée n'est alors pas sélectionnable jusqu'à réactivation. Sources : `src/Services/{InvitationService,MemberRoleService}.php` et leurs Form Requests.
8. **Le propriétaire constitue-t-il une protection permanente ou une étape avant un transfert de propriété ?** Son appartenance et son attribution Administrator sont protégées ; aucune route de transfert n'est exposée. Quel parcours métier est prévu en cas de départ ? Sources : `src/Services/{OrganizationMemberService,MemberRoleDeletionService}.php`, `app-modules/organization/src/Models/Organization.php`.

### IAM : connexion et inscription

9. **Quel besoin motive la connexion exclusivement par code email et Google, avec création du compte à la première preuve valide ?** Les noms peuvent manquer et le middleware bloque ensuite les opérations métier. Est-ce le parcours final de tous les clients ? Sources : `src/Services/{EmailLoginService,GoogleAuthService,EmailAccountService}.php`, `src/Http/Middleware/EnsureProfileComplete.php`.
10. **Pourquoi conserver deux voies pour créer une organisation ?** Une session connectée peut utiliser `/v1/auth/organizations`, alors que le lien public d'inscription dure une heure et crée l'ensemble à sa consommation sans connecter la personne. Quel utilisateur ou canal utilise chaque voie ? Sources : `src/Services/OrganizationOnboardingService.php`, `src/Http/Controllers/AuthController.php`, `tests/Feature/Integration/IamOrganizationOnboardingIntegrationTest.php`.
11. **Pourquoi l'acceptation publique d'une invitation de sept jours peut-elle directement émettre une session de 24 heures ?** Le code distingue cette preuve `email_invitation` de `email_otp` et exige un OTP récent avant liaison Google. Quel compromis souhaites-tu expliquer ? Sources : `src/Services/InvitationService.php`, `src/Http/Controllers/InvitationAcceptanceController.php`, `tests/Feature/Integration/IamGoogleAuthenticationIntegrationTest.php`.
12. **Pourquoi réutiliser une invitation tenant+email à travers annulations, expirations et départs ?** Le même identifiant porte des cycles successifs et le token tourne ; l'historique n'est pas un journal append-only. Quelle information faut-il conserver ou montrer ? Sources : `src/Services/InvitationService.php`, `database/migrations/2026_09_30_091303_create_iam_invitations_tables.php`.
13. **La liaison Google par OTP récent est-elle stabilisée ?** Le compte doit avoir le même email, un profil complet, une adresse vérifiée et une session OTP de moins de dix minutes. Il n'y a pas de délier d'identité. Quel parcours de récupération ou de changement d'identité envisages-tu ? Sources : `src/Services/GoogleAuthService.php`, `src/Integrations/GoogleIdentityVerifier.php`.
14. **Quel objectif métier donnent les métadonnées de sessions, la détection de l'appareil et la localisation IP approximative ?** Les IP et User-Agent restent stockés en interne mais ne sortent pas dans la ressource. Quelle durée de conservation souhaites-tu annoncer, en tenant compte du pruning différé ? Sources : `src/Services/SessionService.php`, `src/Http/Resources/SessionResource.php`, `src/Integrations/{SessionDeviceDetector,SessionGeoLocator}.php`, `bootstrap/app.php`.
15. **Les TTL et budgets d'authentification sont-ils des valeurs finales ou des réglages à rendre configurables ?** OTP et nonce Google : dix minutes ; session : un jour ; invitation : sept jours ; code email : cinq tentatives et cinq envois par heure, avec cooldown. Sources : les services d'authentification et `app/Providers/RateLimitServiceProvider.php`.

### Organization : monnaies et temps

16. **Pourquoi la monnaie fonctionnelle est-elle fixée à la création et non modifiable par settings ?** Quel rôle jouera-t-elle quand arriveront les documents comptables et commerciaux ? Sources : `src/Services/OrganizationService.php`, `src/Http/Requests/OrganizationSettingsRequest.php`, `src/Models/Organization.php` dans `app-modules/organization/`.
17. **Quel sens métier aura `ExchangeRateContext` au-delà de `default` ?** Le service contient un fallback vers Default pour un contexte absent, mais l'enum expose une seule valeur aujourd'hui. Souhaites-tu garder cette extension, et quand l'utilisateur doit-il la voir ? Sources : `src/Enums/ExchangeRateContext.php`, `src/Services/ExchangeRateService.php`.
18. **Pourquoi autoriser la création d'un taux déjà effectif tout en interdisant ensuite de l'éditer ou le supprimer ?** Comment corriger une saisie erronée dans le passé : ajouter un nouveau taux daté, annuler un document, ou autre règle ? Source : `src/Services/ExchangeRateService.php`.
19. **Les paires dirigées sans inversion automatique sont-elles une règle voulue ?** Qui prépare les taux et faut-il un historique de leur origine ? Aujourd'hui il n'y a pas d'import de marché ni de fournisseur externe. Source : `src/Services/ExchangeRateService.php`.
20. **Pourquoi deux règles monétaires différentes ?** `PreciseConversion::toMinorUnits()` tronque à l'entier, tandis que la conversion de change arrondit au plus proche avec moitié vers le haut. Quel traitement attends-tu des montants négatifs et des fractions résiduelles dans les futurs documents ? Sources : `app-modules/master/src/Support/PreciseConversion.php`, `app-modules/organization/src/Services/ExchangeRateService.php`.
21. **Quel périmètre souhaites-tu donner au fuseau d'organisation ?** Les API échangent des instants UTC, le fuseau métier se trouve dans settings et les tâches planifiées utilisent celui de l'application. Quand une règle dépend-elle du jour local de l'organisation ? Sources : `src/Models/OrganizationSetting.php`, `src/Services/OrganizationSettingsService.php`, `.ai/rules/date-time-conventions.md`, `bootstrap/app.php`.

### Master : mesures, vocabulaire et composants partagés

22. **Quel domaine impose les ratios d'unités entiers, positifs, distincts et immuables ?** Comment représenter des conversions comme pouces ↔ mètres ou une unité inférieure à la base choisie ? Pourquoi plafonner les unités custom à un million alors que certaines unités intégrées dépassent ce plafond ? Sources : `src/Assertions/UnitAssertion.php`, `src/Http/Requests/UnitUpsertRequest.php`, `src/Models/Unit.php` dans `app-modules/master/`.
23. **Pourquoi les codes d'unités sont-ils uniques globalement et stables lors d'un renommage ?** Les groupes et la plupart des données sont tenant-scoped. Qui consomme ces codes en dehors du tenant ? Sources : `src/Services/UnitService.php`, `database/migrations/2026_01_01_000000_create_master_tables.php`.
24. **Les unités intégrées (`bottle`, `bundle`, `packaging`, etc.) représentent-elles un périmètre métier précis ?** Quels exemples de stocks et d'achats rendront ces familles compréhensibles à un lecteur sans connaissance du code ? Source : `database/migrations/2026_03_07_195200_seed_master_initial_data.php`.
25. **Pourquoi normaliser les labels en minuscules tout en gardant le slug stable après renommage ?** Sont-ils un vocabulaire technique ou des libellés affichés aux utilisateurs ? Comment souhaites-tu gérer leur traduction ? Sources : `src/Services/LabelService.php`, `src/Models/Label.php`, `src/Validation/LabelPayloadRules.php`.
26. **Un groupe de labels doit-il être limité à cent valeurs au total ?** La création limite chaque requête, mais plusieurs appels peuvent faire dépasser cent ; le réordonnancement exige ensuite toute la liste avec une limite de cent IDs. Source : `src/Http/Requests/LabelReorderRequest.php`, `src/Services/LabelService.php`.
27. **Pourquoi avoir un unique contact principal par parent, commun à tous les types ?** Une personne peut avoir un email et un téléphone principaux ; le schéma actuel en autorise un seul au total. Le retrait du principal ne désigne pas de successeur automatiquement. Sources : `src/Services/ContactService.php`, `database/migrations/2026_08_28_172942_create_master_contacts_table.php`.
28. **La validation des contacts doit-elle rester générique ?** Les types sont email, phone, social et website, mais `value` est une chaîne sans validation spécifique email/URL/téléphone. Quels formats autorisés doivent être expliqués ? Source : `src/Validation/ContactPayloadRules.php`.
29. **Le verrouillage du parent et les contraintes composites/partielles reflètent-ils des incidents ou contraintes de concurrence rencontrés ?** Un exemple concret aidera à expliquer pourquoi les contrôles sont dans le service et la base en plus des requests. Sources : `src/Services/{AddressService,ContactService,LabelService}.php` et migrations correspondantes.

### Master : notes et partage

30. **Les notes doivent-elles pouvoir porter sur toute cible tenant non supprimée sans exiger son droit de lecture ?** `NoteTargetResolver` valide type, ID et tenant ; il ne consulte pas le Gate retrieve de la cible. Quel usage distingue une annotation d'une permission métier sur son document ? Sources : `src/Support/NoteTargetResolver.php`, `src/Policies/NotePolicy.php`.
31. **Pourquoi chaque réponse possède-t-elle sa propre visibilité, et pourquoi le parent n'est-il pas filtré par visibilité lors de la création ?** `resolveParent()` accepte une racine du tenant si son UUID et sa cible correspondent, sans `applyVisibility()`. Il faut décider si répondre à une racine privée connue par ID est volontaire ou doit être refusé. Sources : `src/Services/NoteService.php::resolveParent`, `tests/Feature/NotesIntegrationTest.php`. Aucun scénario testé de ce cas précis n'a été repéré.
32. **Pourquoi le partage d'une note privée est-il irréversible ?** Le code permet promotion vers mentioned ou organization, puis interdit retour au privé et changement de mode. Est-ce une garantie de continuité pour les lecteurs ? Sources : `src/Policies/NotePolicy.php::updateVisibility`, `src/Services/NoteService.php::promoteVisibility`.
33. **Pourquoi limiter les fils à un niveau de réponse et préserver des placeholders de réponses supprimées ?** La racine reste non supprimable tant qu'une réponse historique existe. Quel besoin de traçabilité veux-tu montrer ? Sources : `src/Services/NoteService.php`, `src/Http/Resources/NoteResource.php`.
34. **Pourquoi une racine ayant une expiration future ne peut-elle pas recevoir de réponse dès maintenant ?** La présence de `expires_at`, et non son dépassement, bloque les réponses. Quel cycle différencie note temporaire et fil de discussion ? Source : `src/Services/NoteService.php::resolveParent`.
35. **L'accès direct à une note expirée avant sa purge est-il voulu ?** La liste exclut l'expiration ; `retrieve()` applique la visibilité mais pas cette exclusion. La purge intervient après trente jours et supprime logiquement. Sources : `src/Services/NoteService.php::{paginate,retrieve,pruneExpiredForOrganization}`.
36. **Pourquoi ajouter à nouveau une mention réinitialise-t-il sa date et son état lu ?** La ligne précédente est physiquement remplacée. Est-ce un mécanisme de rappel, et faut-il prévenir le client que ce n'est pas idempotent ? Source : `src/Services/NoteService.php::upsertMentionRows`.
37. **Les membres suspendus doivent-ils être mentionnables ?** Le message d'erreur parle de membres « active », mais le resolver vérifie seulement tenant et suppression logique. Source : `src/Support/NoteTargetResolver.php`, `resources/lang/en/exceptions.php`.
38. **`kind=reminder` restera-t-il une catégorie ou aura-t-il un vrai système de rappels ?** Aucun job de notification ou déclencheur temporel lié à cette catégorie n'est présent. Source : `src/Enums/NoteKind.php`, `src/Services/NoteService.php`.

## Contradictions et documentation ancienne

| Sujet | Évidence actuelle | Texte divergent / effet |
| --- | --- | --- |
| Suppression de rôle | `RoleService` bloque toute attribution non supprimée, même inactive | `docs/iam/permissions.md` parle d'absence d'attribution active ; formulation ambiguë après ajout des indicateurs d'activation |
| Unités | Upsert `group_id`/`group_name`, sans activation ni limite de dix unités | `docs/features/catalog-units.md` décrit `unit_group`, `is_active`, dix unités actives, anciens namespaces Catalog et exceptions consolidées depuis |
| Ressource d'upsert unité | Retour d'un `UnitGroupResource` avec `units` optionnel | Ancienne documentation décrit un flux `UnitCollection` |
| Base de masse | Seed : mg=1, g=1000, kg=1000000 | PHPDoc de `PreciseConversion` illustre g=1, kg=1000 ; l'exemple mathématique est valide pour un groupe différent mais ne décrit pas le seed |
| Contextes de taux | Fallback vers Default présent dans le service, une seule enum actuellement | `app-modules/organization/docs/exchange-rates.md` annonce l'absence de fallback et un futur stockage `exchange_metadata` non implémenté dans des documents comptables |
| Réponses et expiration | Toute expiration configurée bloque une réponse | Le message `expiredNoteCannotReceiveReplies` indique seulement « expired » |
| Mentions de membre | Tenant + non supprimé ; absence de filtre d'activation | Traduction de l'erreur parle de membres « active » |
| Permissions courantes | Renvoie permissions héritées via les rôles | Gates et fixtures peuvent accorder des permissions directes à l'attribution ; contrat à clarifier |

Ces divergences n'ont pas été copiées dans les pages publiques. Les anciens fichiers n'ont pas été modifiés par cet agent.

## Limites correctement présentées dans les pages

- Aucun endpoint de changement de propriétaire, de désassociation Google, de callback OAuth ou de refresh token Google.
- Aucun CRUD public des monnaies, aucune suppression/activation des unités.
- Aucun endpoint HTTP de conversion ; les conversions sont des contrats PHP internes.
- Aucun téléchargement de taux de marché ni document comptable réalisé dans ce périmètre.
- Adresses et contacts sont des composants de services/traits : leurs endpoints appartiennent aux modules hôtes.
- `reminder` ne programme aucune livraison ; l'expiration et la purge sont distinctes.
- Plusieurs fichiers de test de providers sont des placeholders TODO ; ils n'ajoutent aucune preuve de fonctionnement.

## Points à intégrer au travail global

Le questionnaire n'est pas du contenu public. Les réponses de l'auteur pourront alimenter des pages de décisions et des cas métier, après réconciliation des contradictions. Le frontend devra distinguer la preuve d'identité, le profil complet, l'appartenance et la sélection explicite du rôle. La collection Bruno doit employer les voies OTP/Google actuelles et enregistrer le bon `member_role_id` avant tout endpoint métier.
