# Universal CatalogItem Analysis

**Status:** Shared identity implemented for ProductVariant and Bundle.

CatalogItem carries the SKU, unit group, active state, type, and Inventory
boundary for variants and bundles. It shares their UUID. Prices, taxes, and
selling or purchasing capabilities remain future work.

This document analyzes ERPNext's Item model as a source of inspiration. It is
neither a dependency nor an architecture to copy. Its purpose is to guide
future extensions around CatalogItem without moving into the operational
identity what belongs to Product, presentation models, InventoryItem, selling,
or purchasing.

## Conclusion

Inspired by ERPNext's universal Item registry
([documentation](https://docs.frappe.io/erpnext/item)), the architecture makes
CatalogItem the shared operational identity, Product/ProductVariant/Bundle the
presentation models, InventoryItem the stock adapter, and sales/purchase lines
the owners of transaction snapshots.

## ERPNext Vocabulary

### Item

Item is the universal registry. It carries the code, name, description, stock
and manufacturing behavior, purchasing and selling defaults, accounting data,
barcodes, and lifecycle settings. ERPNext uses Item for finished products, raw
materials, sub-assemblies, services, and variants.
[Item documentation](https://docs.frappe.io/erpnext/item)

### Product Bundle

ERPNext uses a parent item, usually non-stockable, and child items that are
delivered or consumed from stock. The parent is selected during sales and the
children define the physical composition.
[Product Bundle](https://docs.frappe.io/erpnext/product-bundle)

In this application, a bundle is a composed offering with its own stockable
CatalogItem and its own InventoryItem. Bundle stock can be assembled from and
disassembled into its components through bundle stock operations. BundleItem
references the generic CatalogItem and exposes its concrete target through the
polymorphic `component` relation. Only variants are currently accepted as
components. Each line stores an integer quantity in the component's ratio-1
base unit, along with an active display_unit_code from the same unit group for
presentation.

### Raw Materials, Semi-Finished Goods, and Finished Goods

ERPNext represents all of them as Item. A BOM connects a manufactured item to
its components and may be multi-level; a semi-finished item can therefore have
its own BOM.
[BOM](https://docs.frappe.io/erpnext/bill-of-materials),
[semi-finished goods](https://docs.frappe.io/erpnext/track-semi-finished-goods)

### Services

A service is a non-stockable item. It still has a quantity, unit, price, taxes,
and accounting behavior, but no warehouse operations or stock entries.
[Services in ERPNext](https://docs.frappe.io/erpnext/erpnext-for-services-organization)

## CatalogItem Responsibility

| Subject | Current or future responsibility |
| --- | --- |
| Identity | id, organization, code/SKU, name, description, active/retired state |
| Classification | product, service, raw material, semi-finished good, finished good, consumable |
| Selling capabilities | is_sellable, is_purchasable |
| Stock capability | is_stockable, unit group, expiration, lot, serial |
| Manufacturing | is_manufactured, BOM eligibility, phantom assembly if needed |
| Presentation | Shared link with ProductVariant or Bundle |
| Search | Barcodes, alternative codes, labels |
| Defaults | Price list, tax classification, accounts, and units when those concepts exist |

The type classifies the item, but rules should not depend only on one large
enum. Explicit capabilities are more flexible. CatalogItem must not contain
current stock, lots, payments, invoices, or sales totals.

## Relationship with Current Inventory

CatalogItem implements HasInventoryItem and is the reference identity for
Inventory. It carries the SKU and base unit group; InventoryItem stores
stock-specific configuration and data, including tracking, deduction strategy,
expiration, and metadata. InventoryItem.sku remains a denormalized cache.

The relationship is:

    CatalogItem 1---0..1 InventoryItem
    InventoryItem 1---* InventoryStock
    InventoryItem 1---* InventoryMovement

A future non-stockable CatalogItem, such as a service, may not have an
InventoryItem. The ledger must not contain two competing identities.

Quantities, lots, movements, and valuation remain in Inventory.

## Behavior Inventory

| Subject | Owner | State or decision |
| --- | --- | --- |
| Universal identity | CatalogItem | Implemented for ProductVariant and Bundle |
| Item type | CatalogItem.item_type | ProductVariant and Bundle implemented |
| Presentation, options, and labels | Product, ProductVariant | Keep outside CatalogItem |
| Concrete SKU | CatalogItem | Source of truth; InventoryItem.sku is a cache |
| Bundle composition | Bundle + CatalogItem components | Implemented, variants only for now |
| Service | Non-stockable CatalogItem | To be added |
| Raw material | CatalogItem + Inventory | To be added with purchasing/manufacturing |
| Semi-finished good | CatalogItem + BOM + Inventory | To be added with manufacturing |
| Selling and purchasing | CatalogItem capabilities | To be defined without placing them in Sale/Purchase |
| Stock configuration | InventoryItem associated with CatalogItem | Created, updated, and deleted only when CatalogItemType::isStockable() is true |
| Units and conversions | Master + CatalogItem unit group | Reuse the current system |
| Prices and price lists | Future pricing component | Add when the need is real |
| Barcodes | Child of CatalogItem | To be added for POS and scanning |
| Expiration, lots, and serials | Inventory, with a possible item policy | To be clarified with stock requirements |
| Valuation | Inventory/accounting | To be defined later |
| Replenishment | Inventory + purchasing | To be added later |
| Supplier and lead time | Purchasing | To be added later |
| Revenue/expense accounts | Accounting configuration referenced by the item | Outside catalog logic |
| Cost center/project | Accounting and transaction | Outside Product |
| Tax classification | Tax configuration referenced by CatalogItem | To be added with taxation |
| BOM | Manufacturing linked to CatalogItem | To be added later |
| Quality control | Quality + Inventory/purchasing | To be added later |
| Warranty | Inventory/after-sales | To be added later |
| Web publication | Presentation/channel | Outside the item core |

## Taxes: Responsibility and Location

Taxes should not be a simple tax_rate on ProductVariant, Bundle, or Sale.
ERPNext separates:

1. the transaction template defines lines, accounts, and calculation;
2. the tax rule selects the template based on context;
3. the item tax template may override the rate;
4. the transaction stores the applied result.

See [tax configuration](https://docs.frappe.io/erpnext/setting-up-taxes),
[Item Tax Template](https://docs.frappe.io/erpnext/item-tax-template), and
[Tax Rule](https://docs.frappe.io/erpnext/tax-rule).

Proposed allocation:

    TaxDefinition / TaxAccount / TaxTemplate = tax/accounting configuration
    CatalogItem.tax_category/default_tax    = item default
    Customer/Address.tax_category           = customer/location context
    Sales/Purchase                           = resolution and calculation
    SaleLine/InvoiceLine                     = immutable historical snapshot

The final rate depends on the transaction context. Sales and purchasing should
use distinct templates because collected and recoverable taxes are handled
differently. [ERPNext taxes](https://docs.frappe.io/erpnext/taxes),
[purchase tax templates](https://docs.frappe.io/erpnext/purchase-taxes-and-charges-template)

The first version should define code/category, rate or fixed amount,
inclusive/exclusive behavior, taxable base, rounding, validity, and a
per-line snapshot. Country-specific rules and withholding remain in
localization/accounting.

## Prices and Units

ERPNext separates prices from the Item registry. A price may depend on a price
list, sales/purchasing direction, currency, unit, customer, minimum quantity,
and period.
[Item Price](https://docs.frappe.io/erpnext/item-price),
[Price Lists](https://docs.frappe.io/erpnext/price-lists)

Proposed allocation:

- CatalogItem: eligibility and base unit group;
- Master: units, ratios, currencies, and precisions;
- future pricing component: current prices and price lists;
- Sales/Purchasing: context-based resolution;
- lines: applied price, currency, discount, and frozen context.

The current price must never be the source of historical amounts.

## Bundles and BOMs Are Different

    Bundle : the customer buys the parent -> components are delivered/consumed
    BOM    : the business produces an output -> components are consumed

They may share the same CatalogItem records, but they must not share one state
model or one business relationship.

## Recommended Sequence

1. create sales and purchasing with normal foreign keys to catalog_item_id;
2. add pricing and taxation as separate capabilities;
3. progressively add services, raw materials, semi-finished goods, and BOMs.

The main remaining risk is reintroducing competing business identities for
future services or components instead of attaching them to a CatalogItem.
