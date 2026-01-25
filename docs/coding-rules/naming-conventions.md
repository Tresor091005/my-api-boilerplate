# Conventions de Style et de Nommage

Ce document définit les conventions de style et de nommage à suivre dans ce projet pour garantir la cohérence et la lisibilité du code.

## 1. Conventions Générales (PHP)

### Variables
Utilisez le `camelCase` pour toutes les variables locales.
```php
$maVariable = 'valeur';
$usersList = ['user1', 'user2'];
```

### Fonctions & Méthodes
Utilisez le `camelCase`.
```php
public function maFonction($unArgument)
{
    // ...
}
```

### Propriétés de Classes
En règle générale, utilisez le `camelCase`.
```php
class MaClasse
{
    public string $maPropriete;
}
```
**Exception :** Pour les objets qui sont une représentation directe de la base de données (modèles Eloquent) ou des DTOs mappant des payloads API, il est acceptable et même recommandé de conserver le `snake_case` pour éviter une conversion superflue.
```php
// Modèle Eloquent
$user->is_active = true;

// DTO
class UserDTO
{
    public function __construct(
        public readonly string $user_name,
        public readonly string $email_address,
    ) {}
}
```

## 2. Base de Données

Tous les noms de tables, colonnes et contraintes doivent être en `snake_case` et en anglais.
- **Tables :** Nom au pluriel (ex: `users`, `blog_posts`).
- **Colonnes :** Nom au singulier (ex: `title`, `created_at`, `is_published`).
- **Clés étrangères :** `[table_au_singulier]_id` (ex: `user_id` dans la table `posts`).

## 3. API & Payloads

Toutes les clés dans les requêtes et réponses JSON doivent être en `snake_case`, en cohérence avec les noms des colonnes de la base de données.
```json
{
  "user_name": "John Doe",
  "is_active": true,
  "created_at": "2023-10-27T10:00:00Z"
}
```

## 4. Routes

### Chemins (Paths)
Utilisez le `kebab-case` pour les segments d'URL qui contiennent plusieurs mots. Les ressources doivent être au pluriel.
- `GET /users`
- `GET /users/{user}`
- `POST /users/{user}/publish-resume`

### Noms (Names)
Utilisez la notation par points (`dot.notation`) pour nommer les routes. Cela facilite l'organisation et le référencement.
- `users.index`
- `users.show`
- `user.publish.resume`
