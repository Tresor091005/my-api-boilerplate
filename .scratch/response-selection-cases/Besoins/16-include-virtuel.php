<?php

// Besoin : inclure des enfants qui ne sont pas une relation Eloquent du parent : modèle d'un autre module (service),
//   chemin qui dépend du type du parent, propriétaire choisi selon le profil, filtre évalué en PHP.
// Fréquence dans le projet d'origine : 19 includes avec loader: (moins PosActorsLoader, besoin 12) ; cas cités :
//   AssetCompanyCompaniesLoader, TaxIncludesLoader (catégorie, variante, lot, stockables), UserProfileOwnersLoader,
//   ActiveUserProfilesLoader.
// Avant : loader: XLoader ; attach($parents, $name) branchait sur $name, posait setRelation() et aplatissait.
// Maintenant : virtualInclude() ; loader classe VirtualIncludeLoader ([clé parent => enfants], requêtes groupées
//   explicites) ou closure par parent, pure, qui lit des relations déclarées dans relations: ; asList: true donne
//   la liste vide ; résultat rangé par le moteur. when: par modèle : parent refusé non passé au loader.
// Frontend : inchangé.

namespace Cases\Besoins;

use Cases\Api\ApiResource;
use Cases\Api\BuiltValues;
use Cases\Api\ResourceDefinition;
use Cases\Api\VirtualIncludeLoader;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

// Origine : packages/catalog/src/Http/Resources/AssetCompanyResource.php:21
final class AssetCompanyResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        $resource->virtualInclude('company', LiteCompanyResource::class, AssetCompanies::class, columns: ['company_id']);
    }
}

// Origine : AssetCompanyCompaniesLoader (packages/catalog/)
final class AssetCompanies implements VirtualIncludeLoader
{
    // À trancher : type exact du service à reprendre du code.
    public function __construct(protected TeamInterface $teamService) {}

    public function load(Collection $parents, BuiltValues $built): array
    {
        $companies = $this->teamService
            ->retrieveCompaniesByIds($parents->map(fn ($p) => $p->getAttribute('company_id'))->filter()->unique()->values()->all(), getTeamOrganization()->id)
            ->keyBy('id');

        return $parents->mapWithKeys(fn ($parent) => [
            $parent->getKey() => $companies->get($parent->getAttribute('company_id')),
        ])->all();
    }
}

// Origine : packages/catalog/src/Http/Resources/CategoryResource.php:15 (TaxIncludesLoader)
// La base TaxOwnerInclude (instanceof sur le parent) disparaît : chaque Resource connaît son type et son chemin.
final class CategoryResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // Chemin à deux niveaux : closure par parent, relations chargées par relations:.
        $resource->virtualInclude(
            'vat_rate',
            VatRateResource::class,
            fn (Category $category) => $category->taxConfiguration?->vatRate,
            relations: ['taxConfiguration.vatRate'],
        );
        // Pour une catégorie, taxes est sa propre relation : include simple (besoin 10).
        $resource->include('taxes', TaxResource::class);
    }
}

// Origine : packages/catalog/src/Http/Resources/ProductVariantResource.php:19 et :38
// Même forme : BundleResource.php:22, StockableResource, StockableListResource, VariantStockableResource.
final class ProductVariantResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // Configuration fiscale déléguée au stockable.
        $resource->virtualInclude(
            'vat_rate',
            VatRateResource::class,
            fn (ProductVariant $variant) => $variant->stockable?->taxConfiguration?->vatRate,
            relations: ['stockable.taxConfiguration.vatRate'],
        );
        $resource->virtualInclude(
            'taxes',
            TaxResource::class,
            fn (ProductVariant $variant) => $variant->stockable?->taxes,
            asList: true,
            relations: ['stockable.taxes'],
        );
        // EligibleInventoryLevelsScope → relation dédiée (besoin 13).
        $resource->include('levels', InventoryLevelResource::class, relation: 'eligibleInventoryLevels');
    }
}

// Origine : packages/iam/src/Http/Resources/UserProfileResource.php (is_current, :37)
final class UserProfileResource extends ApiResource
{
    private const OWNER_COLUMNS = ['company_id', 'client_id', 'organization_scope_id', 'organization_id'];

    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // Valeur dépendant de la session (besoin 03) : contexte lu, aucune requête.
        $resource->field('is_current', columns: ['profile_id'], computedBy: fn (UserProfile $profile) => auth()->profile()?->id === $profile->profile_id);

        // Un loader par include ; when: par modèle sur ownerType(), qui lit OWNER_COLUMNS déclarées.
        $resource->virtualInclude('company', LiteCompanyResource::class, ProfileOwnerCompanies::class, columns: self::OWNER_COLUMNS,
            when: fn (UserProfile $profile, Request $request) => $profile->ownerType() === 'company');
        $resource->virtualInclude('client', LiteClientResource::class, ProfileOwnerClients::class, columns: self::OWNER_COLUMNS,
            when: fn (UserProfile $profile, Request $request) => $profile->ownerType() === 'client');
        // when: de l'include organization non repris du code d'origine (« ... ») : à vérifier.
        $resource->virtualInclude('organization', OwnerOrganizationResource::class, ProfileOwnerOrganizations::class, columns: self::OWNER_COLUMNS);
    }
}

// Origine : UserProfileOwnersLoader (packages/iam/), une classe par include.
// attachOwners() reste pour prepareForLegacyRendering() (besoin 19) ; ownersByProfile() est ajouté pour le moteur.
final class ProfileOwnerCompanies implements VirtualIncludeLoader
{
    public function __construct(protected UserProfileService $profiles) {}

    public function load(Collection $parents, BuiltValues $built): array
    {
        return $this->profiles->ownersByProfile($parents, 'company');
    }
}

final class ProfileOwnerClients implements VirtualIncludeLoader
{
    public function __construct(protected UserProfileService $profiles) {}

    public function load(Collection $parents, BuiltValues $built): array
    {
        return $this->profiles->ownersByProfile($parents, 'client');
    }
}

final class ProfileOwnerOrganizations implements VirtualIncludeLoader
{
    public function __construct(protected UserProfileService $profiles) {}

    public function load(Collection $parents, BuiltValues $built): array
    {
        return $this->profiles->ownersByProfile($parents, 'organization');
    }
}

// Origine : packages/iam/src/Http/Resources/UserResource.php:32
final class UserResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        // ...
        // Vraie relation, mais isUsableBy s'évalue en PHP : une relation filtrée en SQL ne suffit pas.
        $resource->virtualInclude('user_profiles', UserProfileResource::class, SwitchableProfiles::class, asList: true);
    }
}

// Origine : ActiveUserProfilesLoader / attachActiveProfiles (packages/iam/)
final class SwitchableProfiles implements VirtualIncludeLoader
{
    public function __construct(protected UserProfileService $profiles) {}

    public function load(Collection $parents, BuiltValues $built): array
    {
        // Même règle que les payloads d'authentification : actifs, du périmètre de la session,
        // et pour une organisation seulement ceux que isUsableBy accepte ; array<clé utilisateur, Collection>.
        return $this->profiles->switchableProfilesByUser($parents);
    }
}
