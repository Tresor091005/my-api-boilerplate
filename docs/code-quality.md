# Philosophie de la Qualité de Code

L'objectif de ce projet n'est pas seulement de fournir une base fonctionnelle, mais aussi de démontrer une approche rigoureuse de la qualité du code. L'utilisation d'outils d'analyse statique et de formatage est essentielle pour éviter les bugs en amont et garantir une hégémonie stylistique dans le code. Un code lisible et cohérent est plus facile à maintenir et à faire évoluer.

Cette philosophie est mise en œuvre par les outils suivants, orchestrés par un hook de pre-commit :

-   **Pint :** Assure un style de code unifié (basé sur PSR-12) sans effort manuel.
-   **Rector :** Permet des refactorings et des mises à jour de code à grande échelle de manière automatisée et sécurisée.
-   **PHPStan (Larastan) :** Détecte les erreurs et les incohérences de types avant même l'exécution du code, agissant comme un filet de sécurité.
-   **IDE Helper :** Génère des fichiers d'aide pour l'autocomplétion dans l'IDE, améliorant l'expérience de développement et la découverte du code.

L'ensemble de ces outils garantit que chaque commit respecte un haut niveau de qualité.
