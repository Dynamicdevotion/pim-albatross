# PIM — Product Information Management

A Product Information Management application built as a **modular monolith**:
Laravel 13 + [`nwidart/laravel-modules`](https://nwidart.com/laravel-modules) v13
for the domain modules, and [Filament](https://filamentphp.com) v4 for the admin
panel. Each business domain lives in its own module under `Modules/`, wired into
the shared admin panel through a small Filament plugin.

---

## Stack

| | |
|---|---|
| PHP | `^8.3` (server runs 8.4) |
| Framework | Laravel 13 |
| Modules | `nwidart/laravel-modules` ^13 |
| Admin panel | `filament/filament` ^4 |
| Frontend | Vite 8 + Tailwind CSS 4 |
| Default DB | SQLite (`database/database.sqlite`) |
| Session / cache / queue | `database` driver |

---

## Local setup

```bash
git clone ssh://git@ssh.github.com:443/Dynamicdevotion/pim-albatross.git pim
cd pim

composer install
cp .env.example .env
php artisan key:generate

# SQLite (default). For MySQL, see "Database" below.
touch database/database.sqlite
php artisan migrate

# frontend assets (needs Node 20+)
npm install
npm run build

# create an admin user for the Filament panel
php artisan make:filament-user

php artisan serve
```

`composer setup` runs install + env + key + migrate + npm build in one go.
`composer dev` starts server, queue worker, log tailer and Vite together.

The admin panel is served at **`/admin`** (login at `/admin/login`).

### Git hooks

`composer install` (any run — it is wired into `post-autoload-dump`) installs
this project's `pre-commit` hook into `.git/hooks/` automatically, via
`scripts/install-git-hooks.php`. No manual step needed, on this clone or any
future one.

What it does: this project vendors third-party JS/CSS and publishes it with
`php artisan filament:assets` into committed `public/` files (see
*Deployment*) rather than an npm build — a step that is easy to forget after
editing a source under `Modules/*/resources/{js,css}/**`, and has caused a
live 500 more than once when the published copy fell out of sync with its
source. When a commit's staged changes touch one of those sources, the hook:

1. runs `php artisan filament:assets` for you;
2. stages whatever it regenerated under `public/` — never a blanket `git add
   public/`, only the files that actually changed as a result of that command,
   so any other unrelated `public/` changes you haven't staged are left alone;
3. **blocks the commit** if the command itself fails, instead of letting a
   stale/inconsistent `public/` through.

It is a no-op (adds no delay) for a commit that doesn't touch a vendored
JS/CSS source. To reinstall it by hand (e.g. after editing
`scripts/git-hooks/pre-commit`) run:

```bash
php scripts/install-git-hooks.php
```

It won't overwrite a `.git/hooks/pre-commit` that isn't its own (no matching
marker comment) — if you already have a custom one, install-git-hooks.php
leaves it alone and prints a warning instead. `git commit --no-verify` skips
the hook entirely; avoid it for a commit touching `Modules/*/resources/js`
or `css`.

### Note: `.env.example` ships production defaults

To avoid a debug-exposing first deploy, `.env.example` sets:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://localhost
LOG_LEVEL=error
```

For local development, override these in your `.env`:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
LOG_LEVEL=debug
```

### Database

SQLite is the default and needs no configuration beyond creating the file.
To use MySQL, set in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pim
DB_USERNAME=pim
DB_PASSWORD=secret
```

---

## Modular architecture

### How modules are loaded

Modules live in `Modules/<Name>/`. Each has its own `composer.json` whose PSR-4
map is merged into the root autoloader by `wikimedia/composer-merge-plugin`
(configured in the root `composer.json` under `extra.merge-plugin`). After
adding or changing a module `composer.json`, run:

```bash
composer dump-autoload
```

`modules_statuses.json` tracks which modules are enabled.

### Namespace and path mapping

Note the `app/` sub-directory: the module root namespace points at it, **not**
at the module directory itself.

| Path | Namespace |
|---|---|
| `Modules/Products/app/` | `Modules\Products\` |
| `Modules/Products/app/Filament/Resources/` | `Modules\Products\Filament\Resources\` |
| `Modules/Products/database/factories/` | `Modules\Products\Database\Factories\` |
| `Modules/Products/database/seeders/` | `Modules\Products\Database\Seeders\` |
| `Modules/Products/tests/` | `Modules\Products\Tests\` |

### Filament resources inside a module

The admin panel (`app/Providers/Filament/AdminPanelProvider.php`) only
auto-discovers resources under `app/Filament/Admin/`. A module therefore ships
its Filament code under `Modules/<Name>/app/Filament/` and registers it with the
panel through a **plugin**:

```php
// Modules/Products/app/Filament/ProductsPanelPlugin.php
public function register(Panel $panel): void
{
    $panel->discoverResources(
        in: module_path('Products', 'app/Filament/Resources'),
        for: 'Modules\\Products\\Filament\\Resources',
    );
}
```

```php
// app/Providers/Filament/AdminPanelProvider.php
->plugins([
    \Modules\Products\Filament\ProductsPanelPlugin::make(),
])
```

Nothing Filament-related for a domain lives in the app `app/` folder — only
the one-line `->plugins([...])` opt-in.

---

## Products module

Structure (`Modules/Products/`):

```
app/
├── Filament/
│   ├── ProductsPanelPlugin.php              # registers the resource on the "admin" panel
│   └── Resources/Products/
│       ├── ProductResource.php              # model, navigation, pages
│       ├── Pages/
│       │   ├── ListProducts.php
│       │   ├── CreateProduct.php
│       │   └── EditProduct.php
│       ├── Concerns/                        # page/RM hooks: translations + prices load/save
│       │   ├── HandlesProductTranslations.php    HandlesProductPrices.php   # product pages
│       │   └── HandlesVariantTranslations.php    HandlesVariantPrices.php   # variant RM actions
│       ├── RelationManagers/VariantsRelationManager.php  # variants + "Generate variants"
│       ├── Schemas/
│       │   ├── ProductForm.php              # create/edit form schema (+ translation tabs)
│       │   └── ProductPricesTable.php       # fixed per-active-list price table (shared)
│       └── Tables/ProductsTable.php         # list table: columns, filters, actions
├── Enums/ProductType.php                    # simple / variable / variant
├── Exceptions/CannotChangeProductType.php
├── Http/Controllers/ProductsController.php
├── Models/Product.php
├── Support/VariantGenerator.php             # pure combination / SKU / translation-copy logic
└── Providers/
    ├── ProductsServiceProvider.php
    ├── EventServiceProvider.php
    └── RouteServiceProvider.php
config/config.php
database/
├── factories/
├── migrations/2026_08_26_184824_create_products_table.php
└── seeders/ProductsDatabaseSeeder.php
routes/
├── api.php                                  # GET|POST /api/v1/products, ...
└── web.php                                  # /products, ...
composer.json                                # PSR-4: Modules\Products\ => app/
module.json                                  # name, alias, service providers
```

### `Product` model

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | PK |
| `type` | string | `simple` (default) / `variable` / `variant` — see *Variable products* |
| `parent_id` | bigint | nullable FK → `products`, `cascadeOnDelete`; set only on a variant |
| `sku` | string | unique, required |
| `external_id` | string | nullable |
| `status` | string | default `draft` (`draft` / `active` / `archived` in the UI) |
| `stock` | int | nullable, default 0; `null` for `variable` rows |
| `weight` | decimal(8,3) | nullable — kg; `null` for `variable` rows |
| `length` / `width` / `height` | decimal(8,2) | nullable — cm; `null` for `variable` rows |
| `created_at` / `updated_at` | timestamp | |

Mass-assignable: `type`, `parent_id`, `sku`, `external_id`, `status`, `stock`,
`weight`, `length`, `width`, `height`. Translatable content (`name`,
`description`) lives in the Localization module — see below. A `saving` guard
(`CannotChangeProductType`) keeps the type/`parent_id` shape consistent and
blocks turning a variable that still has variants into another type; the same
`saving` hook nulls `stock` **and the four dimension columns** on a `variable`
row, since a container carries neither its own stock nor its own dimensions.

### Admin routes

| Route | Name |
|---|---|
| `GET /admin/products` | `filament.admin.resources.products.index` |
| `GET /admin/products/create` | `filament.admin.resources.products.create` |
| `GET /admin/products/{record}/edit` | `filament.admin.resources.products.edit` |

All behind auth; unauthenticated requests redirect to `/admin/login`.

### Variable products

A **single self-referencing table** — every variant is a full `Product`, so it
inherits Pricing, translations, taxonomy terms and everything else for free.

| Type | Role |
|---|---|
| `simple` | independent product; behaviour unchanged |
| `variable` | container: shared name/description/common terms, **no own price, stock or dimensions** (those fields are hidden in the form) |
| `variant` | child of a variable: own SKU, own translations (seeded from the parent), own per-list prices, own stock, own weight/dimensions, own distinguishing terms |

- **List** (`/admin/products`): top-level rows only (`whereNull('parent_id')`);
  a variable shows a "*N* variants" count. Filters: **type**, **status**,
  **missing translation** (per language), **taxonomy** (faceted — AND across
  taxonomies, OR within one, each term expanded to its subtree), **price**
  (presence + min/max on a chosen list) and **stock** (out-of-stock / low, the
  threshold from `products.low_stock_threshold`). The **stock**, **weight** and
  **length / width / height** columns are toggleable (the dimension ones hidden
  by default). **Saved views** (see *SavedViews module*) snapshot the active
  filters plus the column-manager state under `resource: "products"`.
- **Variants are managed inside the parent**: `VariantsRelationManager` (visible
  only for `variable`) — an inline table (SKU / stock editable in place), a full
  variant edit form, and **Generate variants**: a wizard that takes the chosen
  taxonomies and which of their values to combine, then creates one variant per
  combination with an editable SKU (parent SKU + term slugs) and name (copied
  from the parent). Existing SKUs are skipped; capped at
  `VariantGenerator::MAX_COMBINATIONS` (100).
- **Price grid** (`/admin/prices`): variable containers are excluded; variant
  rows read `— Parent › distinguishing part` and a "Variants / simple / all"
  filter is available. `Modules\Products\Support\VariantGenerator` holds the pure
  combination/SKU/translation-copy logic.

### Images (media)

`Product` is a **Spatie Media Library** `HasMedia` model with two collections,
usable on every product type (a `variable` container included — its image is the
one shown in the list):

| Collection | Shape |
|---|---|
| `main_image` | `singleFile()` — a new upload replaces the previous one |
| `gallery` | multiple, reorderable |

Both accept **jpg / png / webp only** (`Product::IMAGE_MIME_TYPES`), max **5 MB**
per file (`config('media-library.max_file_size')` and the Filament fields both
enforce it). A `thumb` conversion (`Fit::Crop`, 300×300 — a centre-cropped
filled square, not letterboxed) is generated **synchronously** on upload —
`queue_conversions_by_default` is `false` and the conversion is `->nonQueued()`,
because the shared Netsons host runs no queue worker.

**Inheritance.** `Product::getMainImageUrl($conversion = '')` returns the
product's own main image, or — for a `variant` with none of its own — its
parent's, or `null`. Same "own value, then parent" rule already used for variant
names and SKUs.

**Deletion.** Spatie's `InteractsWithMedia` removes a product's own media (rows
+ files) on `$product->delete()`. A variable's variants, though, are removed by
the `parent_id` FK cascade — no model events — so `Product`'s `deleting` hook
calls `deleteAllMedia()` on each variant first, so deleting a variable leaves no
orphan files (mirrors the `deleting` hooks on `ImportRecord` / `ExportRecord`).

**Storage.** Files live on the `public` disk at
`storage/app/public/<media_id>/<file>` and are served through the existing
`public/storage` symlink at `APP_URL/storage/<media_id>/<file>` — verified
working on Netsons (the web server follows the owner-matched symlink).
Uploaded media is outside the repo (`storage/app/public/.gitignore`). The
Filament upload fields pin `->disk('public')` explicitly: without it the
component would use `filament.default_filesystem_disk` (`local` here) and the
files would land in the non-public `storage/app/private`.

**Filament.**
- `ProductForm` and the variant form (`VariantsRelationManager`) are grouped
  into Filament sections: **SEO** (`meta_title` / `meta_description`, inside each
  translation tab), **Spedizione** (`weight` / `length` / `width` / `height`),
  **Immagini** (`main_image` / `gallery`); `sku` / name / description / price /
  stock / taxonomy terms stay in the main body. A `// TODO` marks where a
  future *Custom* section will go.
- The *Immagini* section: a single-file `main_image` upload (with image editor)
  and a multiple, reorderable `gallery` upload, via
  `SpatieMediaLibraryFileUpload`. Both use `panelLayout('grid')` with
  `conversion('thumb')` — a compact square mosaic of cropped previews (3 columns
  from `lg`, 2 below), capped at 30rem wide.
- Each upload has a **"Scegli da un'immagine esistente"** hint action
  (`Modules\Products\Support\ExistingImagePicker`): a searchable `Select` of
  every product image in the library (thumbnail + owner name + file name). On
  confirm, `Media::copy()` **duplicates** the chosen file into this product as
  its own independent Media row — not shared, just a shortcut past re-uploading.
  Available on the edit form (the record must exist); on create it asks to save
  first.
- `ProductsTable` and the variant table show a fixed **thumbnail column**
  (`ImageColumn` over `getMainImageUrl('thumb')`, `imageSize(40)->square()`, so
  a variant with no image of its own shows the parent's — same crop). It is
  **not** toggleable — always visible. `public/images/placeholder-product.svg`
  is the fallback when there is no image.

---

## Localization module

Multilingual content, **independent from `APP_LOCALE`** (which only sets the
admin UI language). Which languages exist is **panel-managed data**, not a
static list. Translations live in satellite tables keyed by `language_id`, with
**no automatic fallback** — a missing row shows as empty.

### `languages` table

| Column | Notes |
|---|---|
| `code` | ISO 639-1, unique (`it`, `en`, …) |
| `name` | display name |
| `active` | offered for editing in the panel |
| `is_base` | exactly one row; the required language and slug/migration source |

`create_languages_table` also inserts a ~20-language catalogue
(`Support/LanguageCatalog`, shared with `LanguageSeeder`); `it/en/es/fr/de` start
active, `it` is base.

**`Modules\Localization\Support\Locales`** is the read facade (replaces the old
`Locale` enum): `active()`, `activeCodes()`, `base()` / `baseCode()`, and
`idFor($code)` / `codeFor($id)` bridging the code used in form state and the
`language_id` stored in the DB.

### Translation tables

`product_translations` (`Products`), `taxonomy_translations` &
`taxonomy_term_translations` (`Taxonomies`) all share the shape:

| Column | Notes |
|---|---|
| `<parent>_id` | FK, cascade on delete |
| `language_id` | FK → `languages`, cascade on delete |
| `name` | required — a row exists only if it has a name |
| `description` | `product_translations` only; nullable RichEditor HTML |
| `meta_title` / `meta_description` | `product_translations` only; both nullable, plain text — the SEO fields, optional and independent of whether the row has a `name` |

Unique on `(<parent>_id, language_id)`. The translation models keep an ergonomic
`locale` attribute (read/write by code) over `language_id`. `meta_title` /
`meta_description` follow `name` / `description` exactly — one set per language,
shown on the simple, variant **and** variable forms.

```php
$product->translations;              // HasMany
$product->translate('en');           // ?Translation, null if absent (no fallback)
$taxonomy->name;                     // read-only accessor → base-language name
```

### `LanguageResource` (`/admin/languages`)

List with an `active` **ToggleColumn** (disabled for the base language and for
any language that already has content). A **"Deactivate…"** row action handles
the content case: it asks *keep the rows hidden* (they reappear when the
language is re-activated — the tab-per-language forms only render **active**
languages) vs *delete every translation in this language*
(`Support/LanguageContent::purge`, across all three tables).

### Editing translations

No dedicated resource for the translations themselves. Each translatable form
(`ProductForm`, `TaxonomyForm`, the Terms relation manager) renders a `Tabs`
block built from `Locales::active()` — one tab per active language, base-language
name required. Save/prune runs in the page's `afterCreate` / `afterSave` hooks
(`HandlesProductTranslations`, `HandlesTranslatableName`), keyed by `language_id`
and touching only active languages.

---

## Taxonomies module

User-defined classification systems (e.g. Categoria, Tag, Misure, Colori). Each
**taxonomy** owns **terms** that can be nested (parent/child), and a product can
carry many terms — from any number of taxonomies.

### Tables

| Table | Columns | Notes |
|---|---|---|
| `taxonomies` | `id`, `slug` (unique), timestamps | the classification types; **name is translated** |
| `taxonomy_terms` | `id`, `taxonomy_id` (FK, cascade), `parent_id` (FK → `taxonomy_terms`, nullable, **nullOnDelete**), `slug`, timestamps | unique `(taxonomy_id, slug)`; deleting a parent term promotes its children to roots; **name is translated** |
| `taxonomy_translations` / `taxonomy_term_translations` | `<parent>_id` (FK, cascade), `language_id` (FK, cascade), `name` | see *Localization module*; unique `(<parent>_id, language_id)` |
| `product_taxonomy_term` | `product_id` (FK, cascade), `taxonomy_term_id` (FK, cascade) | m2m pivot, unique pair |

`slug` is set explicitly (form / factory / seeder). When left blank in the panel
it is derived from the base-language name by `HandlesTranslatableName` — unique
globally for a taxonomy, per-taxonomy for a term.

### Model API

```php
$taxonomy->terms;                 // HasMany<TaxonomyTerm>
$taxonomy->name;                  // read-only accessor → base-language name
$taxonomy->translate('en');       // ?TaxonomyTranslation
$term->taxonomy;                  // BelongsTo<Taxonomy>
$term->parent; $term->children;   // self-referencing hierarchy
$term->products;                  // BelongsToMany<Product>
$term->descendantIds();           // list<int> — self excluded, used to keep parent pickers cycle-free
$product->taxonomyTerms();        // BelongsToMany, via product_taxonomy_term
```

### Admin

- **`TaxonomyResource`** (`/admin/taxonomies`) — CRUD; the name is edited through
  the tab-per-active-language block, `slug` alongside.
- Opening a taxonomy shows a **Terms** relation manager: a table of its terms
  (name, parent, slug, children count) with a form (name tabs + `slug` +
  **Parent** select limited to that taxonomy, excluding the term itself and its
  descendants).
- The **Products** form has a multiple *Taxonomy terms* select (`relationship()`
  mode → the pivot syncs automatically); options and the products-list *Terms*
  column read as `"Taxonomy: Term"`. The products list also has a bulk
  **"Assign taxonomy terms"** action (`syncWithoutDetaching` onto every selected
  product).

---

## Pricing module

Multiple price lists, one price per product per list, and a bulk editing screen.

### Tables

| Table | Columns | Notes |
|---|---|---|
| `price_lists` | `id`, `name`, `is_default` (bool), `active` (bool) | exactly one default at all times |
| `product_prices` | `product_id` (FK, cascade), `price_list_id` (FK, cascade), `price` `decimal(10,2)` | unique `(product_id, price_list_id)` |

**Single default** (same pattern as `is_base` on languages): `PriceList`'s
`saving` hook demotes any previous default (and forces the new one active); the
`deleting` hook throws for the default. In the panel `is_default` is not a form
field — a **"Set as default"** row action flips it, the `active` toggle is
disabled for the default row and Delete is hidden for it.

### Model API

```php
$list->prices;            // HasMany<ProductPrice>
$list->products;          // BelongsToMany<Product> withPivot('price')
PriceList::default();     // ?PriceList
$product->prices;         // HasMany<ProductPrice>
```

### Admin

- **`PriceListResource`** (`/admin/price-lists`, "Pricing" nav group) — CRUD for
  name + active. On **create**, an optional *"Populate prices from another list"*
  section copies every price from a source list applying a signed percentage
  (`round(price * (1 + pct/100), 2)`), handled in `HandlesPricePopulation`.
- **`ManagePrices`** page (`/admin/prices`, "Bulk price editing") — an
  **Excel-like grid** (jspreadsheet CE v4, vendored — see *Deployment*) of every
  product's price in one list: drag-select, paste a block from a real
  spreadsheet, drag-fill. Edits are debounce-batched to `saveCells()`
  (`updateOrCreate` per row, blank = delete). A toolbar on top carries:
  - price-list select, name/SKU search, with/without-price filter, category
    filter, and a **Columns** show/hide row;
  - **saved views** (see below) that store exactly those filters + columns;
  - bulk actions: *set fixed price*, *±% on the grid selection*, *±% by
    taxonomy category* — the ± actions only touch rows that already have a
    price **in the selected list** (missing ones skipped, other lists never).
  - loads all filtered rows up to a 1000 cap (banner past it).
- The **Products** and **variant** forms carry a *Prices* table
  (`ProductPricesTable`) — a fixed row per **active** price list, only the price
  editable, no add/remove. A blank price means "no price on that list"; clearing
  a previously-set price **deletes** the `product_prices` row rather than storing
  null. `ProductPriceMatrix` (`Modules/Pricing/app/Support/`) reads/writes that
  grid for one product (same rule as `ManagePrices`); `PriceAdjuster` holds the
  reusable set/adjust logic.

`PricingSeeder` creates a `Standard` default list; `ProductPriceSeeder` gives
every product a price in every active list (both idempotent).

---

## SavedViews module

Reusable per-user snapshots of a screen's filters + visible columns.

- `saved_views` (`user_id` FK cascade, `resource` string, `name`, `filters`
  json, `columns` json; unique per `user_id + resource + name`).
- `SavedView` model — array casts, `forUser()` / `forResource()` scopes.
- **`InteractsWithSavedViews`** trait: a Livewire page implements
  `savedViewResourceKey()`, `captureViewState()`, `applyViewState()`; the trait
  adds a `savedViewId` property, the per-user option list, an
  `updatedSavedViewId` hook that restores state, and Save / Update / Delete
  Filament actions. Applied to the price grid (`resource: "pricing.prices"`) and
  to the **product list** (`resource: "products"`, `ListProducts` snapshotting
  `tableFilters` + the column-manager `tableColumns` state). No panel plugin —
  model + trait only.

---

## ImportGestionali module

Imports products from a CSV / Excel export with a visual column mapping.
Handles **simple** products and, through the optional **"Codice Padre"**
(parent SKU) column, **variable** products — a container plus its variants.
Single-list pricing; a column can also be mapped to a taxonomy to link its
terms (the same mechanism carries a variant's distinguishing attributes,
e.g. Colour / Size).

Structure (`Modules/ImportGestionali/`):

```
app/
├── Filament/
│   ├── ImportGestionaliPanelPlugin.php       # discoverPages + discoverResources
│   ├── Pages/ImportProducts.php              # the 3-step wizard
│   └── Resources/ImportRecords/              # read-only run history + report page
├── Enums/TargetField.php                     # the 13 fixed fields: sku / parent_sku / name / description / price / stock / weight / length / width / height / status / image_url / gallery_urls
├── Jobs/RunProductImport.php                 # queued run for large files
├── Models/ImportRecord.php                   # one import run + its outcome (+ the taxonomy toggles)
├── Console/PruneImportFilesCommand.php       # importgestionali:prune-files
└── Support/
    ├── SpreadsheetReader.php                 # openspout wrapper: sniff + stream
    ├── FieldGuesser.php                      # header → target, it/en synonyms (+ exact taxonomy-name match)
    ├── MappingTarget.php                     # the `taxonomy:{id}` convention: parsing, labels, grouped Select options
    ├── RowMapper.php                         # mapping + positional row → [target => value]
    ├── ProductRowImporter.php                # one row → created | updated | skipped(reason); import() simple, importParent()/importVariant() for variables; dryRun
    ├── VariantImportPlan.php                 # pass 1: pure classification of each row → simple | container | variant
    ├── TaxonomyTermResolver.php              # term names → ids within one taxonomy, create-on-miss, per-run cache
    ├── TaxonomyResolution.php                # per-taxonomy match outcome (found / created / will_create / missing / gone)
    ├── ImportRunner.php                      # stream the file (single pass), or two-pass when a parent_sku column is mapped; keep the report current
    ├── ImageFetcher.php                      # download an image_url / gallery_urls entry (streamed, capped)
    ├── RowOutcome.php  /  FileShape.php  /  FetchedImage.php   # small value objects
    ├── ImageFetchException.php               # per-image failure → a report note, not a skip
    └── UnreadableImportFile.php              # translated, user-safe file-level failure
config/config.php                             # inline_max_rows, inline_max_rows_variants, max_file_mb, issues_cap, preview_rows, disk, prune_days, image_timeout
database/migrations/
├── 2026_08_28_100000_create_import_records_table.php
└── 2026_09_01_000000_add_taxonomy_toggles_to_import_records.php   # create_missing_terms, replace_taxonomy_terms
resources/samples/prodotti_gioielleria_test.xlsx (+ .csv)          # customer-facing model file, with a populated "Codice Padre" column
```

### The wizard (`/admin/import-prodotti`, "Import" nav group)

1. **Upload** — `FileUpload` on the **private** `local` disk (`storage/app/private/imports/`).
   Accepts `.csv` / `.xlsx` / `.ods`. On upload, `SpreadsheetReader::inspect()`
   reads the header, a sample of rows, the data-row count, and (for CSV) the
   delimiter and encoding. A file-level failure clears the upload and blocks the
   step (see below).
2. **Map the columns** — one `Select` per file column (`mapping.{index}`),
   pre-filled by `FieldGuesser` from the header. The options are grouped:
   *Product fields* (the 13 `TargetField` values, `parent_sku` = "Codice Padre")
   and *Taxonomies* — one entry per existing taxonomy, `taxonomy:{id}` (see
   `MappingTarget`), built from `Taxonomy::all()` at render time. Plus three
   toggles (all default off): **"Update products that already exist"**,
   **"Create missing terms automatically"**, **"Replace existing terms for the
   mapped taxonomies"**. On *Next*: exactly one column must map to `sku`, no
   target may be mapped twice (mapping two columns to the same taxonomy is the
   same error), and if `parent_sku` is mapped then `name` must be too.
3. **Preview** — the first `preview_rows` (10) rows run through
   `ProductRowImporter` in **`dryRun`** mode and are shown with their expected
   outcome (*will be created* / *will update the existing one* / *skipped —
   reason*). Same code path as the real import, so the preview matches.

**Confirm** creates an `ImportRecord` and then decides inline vs queued:

| | |
|---|---|
| ≤ `inline_max_rows` (300) rows — or ≤ `inline_max_rows_variants` (**100**) when a `parent_sku` column is mapped — **and** no image column mapped | runs inline in the confirm request; redirects to a finished report |
| over that row count **or** an image column mapped | `RunProductImport` is queued; the report page polls (`wire:poll`) until done |

An image column means one HTTP download per row, so it always goes to the queue
regardless of size. A `parent_sku` column turns on the two-pass variant
algorithm (see below), which costs ~1.5–2× per row and buffers every mapped row
in memory, so it gets the lower inline ceiling (measured ~85 rows/s vs ~135 for
the flat path on in-memory SQLite; a 10 000-row variant file ≈ 2 min, well
inside the queued job's 1800 s timeout).

### Matching and the "update existing" toggle

Rows are matched on **`sku`** (case-insensitive). With the toggle **off**, a row
whose SKU already exists is skipped and listed in the report. With it **on**,
the row updates that product — and **an empty or unmapped value leaves the
existing value untouched** (only non-empty cells are written). A new product
with no `status` defaults to `draft`.

Value parsing: numbers accept both `1.234,56` and `1234.56` and a trailing
`kg`/`cm`; `status` accepts `draft`/`active`/`archived` and the Italian
`bozza`/`attivo`/`archiviato`. The base-language `name` / `description` go
through the Localization module; `price` is written to the default price list
via `ProductPriceMatrix`.

### Images (`image_url` / `gallery_urls`)

Both columns hold **URLs**. `image_url` is a single URL → the `main_image`
collection; `gallery_urls` is a **`|`-separated** list → the `gallery`
collection, in order. `ImageFetcher` downloads each one (streamed, capped at
`media-library.max_file_size`, timeout `image_timeout` = 15 s, content sniffed
to jpg/png/webp, obvious private/loopback hosts refused) and hands it to Spatie
Media Library on the `Product` — the same collections the product form uses.

- **Best-effort**: a URL that will not download does **not** skip the row. The
  product still imports; the failure is a line in the report (*"riga 34:
  immagine principale — download fallito (HTTP 404)"*, *"riga 52: galleria —
  2/3 immagini importate (1 non scaricate)"*).
- **Update semantics** match the other fields: a non-empty cell **replaces** the
  whole collection; an empty or unmapped cell leaves it untouched.
- The preview never downloads anything (`dryRun`).

### Taxonomies (`taxonomy:{id}` columns)

A column mapped to `taxonomy:{id}` holds one or more **term names** separated by
`|` (e.g. `Rosso|Blu`). `TaxonomyTermResolver` matches each name **within that
one taxonomy** — by base-language name (case-insensitive), then by slug — and
links the resolved terms through the existing `product_taxonomy_term` pivot.

- **"Create missing terms automatically"** (default off, mirrors *update
  existing*): off, a name with no match is a report note (*"riga 34: termine
  «Verde» non trovato nella tassonomia Colore, ignorato"*) and the rest of the
  row still imports; on, the term is created as a **root** term of that taxonomy
  (slug from the name, base-language translation) and reused by later rows in the
  same run.
- **"Replace existing terms for the mapped taxonomies"** (default off): off,
  resolved terms are **added** (`syncWithoutDetaching`, like the bulk "assign
  terms" action); on, for each mapped taxonomy the cell's terms **replace** the
  product's current terms of that taxonomy — but only when at least one term
  resolves, otherwise the taxonomy is left untouched. An empty cell never
  changes anything.
- The preview shows the expected match per taxonomy (`Colore: Rosso ✓ · Verde —
  non trovato`), computed by the same `dryRun` path; it never writes.
- Both toggles are stored on the `ImportRecord` so a queued run behaves the same.

### Variable products (`parent_sku` / "Codice Padre" column)

Mapping a column to `parent_sku` switches `ImportRunner` from the single
streaming pass to a **two-pass** algorithm. Row order in the file is irrelevant
— a variant may sit above its parent, or the parent may have no row of its own.

- **Pass 1** (`VariantImportPlan`, pure, no queries): every row is classified as
  `simple` (empty `parent_sku`, no one references its SKU), `container` (empty
  `parent_sku`, another row names its SKU) or `variant of X` (`parent_sku` = X).
  A 2nd top-level row for an already-seen SKU is a `sku_dup_in_file` skip; when
  several variant rows name the same parent, the parent's own cells come from
  the **first** row that defines it and later references contribute only the link.
- **Pass 2a** — top-level rows in file order: simple products through the normal
  `import()`; containers through `importParent()`.
- **Pass 2b** — variant rows in file order through `importVariant()`, wired to
  the container built in 2a. A variant carries its **own** price, stock,
  dimensions and taxonomy terms; if its `name` cell is empty it inherits the
  container's translations (`VariantGenerator::copyTranslations`, the same as the
  admin's "Generate variants").

**A container carries no price, stock or dimensions.** When a row references a
SKU that already exists as a **simple** product, `importParent()` converts it:
`type` → `variable` (the model's `saving` hook nulls `stock` + the four
dimension columns), and its `product_prices` rows are **deleted** in the same
transaction — a container has neither a price field in the form nor a row in the
price grid. The conversion is reported (*"riga N: «X» esisteva come prodotto
semplice ed è stato convertito…"*). It only happens with **"update existing
products" on**; with it off the container row is skipped
(`parent_exists_update_off`) and its variants cascade to a skip, exactly like a
plain `sku_exists`.

Other parent problems (all report notes, the rest of the file still imports):
`parent_not_found` (referenced SKU is neither a row nor an existing product),
`parent_is_variant` (the referenced SKU is itself a variant — nested variants
are not supported), `variant_sku_conflict` (a variant row's SKU already belongs
to a non-variant product).

`resources/samples/prodotti_gioielleria_test.xlsx` (with a `.csv` twin used by
the tests) is the customer-facing model file: simple products, plus a ring in
three sizes and a bracelet in two, with the container rows deliberately out of
order.

### File-level failures vs row-level problems

**File-level** (raised as one translated `UnreadableImportFile`, shown at the
relevant wizard step, nothing is imported):

- not a valid CSV / spreadsheet, or corrupt;
- header row unreadable, or no data rows;
- CSV encoding that is not UTF-8 and not detectable as Windows-1252 / ISO-8859-1
  ("re-export as UTF-8");
- legacy `.xls` (Excel 97-2003) — openspout cannot read it ("save as .xlsx or .csv");
- at the mapping step: no column mapped to SKU.

**Row-level** (the run continues; each is one line in the report). Two kinds:
a **skip** (the whole row is not imported) — SKU missing, SKU duplicated within
the file, SKU already exists (toggle off), name missing on a new product, a
numeric field that is not a number or is negative, stock not a whole number, an
unrecognised `status`, or a variant whose parent could not be built
(`parent_not_found` / `parent_is_variant` / `parent_exists_update_off` /
`variant_sku_conflict`); or a **note** on a row that *was* imported — an image
URL that would not download, a taxonomy term name that was not found, a
simple→variable conversion, a variant imported without a name.

### Report

`ImportRecordResource` ("Esiti import", no create/edit): a list of past runs
with a row + bulk **Delete** action for manual cleanup (deleting a row also
removes its stored source file, via an `ImportRecord` `deleting` hook), and a
**View** page with the created / updated / skipped counts, the run timing, and
the plain-language list of problem rows (`import_records.issues`, capped at
`issues_cap` = 500, then "…and N more").

### `import_records` table

| Column | Notes |
|---|---|
| `user_id` | FK → `users`, `nullOnDelete` |
| `original_filename` | as uploaded |
| `stored_path` | on the `local` disk; nulled by the prune command |
| `status` | `pending` → `processing` → `completed` \| `failed` |
| `update_existing` | bool |
| `mapping` | json — column index → field |
| `meta` | json — header, delimiter, encoding |
| `total_rows` | data rows counted at upload |
| `created_count` / `updated_count` / `skipped_count` | uint |
| `issues` | json — `[{line, reason}, …]` |
| `error_message` | set on a file-level failure |
| `started_at` / `finished_at` | timestamps |

### Scheduling (needs a cron on Netsons)

`routes/console.php` schedules:

```php
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=1 --sleep=1')
    ->everyMinute()->withoutOverlapping()->runInBackground();
Schedule::command('importgestionali:prune-files')->dailyAt('03:10');
```

The shared host runs no queue worker, so this only takes effect once a single
cPanel cron is added:

```
* * * * * cd ~/apps/pim && php artisan schedule:run >> /dev/null 2>&1
```

Until then, imports of **≤ 300 rows still work** (they run inline); a larger one
stays `pending`. `importgestionali:prune-files` deletes stored source files
older than `prune_days` (7); the `ImportRecord` rows (the reports) are kept.

---

## ExportProdotti module

Exports products to **CSV or XLSX**, the natural complement of ImportGestionali
— a file exported here can be fed straight back into the importer (same column
vocabulary as `TargetField`).

Structure (`Modules/ExportProdotti/`):

```
app/
├── Filament/
│   ├── ExportProdottiPanelPlugin.php         # discoverResources
│   └── Resources/ExportRecords/              # read-only run history + report/download page
├── Enums/ExportColumn.php                    # sku / name / description / price / stock / weight / length / width / height / status / image_url / gallery_urls
├── Jobs/RunProductExport.php                 # queued run for large catalogues
├── Models/ExportRecord.php                   # one export run + its outcome
├── Console/PruneExportFilesCommand.php       # exportprodotti:prune-files
└── Support/
    ├── SpreadsheetWriter.php                 # openspout wrapper: streaming CSV / XLSX writer
    ├── ProductExportRow.php                  # one Product (or variant) → the ordered cell values
    └── ExportRunner.php                      # build the query, stream it to the file, keep the record current
config/config.php                             # inline_max_rows, disk, prune_days
database/migrations/2026_08_31_120000_create_export_records_table.php
```

### The "Esporta" action (products list)

`ListProducts` renders an **Esporta** button next to the Filters / Columns
controls. It opens a **slide-over** (native Filament action modal — not the
bespoke filter drawer) with:

- **Formato** — CSV or XLSX (XLSX pre-selected);
- **Colonne da includere** — a `CheckboxList` of the twelve `ExportColumn`
  values, pre-ticked from the columns currently visible in the list (the
  column-manager state: `sku`, `name` → base name, `stock`, the four
  dimensions, `status`, `main_image` → `image_url`; export-only columns
  `description` / `price` / `gallery_urls` have no list column and start
  unticked). If nothing maps, it falls back to `sku, name, price, stock,
  status`.

**What is exported**

- **Every product matching the filters currently applied to the list**
  (`ProductListQuery` — the same faceted taxonomy / price / stock / search /
  type / status / missing-translation clauses the drawer uses), pagination
  ignored.
- **Variants as their own rows**: a `variable` container is written as one row
  (its own name / status / images; no price, stock or dimensions), followed by
  one row per child variant with the variant's own SKU / price / stock /
  dimensions and its own-or-inherited name and images.
- `name` / `description` are the **base-language** translation; `price` is the
  amount on the **default price list**, formatted `1234.56`; `image_url` is the
  public URL of the main image (a variant inherits the parent's), `gallery_urls`
  is the gallery URLs joined with `|`. All values are chosen so the file
  round-trips through ImportGestionali.

### Inline vs queued

The matching **top-level** product count (before variant expansion) decides:

| | |
|---|---|
| ≤ `inline_max_rows` (**1000**) | generated in the request, streamed straight to the browser as a download |
| > 1000 | an `ExportRecord` is created, `RunProductExport` is queued, and the user is sent to the run's report page, which polls (`wire:poll`) until the file is ready and then shows a **Scarica** button |

Same Netsons caveat as the importer: the queue only drains through the cron
`* * * * * cd ~/apps/pim && php artisan schedule:run` — without it a large
export stays `pending`, while everything up to 1000 products still works inline.
`exportprodotti:prune-files` (scheduled `dailyAt('03:20')`) deletes generated
files older than `prune_days` (7); the `ExportRecord` rows are kept. The
ExportRecords list also has a row + bulk **Delete** action for manual cleanup,
which removes the generated file too (an `ExportRecord` `deleting` hook).

### `ProductListQuery` (Products module)

`Modules\Products\Support\ProductListQuery` is the **single source of truth** for
the products-list query: the base scope (`applyBase()` — top-level rows +
eager loads, wired into the table's `modifyQueryUsing`) and one static method
per filter clause. `ProductsTable` routes every filter's `query()` to it, and
`ExportRunner::query()` rebuilds the identical query from a saved `tableFilters`
snapshot — so an export can never drift from what the list shows.

### `export_records` table

| Column | Notes |
|---|---|
| `user_id` | FK → `users`, `nullOnDelete` |
| `format` | `csv` / `xlsx` |
| `columns` | json — the chosen `ExportColumn` keys, stored in canonical order |
| `filters` | json — the `tableFilters` snapshot the list was showing |
| `sort` | json — `{column, direction}` or null |
| `status` | `pending` → `processing` → `completed` \| `failed` |
| `total_rows` | top-level products matched (variant rows added on top at write time) |
| `row_count` | data rows actually written |
| `stored_path` | on the export disk; nulled by the prune command |
| `original_filename` | `export-prodotti-YYYYMMDD-HHMMSS.<ext>` |
| `error_message` | set on a failed run |
| `started_at` / `finished_at` | timestamps |

---

## Branding module

Per-installation panel branding — one client per install, so a **single-row
`settings` table**, no per-user / per-role variant.

Structure (`Modules/Branding/`):

```
app/
├── Models/Setting.php                    # singleton HasMedia; current() / branding() / primaryPalette()
└── Filament/
    ├── BrandingPanelPlugin.php           # wires brandName / brandLogo / colors + discoverPages
    └── Pages/ManageBranding.php          # the "Branding" settings page
database/migrations/2026_08_31_130000_create_settings_table.php
```

### `Setting` (single row)

| Column | Notes |
|---|---|
| `brand_name` | nullable — company / product name (e.g. "Albatross") |
| `primary_color` | nullable — a named Filament palette key (`amber`, `blue`, …), not a hex |

The logo is a Spatie Media Library `singleFile` collection `logo` (jpg / png /
webp, max 5 MB, `public` disk) — same setup as the product images.

- **`Setting::current()`** — the one row, `firstOrCreate`d on first access. Used
  by the settings page (the media upload needs a real model to bind to).
- **`Setting::branding()`** — a `Cache::rememberForever` snapshot
  `['brand_name', 'primary_color', 'logo_url']`, safe before the table exists.
  This is what the panel closures read on every request; the cache is flushed
  by the model's `saved` / `deleted` events and explicitly by the settings page
  after a logo-only change.
- **`Setting::primaryPalette()`** — `Color::all()[$primary_color]` (a named
  Filament palette expanded to shades), or `Color::Amber` (the historical
  default) when unset or unknown. `Setting::primaryPaletteOptions()` builds the
  `value => HTML swatch label` list for the picker (`Setting::PRIMARY_PALETTES`,
  ~10 curated names).

### `ManageBranding` page (`/admin/impostazioni`, "Impostazioni" nav group)

A form: `SpatieMediaLibraryFileUpload` (logo) + `TextInput` (name) + a
`Select` (`->native(false)->allowHtml()`) of ~10 named Filament palettes shown
as colour swatches. `mount()` fills from `Setting::current()`;
`save()` calls `$this->form->getState()` (which persists the logo via the
schema's `saveRelationships()`), updates the two columns and flushes the cache.

`canAccess()` returns `true` for now — **TODO: restringere agli admin quando
arriva il sistema di permessi** (noted in the class).

### Panel wiring (`BrandingPanelPlugin`)

```php
$panel
    ->brandName(fn () => Setting::branding()['brand_name'] ?: (config('app.name') ?: 'Albatross'))
    ->brandLogo(fn () => Setting::branding()['logo_url'])
    ->brandLogoHeight('2rem')
    ->colors(fn () => ['primary' => Setting::primaryPalette()]);
```

All closures — re-evaluated per request from the cached snapshot, so a save
takes effect immediately with **no `filament:optimize-clear` needed**. Filament
shows the logo `<img>` when `brandLogo` is a URL and falls back to the
`brandName` text otherwise, so "logo, else text name, never empty" is the
stock behaviour. The brand name falls through brand setting → `APP_NAME` →
the literal `Albatross` (this is the single Albatross install; `APP_NAME` is
also set to `Albatross` in the server `.env`). `AdminPanelProvider` keeps `->colors(['primary' => Color::Amber])`
as the base; the plugin appends the dynamic `primary` (itself Amber-defaulting).

---

## Dashboard module

Replaces the stock Filament dashboard (`AccountWidget` / `FilamentInfoWidget`,
removed from `AdminPanelProvider`) with a catalogue overview. Everything that
can be is a link.

Structure (`Modules/Dashboard/app/Filament/`):

```
DashboardPanelPlugin.php                  # $panel->widgets([...])
Widgets/
├── ProductOverviewStats.php              # StatsOverviewWidget — status numbers
├── ProductsByCategoryChart.php           # ChartWidget (bar) — clickable
├── ProductsMissingImage.php              # TableWidget — recent, no main image
└── RecentImportIssues.php                # Widget (blade) — last import's skipped rows
```

### `ProductOverviewStats`

Six `Stat`s, each counted through `ProductListQuery::for($filters)` and linking
to `ProductResource::getUrl('index', ['filters' => $filters])` with the **same
`$filters`** — so the number and the list it opens always agree:

| Stat | `$filters` |
|---|---|
| Prodotti attivi / Bozze / Archiviati | `status.value = active` / `draft` / `archived` |
| Senza prezzo | `price.presence = without` + `price.price_list_id = <default>` — only shown when a default price list exists |
| Stock a zero | `stock.level = zero` |
| Traduzione mancante | `missing_translation.value = *` (see below) |

### `missing_translation` — the `*` option

`ProductListQuery::missingTranslation()` accepts, besides a language code, the
value **`'*'`** — products missing a translation in *at least one* active
language:

```php
whereHas('translations',
    fn ($q) => $q->whereIn('language_id', $activeIds),
    '<', count($activeIds));
```

(products with no translations included). It is also a real option in the
products filter drawer, labelled *"Una qualsiasi lingua attiva"*.

### `ProductsByCategoryChart`

Bar chart of product count per term of the category taxonomy —
`config('dashboard.category_taxonomy_slug')` when set, otherwise the first
taxonomy whose slug starts with `categor` (the panel creates it as "Categorie"
→ slug `categorie`; seeded data used `categoria`). Each bar's value is
`ProductListQuery::for(['taxonomy_terms' => ['terms' => [$term->id]]])->count()`
— the same clause (subtree expansion included) the bar links to, so clicking a
bar opens the list showing exactly that many rows. The click is a Chart.js
`onClick` in `getOptions()` (`RawJs`) reading a `urls` array carried on the
dataset. Empty (no bars) when there is no such taxonomy.

### `ProductsMissingImage`

`TableWidget` — the 8 most recently created top-level products with no
`main_image` media. `recordUrl` → the product form.

### `RecentImportIssues`

A blade widget: the most recent **completed** `ImportRecord` with
`skipped_count > 0`, its first 10 `issues` lines, and a link to that run's
report (`ImportRecordResource::getUrl('view', …)`). Skipped rows are an
import-only concept, so this always points at ImportRecords. Empty state when
no recent import dropped any rows.

---

## WooSync module

Pushes products to a single **WooCommerce** store and reads their stock back.
Sold as a **separate add-on**: it is off unless the installation opts in, and
when off it contributes nothing to the panel.

### Commercial feature flag (Laravel Pennant)

`config('woosync.enabled')` ← `WOOSYNC_ENABLED` (`.env`, default `false`). The
gate is `Modules\WooSync\Support\WooSync::enabled()`, read straight from config
because `WooSyncPanelPlugin::register()` consults it while the panel is being
built (before the module provider defines the Pennant feature). A matching
Pennant feature `woosync` is still defined from the same value
(`WooSync::defineFeature()`), so `Feature::active('woosync')` and `@feature`
work. Pennant runs on the **`array`** store (`config/pennant.php`) — the flag is
global, no `features` table.

With the flag **off**: `WooSyncPanelPlugin` discovers no pages/resources, the
`WooSyncServiceProvider` registers no product actions → no "WooCommerce" nav
group, no connection page, no report, no sync buttons.

### Module boundaries

Depends (one way) only on Products / Pricing / Taxonomies / Localization. The
products-list actions are injected through a generic seam in the Products
module — `Modules\Products\Support\ProductRowActions` (a `record()` / `bulk()`
registry that `ProductsTable` spreads into its actions). Products never
references WooSync. The PIM↔Woo id map lives in **`woosync_product_links`**
(not a column on `products`), so removing the module is a table drop.

### Connection (`ManageWooSync`, `/admin/woocommerce`, "WooCommerce" nav group)

`woosync_settings` — single-row singleton (`WooSyncSetting::current()`), same
shape as Branding's `Setting`. Fields: `store_url`, `consumer_key`,
`consumer_secret` (both `encrypted` cast). **HTTPS is required** — the URL field
rejects `http://`. "Testa connessione" probes `GET /wp-json/wc/v3/system_status`
with the values in the form and records `last_test_ok` / `last_test_message`.

Auth is **HTTP Basic** (consumer key/secret) over TLS — WooCommerce's standard
scheme for an HTTPS store. A plain-HTTP store would need OAuth 1.0a request
signing; that is deliberately **not** implemented. The current test store
(`https://www.foxynet.it/demo/warpress/`) is HTTPS and works with Basic Auth.

### REST client

`Modules\WooSync\Support\Http\BasicAuthWooClient` implements
`Contracts\WooCommerceClient` on Laravel's `Http` client (no third-party SDK).
Every non-2xx / transport failure maps to a `WooSyncException` subclass with a
translated reason: `StoreUnreachable` (connection), `AuthenticationFailed`
(401/403), `ResourceGone` (404), `RateLimited` (429, reads `Retry-After`),
`RequestRejected` (other 4xx, carries Woo's `message`), `StoreError` (5xx). One
retry on `ConnectionException` only. Bound in the container behind the contract
so the runner and payload builders never see the transport (tests use
`Http::fake()` or `Tests\Support\FakeWooClient`).

### Sync (`SyncProductsAction` — row action + bulk action on the products list)

Both hidden until the connection is configured. Creates a `woosync_runs` row
and, at `≤ config('woosync.inline_max_products')` (default **25**), runs it
inline; above that, queues `RunWooSync` (needs the `queue:work
--stop-when-empty` cron, like imports/exports). The user lands on the run's
report.

`WooSyncRunner` per product (`WooSyncRunnerTest` covers each path):

- **simple only** — `variable` / `variant` → `skipped` with a reason; no SKU →
  `skipped`.
- **create vs update** — linked (`woocommerce_id`) → `GET /products/{id}` then
  `PUT`; a `404` on the `GET` drops the stale id (and stock baseline) and
  recreates. Not linked → `GET /products?sku=` to adopt an existing store
  product, else `POST`.
- **fields** — `sku`, `name` + `description` (base language), `regular_price`
  (default `PriceList`; missing → still pushed, noted in the report), `weight` +
  `dimensions`, `images` (main then gallery, by public URL), `categories` (see
  below), and the reconciled stock fields below.
- **stock reconciliation** — never a blind overwrite: both sides move between
  syncs (PIM for production / corrections, store for sales), so each link keeps
  a `last_known_stock` baseline.
  - *first sync* (no baseline): `PUT`/`POST` `manage_stock: true`,
    `stock_quantity: <PIM stock>`; baseline ← that value. Same when a linked
    product 404s ("recreated") and when an already-linked store product is
    stock-synced for the first time (the PIM value wins, overwriting the store —
    the agreed bootstrap).
  - *later syncs*: `new = store_quantity + (PIM_stock - last_known_stock)`
    (the store's quantity already reflects sales; the parenthesised term is the
    PIM-side change). `new` is written to **both** the store and `product.stock`
    (`saveQuietly`), and the baseline moves to it. `new < 0` is clamped to `0`.
  - *store stock management off* (`manage_stock: false` on the store): not
    forced back on — no stock in the `PUT`, both sides left as they are, the
    baseline is dropped (so re-enabling it is a clean first sync), and the
    report row says "gestione stock disattivata su WooCommerce".
  - the GET→PUT gap is a small race window: a store-side stock edit in between
    is not seen and gets reconciled away on the next sync.
- **rate limit** — a `429` anywhere stops the whole run (`status = failed`,
  partial progress kept); a small `request_delay_ms` pause (default 250ms) sits
  between products.

`CategoryResolver` maps the product's terms in the **"Categorie"** taxonomy
(slug `categorie`) to native Woo product categories: match by base-language
name within the parent, create what's missing (parent first), and remember each
mapping in `woosync_category_links`.

### Report (`WooSyncRunResource`, `/admin/sincronizzazioni-woocommerce`)

Read-only (`canCreate() = false`), same shape as Import/Export reports: status
badge, per-outcome counts, and a `RepeatableEntry` over the JSON `items`
(`product`, `sku`, `result` = created/updated/skipped/failed, `reason`). The
view blade `wire:poll.5s` while the run `isRunning()`. Delete action + bulk
delete.

### Tables

`woosync_settings`, `woosync_runs`, `woosync_product_links` (`unique(product_id)`,
`images_hash`, `last_known_stock` — the stock-reconciliation baseline),
`woosync_category_links` (`unique(taxonomy_term_id)`). All in
`Modules/WooSync/database/migrations/`.

---

## Interface localization

The **panel UI** (labels, buttons, notifications) is translatable, separately
from product content. Two interface languages ship: **Italian and English**,
`it` is the default (`APP_LOCALE=it`).

- **Custom strings** live in `lang/it/pim.php` and `lang/en/pim.php` under the
  `pim.*` namespace (`pim.field.*`, `pim.action.*`, `pim.notification.*`, …).
  Every hardcoded label across the module Filament layers goes through
  `__('pim.…')`; resource names and the Pricing page title/nav resolve via
  `getModelLabel()` / `getPluralModelLabel()` / `getNavigationLabel()` /
  `getNavigationGroup()` / `getTitle()` overrides so they follow the request
  locale.
- **Framework strings** (Filament's own "Create", "Save", "Are you sure?",
  pagination, system notifications) need no package — Filament v4 ships complete
  `it` translations for all its sub-packages, active whenever
  `app()->getLocale() === 'it'`.
- **Switcher**: [`bezhansalleh/filament-language-switch`](https://github.com/bezhanSalleh/filament-language-switch)
  v5, configured in `AppServiceProvider` (`it`/`en`, native labels). It
  auto-registers a topbar control and the locale middleware.
- **Persistence is per user, not per browser session**: the `users.locale`
  column holds each account's last choice. The switcher reads it as the default
  (`userPreferredLocale()`), and a `LocaleChanged` listener writes every change
  back. A fresh login on any device restores the stored preference.

---

## Working with modules

```bash
php artisan module:make Blog                        # scaffold a new module
php artisan module:make-model Post Blog
php artisan module:make-migration create_posts_table Blog
php artisan module:migrate Blog                     # run one module's migrations
php artisan module:list
php artisan module:enable Blog       # / module:disable Blog
```

### Adding a Filament resource to a module

```bash
# 1. generate against the module model
php artisan make:filament-resource Post \
  --model-namespace="Modules\Blog\Models" \
  --generate

# 2. move app/Filament/Admin/Resources/Posts/* into
#    Modules/Blog/app/Filament/Resources/Posts/ and rewrite the namespaces
#    to Modules\Blog\Filament\Resources\Posts(...)

# 3. create Modules/Blog/app/Filament/BlogPanelPlugin.php (use Products as the
#    template) and add BlogPanelPlugin::make() to AdminPanelProvider->plugins()

composer dump-autoload && php artisan optimize:clear
```

`--resource-namespace` is ignored when a single panel exists, so the generator
always writes into the app panel namespace — hence the manual move.

On the server the panel is cached, so after deploying a new module or resource
run `php artisan filament:optimize-clear` (or the full `php artisan optimize`) —
without it the new pages/resources get no route and no menu entry. See
*Deployment*.

---

## Deployment (Netsons shared hosting)

- Document root points at `public/`. PHP 8.4.
- The server database is **MySQL/MariaDB** (`.env` on the server sets
  `DB_CONNECTION=mysql`); the SQLite default applies only to a fresh local clone.
- Node is **not** available on the server, so the compiled `public/` assets are
  committed to the repo. Third-party JS/CSS is vendored, not npm-built: e.g.
  jspreadsheet CE v4 + jsuites (both MIT) live in
  `Modules/Pricing/resources/js/vendor/`, are registered with `FilamentAsset`
  in `PricingPanelPlugin`, and `php artisan filament:assets` publishes them to
  `public/js/pricing` + `public/css/pricing` (committed). Re-run
  `php artisan filament:assets` after changing a vendored file — the
  *Git hooks* pre-commit hook (see *Local setup*) does this automatically for
  a staged change under `Modules/*/resources/{js,css}/**`.
- **HTTPS is enforced**: `public/.htaccess` 301-redirects plain HTTP to HTTPS
  (guarded on `%{HTTPS}` and `X-Forwarded-Proto` so it is loop-safe), and
  `AppServiceProvider` calls `URL::forceScheme('https')` in production.
- Outbound SSH on port 22 is blocked, so `origin` uses SSH over 443
  (`ssh://git@ssh.github.com:443/...`) with a repo deploy key.
- **No long-running queue worker.** `QUEUE_CONNECTION=database`, and the
  scheduler (`routes/console.php`) runs `queue:work --stop-when-empty` every
  minute plus `importgestionali:prune-files` daily — but only if a single cPanel
  cron drives it:

  ```
  * * * * * cd ~/apps/pim && php artisan schedule:run >> /dev/null 2>&1
  ```

  Without that cron, queued jobs never run: a large ImportGestionali import
  (> 300 rows) stays `pending`, while smaller ones still work because they run
  inline. See *ImportGestionali module → Scheduling*.

Deploy pull:

```bash
cd ~/apps/pim
git pull
composer install --no-dev --optimize-autoloader
php artisan optimize:clear          # drop stale caches before migrating
php artisan migrate --force
php artisan optimize                # rebuild caches: config/route/view/event + Filament
```

Order matters: clear the caches first so `migrate` and the other artisan
commands run against the fresh config, then rebuild the caches with `optimize`
as the **last** step, after `migrate`.

`php artisan optimize` writes the production caches to `bootstrap/cache/`
(`config.php`, `routes-v7.php`, `events.php`, `blade-icons.php`) and the Filament
panel to `bootstrap/cache/filament/panels/admin.php`. Because config, routes and
the panel's discovered pages/resources are then frozen, **re-run `php artisan
optimize` after any change to `.env`, `config/*` or the route files — and after
adding a module, page or resource to the panel**, otherwise the change is not
picked up. A stale panel cache is easy to miss: it leaves a new module's pages
and resources with **no route and no navigation entry** even though its plugin is
correctly listed in `->plugins([...])` (the page is still reachable from a
`Livewire::test()`, which bypasses panel routing). Use `php artisan
optimize:clear` to drop all caches (e.g. when debugging), or `php artisan
filament:optimize-clear` for just the panel cache.

For `route:cache` to succeed, route files must not use Closure handlers — use
`Route::view()` / controllers instead (the `/` route already does).

---

## Useful commands

```bash
php artisan route:list --path=admin/products
php artisan optimize                # build production caches (config/route/view/event/filament)
php artisan optimize:clear          # drop all of the above
php artisan make:filament-user
composer test                       # config:clear + artisan test
./vendor/bin/pint                   # code style
```

> **Tests + cached config:** `php artisan test` reads the DB connection from
> `phpunit.xml` (`sqlite` `:memory:`), but a cached `bootstrap/cache/config.php`
> overrides it and the suite then runs against the **real** database. Always
> `php artisan config:clear` (or use `composer test`, which does it for you)
> before running tests on the server, then `php artisan optimize` afterwards.
