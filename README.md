# Lahatre ERP

[Français](#français) · [English](#english)

## Français

Lahatre ERP est un ERP modulaire en développement, construit avec PHP et Laravel.
Il relie identité et organisations, catalogue, clients, stock et fichiers privés
au sein d'une même application. Son premier parcours métier cible la **vente et
la livraison**. Les commandes et les documents de livraison restent à construire.

Les huit modules existants portent des besoins concrets de l'ERP. Leur code peut
également servir de référence ou être adapté à d'autres applications métier et
SaaS. Ils sont intégrés par des packages Composer locaux ; ils ne sont pas encore
distribués comme des packages autonomes.

La [documentation française](https://lahatre-erp-docs.vercel.app/fr/docs) propose recherche, navigation par
module, exemples et répertoire des routes. Consultez le
[guide de démarrage](https://lahatre-erp-docs.vercel.app/fr/docs/getting-started/installation) pour installer l’ERP.

Le [guide Docker](docs/infrastructure/docker.md) détaille les services et les ports.
Le client [Bruno](api-client/README.md) accompagne les essais manuels de l'API.

## English

Lahatre ERP is a modular ERP in development, built with PHP and Laravel. It brings
identity, organizations, catalog, customers, inventory and private files together
in one application. Its first intended business workflow is **sales and delivery**.
Orders and delivery documents are still planned.

The eight existing modules implement concrete ERP needs. Their code can also be
used as a reference or adapted to other business applications and SaaS products.
They are integrated through local Composer packages and are not yet independently
distributed packages.

The [English documentation](https://lahatre-erp-docs.vercel.app/en/docs) provides search, module navigation,
examples and a route directory. Follow the
[installation guide](https://lahatre-erp-docs.vercel.app/en/docs/getting-started/installation) to set up the ERP.
Use [Bruno](api-client/README.md) for manual API exploration.

## Contribution

The [documentation index](docs/README.md) separates public guides, contributor
references and working decisions. Follow the current rules in `.ai/rules/`.
Behavior changes require affected Pest coverage. Use a disposable PostgreSQL test
database: the current `phpunit.xml` inherits database credentials from `.env`.

## License

The project and all eight local modules are licensed under [MIT](LICENSE).
Third-party dependencies retain their own licenses.
