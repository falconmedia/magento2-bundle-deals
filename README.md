# FalconMedia_BundleDeals

Bundle deals ("better together") for Magento 2 / Hyvä: a set of equal members, each filled by products that match
catalog conditions. Every member product can be the entry point: its product page shows the other members, the
shopper picks size/colour per product and adds the whole set in one request. The discount runs through native cart
price rules, so it stays visible in cart, checkout, order, invoice and credit memo.

## Installation

```bash
composer require falconmedia/magento2-bundle-deals:@dev
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento hyva:config:generate
npm --prefix app/design/frontend/<Vendor>/<theme>/web/tailwind run build
bin/magento cache:flush
```

Requires a Hyvä theme (Alpine.js, `HyvaCsp`). Tailwind setup: see *Theme setup* below.

## Theme setup

The storefront templates are built for Hyvä (Alpine.js + Tailwind). To keep them independent of any specific theme,
all styling uses a module-owned prefix: **`fmbd-`** ("FalconMedia BundleDeals").

- **Tailwind utilities** such as `text-fmbd-sm`, `gap-fmbd-3`, `bg-fmbd-surface-1`, `border-fmbd-border`.
- **Component classes** such as `fmbd-btn`, `fmbd-chip`, `fmbd-option`, `fmbd-row-slider__track`.
- **CSS custom properties** such as `--fmbd-primary` and `--fmbd-text`. Colours read these variables and fall back to a
  neutral palette, so the module looks acceptable without any setup.

The prefix avoids clashes with your theme's own classes and with other modules.

### 1. Register the tokens in Tailwind

In your theme's `tailwind.config.js`, merge the module's theme extension (adjust the relative path):

```js
const fmbd = require('../../../../../../../vendor/falconmedia/magento2-bundle-deals/src/view/frontend/tailwind/fm-bundle-deals.theme.js');

module.exports = {
    theme: { extend: { ...fmbd } },
    // ...
};
```

The module's own `tailwind.config.js` already adds its templates to the Tailwind `content` list through
`bin/magento hyva:config:generate`.

### 2. Import the component CSS

In your theme's `tailwind-source.css`:

```css
@import "../../../../../../../vendor/falconmedia/magento2-bundle-deals/src/view/frontend/tailwind/fm-bundle-deals.css";
```

Then run `bin/magento hyva:config:generate` and your Tailwind build.

### 3. Match your brand (optional)

Set the variables on `:root` (and in your dark-mode selector, if you have one):

```css
:root {
    --fmbd-primary: #c8102e;
    --fmbd-text: #16181d;
    --fmbd-bg: #ffffff;
}
```

| Variable | Used for |
| --- | --- |
| `--fmbd-bg`, `--fmbd-surface-1` .. `-3` | backgrounds and cards |
| `--fmbd-text`, `--fmbd-text-muted`, `--fmbd-text-soft` | text |
| `--fmbd-border`, `--fmbd-border-strong` | borders |
| `--fmbd-primary`, `--fmbd-on-primary` | accents, selected states |
| `--fmbd-cta`, `--fmbd-on-cta` | add-to-cart button |
| `--fmbd-discount`, `--fmbd-discount-bg` | savings badges and prices |
| `--fmbd-ok`, `--fmbd-warning`, `--fmbd-error` (+ `-bg`) | stock and message states |
| `--fmbd-focus`, `--fmbd-rating` | focus ring, star rating |
| `--fmbd-disabled-bg`, `--fmbd-disabled-text` | unavailable options |
| `--fmbd-wrap-max`, `--fmbd-wrap-gutter` | width of the overview page |

Spacing, font sizes and radii (`*-fmbd-1` .. `-8`, `text-fmbd-xs` .. `-2xl`, `rounded-fmbd-sm|md|lg`) are fixed in
`fm-bundle-deals.theme.js`; edit that file's copy in your theme if you need a different scale.

## Configuration

Stores > Configuration > Catalog > Bundle Deals: enabled, default block title, maximum number of other slots shown,
maximum alternatives per slot, and the position on the product page.

### Variant match (colour or any other attribute)

Stores > Configuration > Catalog > Bundle Deals > Variant Match. All fields are dropdowns, per store view:

- **Match Items:** on a product page, the other items start with the same value as the viewed product.
- **Match Attribute:** any dropdown or multiple select product attribute. Default `color`; use e.g. material or size if
  that is what your variants differ on. The same attribute defines the variant groups on the overview page.
- **Swatch Attribute:** optional; its visual swatch gives the dots on the overview page. Empty means the match attribute.
- **Fallback Values:** values to prefer when no item has the same value as the viewed product (in attribute option
  order). Empty by default.

### Other settings that differ per shop

