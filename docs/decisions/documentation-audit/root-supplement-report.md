> Snapshot de vérification. Les corrections suivantes sont réconciliées dans le [bilan](README.md) : accès Auth, lock du frontend, nonce Google et filtre unit/list.

# Complément d'audit racine

Lecture seule de 21 configurations PHP supplémentaires, six migrations racine, `DatabaseSeeder.php` et les huit fichiers directement sous `tests/Feature`. `config/frontend.php` et `config/services.php` avaient déjà été lus dans le manifeste principal. Les corps exécutables de toutes les configurations ont été inspectés ; les longs commentaires génériques ont été omis dans certaines vues compactes. Les migrations, seeders et tests ont été lus intégralement.

Aucun fichier `.env` ni valeur de `api-client/environments/dev.yml` n'a été lu dans cette passe. Aucun test PHP ni commande de migration/seeding n'a été exécuté. Le manifeste ne prouve pas un résultat d'exécution.

## Correction publique à faire

Les pages `docs/site/content/docs/{en,fr}/api/routes/auth.mdx` indiquent **Public** pour plusieurs endpoints qui requièrent `auth:sanctum` :

- `GET/PATCH /v1/auth/me` ; `POST /v1/auth/logout`.
- `GET/DELETE /v1/auth/sessions` ; `DELETE /v1/auth/sessions/{session}`.
- `POST /v1/auth/switch-member-role`, `/google-identities`, `/organizations`, `/invitations/accept`.

Le groupe source dans `app-modules/iam/routes/iam-routes.php` emploie directement `auth:sanctum`, `TrackSessionActivity` et `ResolveAuthContext:user`. Ce sont des routes **Bearer**, utilisables sans contexte d'organisation sélectionné ; certaines exigent en plus le profil complet. Le générateur doit reconnaître `auth:sanctum` aussi bien que `auth.api`.

Les routes réellement publiques de ce groupe sont les défis/vérifications email et Google, les tokens/consommations d'inscription d'organisation, ainsi que l'acceptation publique d'invitation sous `/v1/iam/invitations/accept`.

## Faits essentiels pour les pages globales

| Sujet | Évidence dans la racine | Effet documentaire |
| --- | --- | --- |
| Jeu de données local | `DatabaseSeeder` crée `admin@lahatre.com`, organisation `kouri`, monnaie XOF et fuseau Africa/Porto-Novo ; appelle `permissions:discover` puis les seeders métier | On peut présenter ce jeu de démonstration partiel. Une organisation nouvellement créée reste distincte et peut être vide |
| Idempotence attendue du seeding | `DatabaseSeederTest` lance deux seedings et attend 6 produits, 9 variantes, 4 bundles, 13 CatalogItem/InventoryItem, 2 emplacements Catalog/Inventory, 3 clients et 1 transaction | Décrire cela comme un scénario testé, sans annoncer une exécution réussie pendant cet audit |
| Bases supportées | `config/database.php` conserve SQLite comme fallback et plusieurs connexions Laravel génériques ; les services/migrations métier utilisent PostgreSQL | Les choix du parcours Docker doivent explicitement régler pgsql. La présence d'autres connexions ne prouve pas le support métier de ces moteurs |
| Queues | Fallback `QUEUE_CONNECTION=database`, Redis disponible ; chaque connexion asynchrone déclare `after_commit=false` | Le parcours Horizon utilise Redis et les jobs dépendant des données validées doivent dispatcher après commit |
| Horizon | Redis `default` et `email`, jusqu'à 3 processus en local ; une tentative et timeout 60s par défaut ; production jusqu'à 10 processus, 3 tentatives et timeout 75s | La page operations actuelle est cohérente pour le profil local ; ne pas généraliser ses paramètres à la production |
| Cache et limiter | Fallback cache database, limiter database séparé ; Unit/Currency/EloquentCollection autorisés dans les classes sérialisables | Redis du parcours local ne signifie pas que les budgets d'authentification utilisent Redis |
| Mail | Fallback `MAIL_MAILER=log`, SMTP disponible, défaut SMTP 127.0.0.1:2525 | Lire un code dans Mailpit suppose les réglages SMTP locaux adéquats ; les defaults nus ne livrent pas dans Mailpit |
| Temps | `config/app.php` fixe UTC | Les horaires du scheduler sont UTC dans ce dépôt ; le fuseau d'organisation est une donnée métier distincte |
| Sessions | `config/session.php` décrit la session web, lifetime 120 minutes par défaut ; Sanctum expiration globale null | Ces valeurs ne définissent pas la durée des bearer API : IAM émet des tokens avec leur `expires_at` d'un jour |
| CORS HTTP | Une origine tirée de `FRONTEND_URL`, méthodes/headers tous, credentials false, preflight 600s | Le client utilise le bearer, pas une authentification CORS par cookie. La validation Google impose aussi JSON et le contrôle d'origine |
| Reverb | Origines `*` dans l'application Reverb, serveur 8080 par défaut, limites 10 000 octets | Les restrictions CORS HTTP ne décrivent pas la politique WebSocket Reverb. Les ports Docker sont des overrides de runtime |
| Doctor | Les checks Laravel queue-asynchronous/scheduler sont exclus ; des checks applicatifs les remplacent, avec contrôle GeoIP et environnement | Un résultat Doctor est un diagnostic complémentaire, pas une garantie de santé des workers |
| OpenAPI | Scramble analyse `v1`, export `api.json`, middleware `web` + `RestrictedDocsAccess` | `/docs/api` est documenté comme local et la restriction non locale est cohérente |

