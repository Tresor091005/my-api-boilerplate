> Snapshot de vérification. Les corrections suivantes sont réconciliées dans le [bilan](README.md) : accès Auth, lock du frontend, nonce Google et filtre unit/list.

# Contrôle final du client et de la démo

Périmètre : tous les YAML `api-client` sauf `environments/dev.yml`, README, environnement example, tous les fichiers `frontend` et `frontend/public`, le test `ApiRequestContextIntegrationTest.php` et les guides Bruno français/anglais. Lecture seule dans le dépôt ; aucun appel HTTP et aucun accès à la base. Les noms de démonstration Catalogue ont été harmonisés dans la version inspectée.

## Résultats

- 92 fichiers YAML analysés avec yaml 2.9.1, dont 69 requêtes HTTP, 35 corps JSON non vides et 14 scripts de réponse.
- 22 variables déclarées dans example ; aucun placeholder non défini. Aucun secret dans cet environnement d’exemple.
- Un corps JSON vide sur le GET `auth/current-permissions` n’a pas été traité comme un objet JSON : il n’envoie aucune donnée utile. Ce n’est pas un défaut métier.
- Aucun snapshot de réponse enregistré ne reste dans les YAML inspectés.
- 127 contrôles purs sur les scripts : syntaxe, captures de succès, statuts 400/401/403/422/429/500 et corps absent. Les scripts ne remplacent pas les jetons/identifiants lors des erreurs HTTP testées.
- Syntaxe correcte pour `frontend/server.mjs`, `frontend/server.test.mjs`, `frontend/public/app.js`, le script inline du helper Google et le test PHP.
- Les guides EN/FR, le README et les réponses consommées suivent le même parcours : OTP, profil, contexte membre, création d’unités, produit, variantes, invitations. L’invitation publique renvoie désormais un token et un utilisateur avec `201`, conformément au contrôleur, au contrat de réponse, à Bruno et au frontend actuels.
- Les empreintes de tous les fichiers relus étaient inchangées au dernier contrôle de fraîcheur.

## Points utiles

### P3 : contrôler le nonce avant sa capture

`api-client/auth/google/challenge.yml:19` vérifie le statut et le type de `challenge_id`, mais écrit `googleNonce` sans vérifier `nonce`. Une réponse 200 avec identifiant et nonce absent écrase la variable avec `undefined` dans le contrôle pur. Le serveur normal renvoie les deux champs, donc aucun échec du parcours habituel n’est démontré. Pour respecter la promesse de captures valides, vérifier également que nonce est une chaîne avant de modifier les deux variables.

### P3 : aligner le filtre de lecture des unités

`api-client/master/unit/list.yml:8` et `:11` filtrent toujours `group=bottle`, alors que l’upsert du parcours crée `demo counting units`. La lecture proposée ne montre donc pas l’unité créée par ce parcours. Retirer le filtre ou choisir le préfixe du groupe de démonstration. Le contrat du filtre porte sur le nom du groupe, pas son UUID.

## Contrôles et limites

Le signalement initial d’une permission `catalog_option.delete` manquante dans le nouveau test a été retiré après lecture du contrôleur : la suppression d’une valeur autorise `update` sur son option parente, permission déjà accordée dans le fixture. Il n’y a pas de défaut démontré sur ce point. La garde de statut sur la capture Google `email_verification_required` est présente dans la version finale inspectée.

Le test PHP a été lu et vérifié syntaxiquement, sans exécution de ses scénarios `RefreshDatabase`. Les tests serveur Node ont été lus, sans lancement de leurs serveurs de simulation. Le frontend n’a pas été rendu dans un navigateur et aucune connexion Google réelle n’a été tentée. Ces limites évitent tout appel HTTP ou effet sur les données ; elles ne constituent pas une validation visuelle ou une exécution d’intégration complète.

Les empreintes, les 69 requêtes et le résultat détaillé des captures figurent dans `final-client-coverage.json`. Aucune correction n’a été appliquée par cet agent.