- **General > Anchor ID:** id of the bundle section on the product page (default `bundle-deal`), used by the teaser link.
- **Overview page > Sport filter attribute / Brand attribute:** dropdowns with your option attributes.
- **Overview page > Brand filter URL parameter:** name of the URL parameter (default `brand`, so `/bundles?brand=venum`).
- **Set page > Size Advice CMS Block:** CMS block shown on set pages (default identifier `sizechart`; nothing shows when the block does not exist).

## Creating a bundle deal

Marketing > Bundle Deals > Add New:

1. Name, storefront title, store views, active, from/to date and priority (lower wins when a product is the entry of
   several bundle deals). "Exclude from other discounts" keeps every other cart price rule off the items of a complete
   set.
2. Save, then add at least two slots. Per slot: title, product conditions (category, attribute set, SKU, ...),
   sorting of the alternatives, "Use as entry" and the discount (none, percentage or fixed amount per item).
3. Saving a slot creates or updates its generated cart price rule (`[Bundle deal #id] ...`) and reindexes the
   bundle. Do not edit the generated cart price rules; they are overwritten on the next save.

On the product edit page, the "Bundle Deals" section adds a product to a slot by hand and overrides "Use as entry" for
that product.

Sorting per slot: `position` (manual links first), `price_asc`, `price_desc`, `name`, `newest`. Configurable products
sort on the lowest guest price of the price index.

## Creating a bundle deal from the CLI

```bash
bin/magento falconmedia:bundle-deals:create "Venum Elite set" \
  --title="Samen voordeliger" \
  --store=<store_code> \
  --priority=0 \
  --slot="Bokshandschoenen|VENUM-1392-126,VENUM-03540-522|10%" \
  --slot="Scheenbeschermers|VENUM-1394-126,VENUM-1394-534|10%" \
  --slot="Bandages|VENUM-0430-200,VENUM-0429-500|5%|0"
```

Slot format: `title|sku,sku,...|discount|use_as_entry|sort_by|slot_type`. Discount is empty (none), `10%` (percentage) or
`5` / `2,50` (fixed amount per item). `use_as_entry` is `1` (default) or `0`; `sort_by` defaults to `position`; `slot_type` is `fixed`, `variable` (default) or `optional`.
Optional slots may stay empty in the cart and on the set page; the deal needs at least two filled slots.
Options: `--store` (comma separated store codes, default `admin` = all store views), `--priority`,
`--allow-other-discounts`, `--inactive`. The command creates the generated cart price rules and reindexes.

## Storefront

- Product page: block with the other slots, "Kies andere" to switch the alternative, size/colour selection per
  configurable product and one add-to-cart request for the whole set.
- Cart: block "Vaak samen gekocht" with only the members still missing for a started set.
- CMS widget "Bundle Deals Overview" (`falconmedia_bundle_deals_overview`, parameters `title`, `heading_level` and
  `limit`) lists the active bundle deals, for example on the CMS page `bundles`:
  `{{widget type="FalconMedia\BundleDeals\Block\Widget\BundleOverview" title="Bundels" heading_level="1" limit="12"}}`
  Use `heading_level="1"` when the overview is the main content of the page; the bundle titles then become h2. Cards
  show the storefront title of the bundle deal, or its name when that is empty; a title equal to the widget title is
  not repeated.
  A CMS page with the same identifier as the overview URL key wins over the overview router, so disable it when using
  the overview page.

## Delivery time

The product page block shows the delivery time per product and a summary for the whole set ("Alles morgen in huis"
or "Compleet in X tot Y werkdagen"). The times come from `FalconMedia\BundleDeals\Api\DeliveryTimeProviderInterface`.
The module ships `NullDeliveryTimeProvider` (no delivery times, rows and summary are hidden); a stock module replaces
it with a DI preference. Example mapping to your own resolver:

```xml
<preference for="FalconMedia\BundleDeals\Api\DeliveryTimeProviderInterface"
            type="Vendor\Module\Model\BundleDeals\DeliveryTimeProvider"/>
```

Stores > Configuration > Catalog > Bundle Deals > "Show split shipment notice" adds a line that the set may arrive in
two shipments when the delivery times differ. It is display only and does not change shipping. All texts are in
`i18n/*.csv`.

## Discount and stacking

- A slot discount applies only to the units of a cart line that are part of a complete set. Two gloves with one pair
  of shin guards get the set discount once.
- Items added separately count as well; the set is matched on the cart contents, not on how the items were added.
- Every unit counts for one set of one bundle deal. When deals share products, the units are divided over the deals
  so that the total customer discount is highest; on a tie the lowest bundle deal id wins.
- Cart price rules have the field "Also valid on bundle deal items". Set it to No to skip items of a complete set.
- A bundle deal with "Exclude from other discounts" removes all other cart price rules from items of a complete set.
- Cart price rule conditions "Bundle deal set complete in cart" and "Item belongs to complete bundle deal set"
  (actions) are available for custom rules.

Known limitations:

