# Broadcasting avec Laravel Reverb

L'approche de communication temps réel de ce projet repose sur le broadcasting d'événements, avec **Laravel Reverb** comme implémentation principale. Reverb est en passe de devenir la solution standard de l'écosystème Laravel.

## Principes du Broadcasting

Le broadcasting via WebSockets est une méthode de communication client-serveur qui permet au serveur d'envoyer des informations aux clients dès qu'elles sont disponibles.

-   **Efficacité :** Il élimine le besoin pour le client de "sonder" (polling) constamment le serveur pour savoir si des mises à jour sont disponibles.
-   **Instantanéité :** Les clients sont informés quasi instantanément d'un changement, ce qui est crucial pour les applications collaboratives ou les flux de données en direct.
-   **Autonomie :** Utiliser Reverb nous affranchit de toute dépendance à des services tiers payants comme Pusher ou Ably, tout en gardant l'infrastructure sous notre contrôle.

## Cas d'usage : Invalidation de cache client

Pour illustrer une approche orientée design et efficacité, considérons la gestion de listes de données sur le frontend (par exemple, une liste de produits).

**Problème :** Le frontend met en cache la liste des produits (via IndexedDB, localStorage, ou une solution plus avancée comme SQLite WASM + OPFS) pour accélérer la navigation. Comment s'assurer que ce cache est à jour lorsqu'un produit est modifié en base de données ?

**Solution via Broadcasting :**

1.  Lorsqu'un produit est créé, mis à jour ou supprimé, le backend diffuse un événement privé, par exemple `ProductChanged`.
2.  Le frontend, qui écoute cet événement, peut alors réagir de plusieurs manières intelligentes :
    *   **Refresh complet :** L'événement peut simplement notifier le client que les données ont changé, l'incitant à vider son cache local et à redemander la liste complète au prochain appel API. C'est la solution la plus simple.
    *   **Mise à jour ciblée (Payload) :** L'événement peut contenir les données du produit modifié (`client_refresh` avec le nouvel objet produit). Le frontend peut alors mettre à jour, ajouter ou supprimer cet élément spécifique de son cache local sans avoir à tout recharger.
    *   **Notification d'URL :** L'événement peut envoyer une URL spécifique (ex: `/api/products/123`) à rafraîchir. Le frontend sait alors que uniquement les données liées à cette URL sont obsolètes dans son cache.

Cette approche permet de construire des interfaces utilisateur rapides et réactives, tout en optimisant les appels réseau et en garantissant la cohérence des données.
