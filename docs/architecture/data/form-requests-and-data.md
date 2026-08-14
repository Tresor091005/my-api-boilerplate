# Form Requests et classes Data

Les entrées HTTP et les contrats de service sont deux responsabilités distinctes.

- Une Form Request valide et normalise le payload HTTP.
- Une classe Data transporte des valeurs déjà validées vers le service.
- Le Controller conserve l'autorisation avec les Gates et Policies.
- Le Service ne dépend ni de HTTP, ni du Validator, ni d'une Request.

## Flux standard

```php
public function update(CategoryRequest $request, Category $category): JsonResponse
{
    Gate::authorize('update', $category);

    $data = CategoryData::fromArray(
        $request->validated(),
        missingFields: ['name', 'parent_id', 'is_active'],
    );

    return response()->json($this->categoryService->update($category, $data));
}
```

Une Request injectée est validée avant l'exécution du Controller. Le Gate reste volontairement dans le Controller : l'autorisation ne doit pas être cachée dans la validation.

## Nommage

Les Form Requests gardent toujours le suffixe `Request` :

- `CategoryRequest` si store et update partagent réellement le même contrat ;
- `StoreOptionValueRequest` et `UpdateOptionValueRequest` lorsque les shapes divergent ;
- `CategoryFilterRequest`, `LoginRequest`, `UnitUpsertRequest` selon l'intention.

Les objets de service utilisent le suffixe `Data` :

- `CategoryData` ;
- `CategoryFilterData` ;
- `LoginData` ;
- `UnitUpsertData`.

Il ne faut pas créer automatiquement une paire store/update. Une classe représente un shape cohérent ; elle est divisée lorsque les branches conditionnelles rendent le contrat difficile à lire.

## Form Requests

La Request contient :

- `rules()` pour les règles Laravel ;
- `prepareForValidation()` uniquement pour le nettoyage propre aux champs concernés ;
- `after()` pour les validations HTTP complexes et indexées ;
- les règles liées au modèle de route, par exemple `unique()->ignore(...)`.

Les règles de présence sont choisies explicitement :

- absence autorisée : aucune règle de présence ;
- clé exigée, valeur éventuellement nulle : `present` et `nullable` ;
- clé et valeur exigées : `required` ;
- `nullable` ne permet pas de savoir si la clé était absente.

## Classes Data

Une classe Data est normalement `final readonly`, utilise un constructeur privé et expose toujours `::fromArray()` :

```php
final readonly class CategoryFilterData
{
    private function __construct(
        public int $perPage,
        public string $sortBy,
        public ?bool $isActive,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            perPage: (int) ($data['per_page'] ?? 15),
            sortBy: $data['sort_by'] ?? 'name',
            isActive: array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : null,
        );
    }
}
```

Les payloads et les colonnes restent en `snake_case`. Les propriétés PHP sont en `camelCase`. Le mapping reste explicite dans `::fromArray()`.

`::fromArray()` peut construire des enums, dates, collections ou Data imbriquées à partir de valeurs déjà validées. Il ne doit pas relancer une validation HTTP.

## Absence et nullabilité

`MissingValue` différencie une clé absente d'une valeur explicite :

Dans les unions de types des Data, `MissingValue` est placé en premier afin de rendre cette possibilité visible immédiatement : `MissingValue|string|null`, `MissingValue|array|null` ou `MissingValue|bool`.

Quand une Data lit plusieurs champs, `MissingValueReader` peut être créé une fois pour éviter de répéter le tableau source et la liste des champs absents :

```php
$read = MissingValueReader::fromArray($data, $missingFields);

$isActive = $read->get('is_active', default: false);

return new self(
    name: $read->get('name'),
    isActive: $isActive instanceof MissingValue ? $isActive : (bool) $isActive,
);
```

Le lecteur conserve exactement les mêmes règles que `MissingValue::fromArray()` : une clé présente garde sa valeur, y compris `null` ; une clé absente autorisée retourne le sentinel ; un `default` est utilisé pour les autres absences.

```php
$data = CategoryData::fromArray(
    $request->validated(),
    missingFields: ['parent_id'],
);
```

Les noms de `missingFields` sont ceux de la source et restent donc en `snake_case`.
La liste est locale au tableau passé à la Data : elle ne s'applique pas automatiquement aux objets ou tableaux imbriqués. Une Data enfant doit recevoir sa propre liste si elle supporte elle aussi les mises à jour partielles. Les chemins globaux comme `variants.*.name` ne sont donc pas interprétés par `MissingValueReader`.

Pour appliquer une mise à jour :

```php
use function Lahatre\Shared\Data\withoutMissing;

$category->fill(withoutMissing([
    'name' => $data->name,
    'parent_id' => $data->parentId,
]));
```

Le helper retire uniquement `MissingValue::Instance`. Il conserve `null`, `false`, `0`, `''` et `[]`.

Pour un champ obligatoire à la création :

```php
use function Lahatre\Shared\Data\required;

'name' => required($data->name),
```

## Génération

Créer les fichiers dans le module concerné :

```bash
php artisan make:request CategoryRequest --module=catalog --no-interaction
php artisan make:class Data/CategoryData --module=catalog --no-interaction
```

La commande `make:dto` et la classe `LahatreDTO` ont été retirées.

## Tests

Tester séparément :

1. les règles, la normalisation et les erreurs indexées de la Form Request ;
2. le mapping `snake_case` vers `camelCase` de la Data ;
3. la différence entre absence, `null`, `false`, zéro et tableaux vides ;
4. la logique et la persistance du service.