- The stacking filter works per cart line, so a line that is only partly in a complete set (qty 3, one unit in the
  set) is filtered as a whole. With "Exclude from other discounts" the two loose units then lose other discounts too.
- Generated cart price rules use sort order 0 without "Discard subsequent rules". An existing automatic rule with sort
  order 0 and "Discard subsequent rules" can block the bundle discount; give such rules a higher sort order.
- Customer groups of the generated rules are copied on save. Save the bundle deal again after adding a customer group.
- The storefront templates need Hyvä (Alpine, `HyvaCsp`).

## Generated cart price rules

Every slot with a discount has a generated cart price rule named `[Bundle deal #<id>] ...`. Saving or deleting a bundle
deal or slot disables generated rules that no longer belong to a slot. To rebuild all generated rules and disable
orphaned ones (run after a deploy):

```bash
bin/magento falconmedia:bundle-deals:sync-rules [--dry-run]
```

## Importing Amasty packs

Copies the packs of `amasty_mostviewed_pack` into bundle deals. Run without Xdebug: with Xdebug in debug mode PHP
segfaulted halfway through a full import.

```bash
XDEBUG_MODE=off bin/magento falconmedia:bundle-deals:import-amasty --dry-run
XDEBUG_MODE=off bin/magento falconmedia:bundle-deals:import-amasty [--pack=12,13] [--keep-status] [--children-as-entry] [--force]
```

- `--dry-run` prints the plan per pack and the colour groups, and writes nothing.
- `--pack` limits the import to the given Amasty pack ids.
- `--keep-status` copies the Amasty status; without it every newly imported deal is active.
- `--children-as-entry` also shows the deal on the item product pages.

- `--force` overwrites deals imported earlier: rule fields, slots and manual links are replaced. The status of the deal stays as it is unless `--keep-status` is given.

The import is idempotent: a pack is matched on `external_reference` (`amasty_pack:<pack_id>`) and an existing deal is left untouched on a second run.
Packs whose products no longer exist are skipped. Simple products that are not visible on their own (size or
colour children) are replaced by their configurable parent, and items that end up on the same parent share one slot. Each slot is reindexed after saving, so the catalog indexes
(`catalog_category_product`, `catalog_product_price`, `cataloginventory_stock`) must be current first; with
empty indexes no products match and the bundle index stays empty.

### Colour groups

Deals that are colour versions of one bundle share a `color_group` code, with `color_option_id` and
`color_sort_order`. The import resolves groups in this order:

1. The same `style_group` attribute value on the entry products, when that attribute exists.
2. Colour variant product links (link type 20) between the entry products.
3. The same pack name before the last " - ", with a colour suffix that differs per pack.

The group can also be set or changed per deal in the admin form.

## Overview page

`/bundles` (configurable URL key) lists the active deals as cards, one card per colour group. The page is disabled by
default and returns a 404 until it is enabled. Settings are under
Stores > Configuration > FalconMedia > Bundle deals > Overview page: enable, URL key, page title, H1, meta title and
description, intro text or CMS block, items per page, default sort, and the sport and brand attributes.

- URL parameters: `sport`, `merk` (alias `brand`), `sort` (`popular`, `saving`, `price_asc`, `price_desc`,
  `newest`) and `p` for the page.
- The canonical points to the unfiltered page (with `p` when beyond page 1). Pages with a filter or sort parameter
  get `NOINDEX,FOLLOW`; filter and sort links are `rel="nofollow"`. The page outputs an `ItemList` JSON-LD.
- Brand comes from the entry product; sports are the union over the default products of all slots.
- Facet counts are cross-filtered: each facet counts with the other filters applied.

## Set page

Every deal has a page at `/<prefix>/<url key>` (default prefix `set`, config `falconmedia_bundle_deals/set_page/*`).
The url key defaults to the deal title and is unique. Deals in one colour group share the page layout and switch
colour without reload (`history.replaceState`); each colour keeps its own self canonical. The page answers 404 when
the module or set page is off, the deal is inactive, or a required slot has no salable product, and is `noindex`
until `set_page/indexable` is enabled. Delivery and return texts come from config or CMS blocks. Items are added as
separate quote lines; the discount comes from the generated cart price rules.

## Colour variants on the product page

`BundleDeal::getColorGroupVariants($ruleId)` returns the other colour versions of a deal: label (colour option,
then `kleuren`, then the name suffix), swatch hex from the `kleuren` swatch, image, prices, `inStock`, `deliverable`
and `deliveryText`. Variant links end in `#<anchor id>`, the configurable id of the bundle deal section (General > Anchor ID).

## Index

The product index is rebuilt on every save and hourly by cron. Manual rebuild:

```bash
bin/magento falconmedia:bundle-deals:reindex [rule_id ...]
```

## Tests

```bash
vendor/bin/phpunit -c phpunit.xml
```

## License

MIT, see [LICENSE](LICENSE).