## Schéma et modèles racine

Il n'existe pas de dossier `app/Models` ni de modèle Eloquent racine. `config/auth.php` pointe vers `Lahatre\Iam\Models\User` et le modèle utilise `iam_users`.

La migration Laravel initiale crée aussi une table `users` avec password et `password_reset_tokens`. Cette table n'est pas celle du compte IAM actuel. Une migration du module crée séparément `iam_users`; une migration ultérieure retire password/remember_token de cette table IAM. Les paramètres de password broker dans `config/auth.php` restent du scaffolding et ne créent aucun endpoint de connexion par mot de passe dans le projet actuel.

La racine crée les tables techniques cache/locks, jobs/batches/failed_jobs, sessions web, Telescope et `personal_access_tokens`. Cette dernière utilise UUID primaire et UUID morphs ; les métadonnées IAM sont ajoutées par le module. `config/model-integrity.php` exclut explicitement les tables techniques de plusieurs audits et conserve des exceptions documentées pour les identités globales et les index cibles de FK composites. La documentation doit garder ces limites, comme le fait déjà `data-tenancy.mdx`, plutôt que promettre une contrainte tenant uniforme sur toute table.

Question d'entretien du projet : faut-il garder les tables et configurations password/session issues du scaffolding, ou les retirer lorsque le rebranding est stabilisé ? Leur présence ne doit pas être présentée comme une fonctionnalité IAM.

## Ce que les tests racine confirment comme contrat attendu

- `AssertionExceptionRenderTest` : erreur métier HTTP 422, message, `errors.type`, `errors.context` avec les données métier.
- `RateLimitTest` : API 90/min, auth 5/min, Google 20/min indépendant ; toutes les routes API sont throttled, limiter database. Le provider donne une clé user-ID sinon IP pour API, IP pour auth/Google.
- `MorphPermissionTest` : morph aliases comme namespaces de permission ; actions techniques parent-owned exclues, actions note pin/mention/visibility ajoutées ; seul Administrator est intégré.
- `BundleEndpointsIntegrationTest` : quantité wire dans l'unité affichée puis conversion base, CRUD/composition et stock-operation draft, validation attach/detach, bindings enfant limités au parent.
- `ServiceEndpointsIntegrationTest` : CatalogItem non stockable, templates ordonnés inclus explicitement, groupe d'unités immutable même si la valeur fournie est identique ; remplacement des templates et soft deletion du service.
- `NotesIntegrationTest` : visibilité propre aux notes/réponses, comptages filtrés, placeholders, expiration standalone, mentions et contraintes tenant, pruning interorganisations. Le cas d'une réponse à une racine privée inconnue du lecteur reste absent des scénarios lus.
- `DoctorConfigurationTest` : présence des checks spécifiques et absence des deux checks Laravel remplacés ; base GeoIP absente produit un diagnostic Fail sans prouver un échec de login.
- `DatabaseSeederTest` : fixture cohérente et idempotente attendue, avec les counts cités plus haut.

## Vérification des pages relues

Les pages API index et operations sont cohérentes avec ces configurations, en tenant compte des overrides du parcours local qu'elles annoncent. La correction du tableau d'accès Auth est l'écart factuel immédiat. Le texte first-request sur une organisation nouvellement créée vide reste correct ; préciser la fixture `kouri` améliorerait l'orientation sans confondre les deux tenants.

Les remarques du rapport principal restent valides : IAM répond parfois 403 pour un UUID d'un autre tenant, et la base GeoIP n'est nécessaire qu'à la localisation, pas à la détection d'appareil.
