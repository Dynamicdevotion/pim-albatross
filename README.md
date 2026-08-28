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
enforce it). A `thumb` conversion (`Fit::Contain`, 300×300) is generated
**synchronously** on upload — `queue_conversions_by_default` is `false` and the
conversion is `->nonQueued()`, because the shared Netsons host runs no queue
worker.

**Inheritance.** `Product::getMainImageUrl($conversion = '')` returns the
product's own main image, or — for a `variant` with none of its own — its
parent's, or `null`. Same "own value, then parent" rule already used for variant
names and SKUs.

**Storage.** Files live on the `public` disk at
`storage/app/public/<media_id>/<file>` and are served through the existing
`public/storage` symlink at `APP_URL/storage/<media_id>/<file>` — verified
working on Netsons (the web server follows the owner-matched symlink).
Uploaded media is outside the repo (`storage/app/public/.gitignore`). The
Filament upload fields pin `->disk('public')` explicitly: without it the
component would use `filament.default_filesystem_disk` (`local` here) and the
files would land in the non-public `storage/app/private`.

**Filament.**
- `ProductForm` and the variant form (`VariantsRelationManager`) carry an
  *Immagini* section: a single-file `main_image` upload (with image editor) and
  a multiple, reorderable `gallery` upload, via
  `SpatieMediaLibraryFileUpload`.
- `ProductsTable` and the variant table show a fixed **thumbnail column**
  (`ImageColumn` over `getMainImageUrl('thumb')`, so a variant with no image of
  its own shows the parent's). It is **not** toggleable — always visible.
  `public/images/placeholder-product.svg` is the fallback when there is no image.

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

Unique on `(<parent>_id, language_id)`. The translation models keep an ergonomic
`locale` attribute (read/write by code) over `language_id`.

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
  `php artisan filament:assets` after changing a vendored file.
- **HTTPS is enforced**: `public/.htaccess` 301-redirects plain HTTP to HTTPS
  (guarded on `%{HTTPS}` and `X-Forwarded-Proto` so it is loop-safe), and
  `AppServiceProvider` calls `URL::forceScheme('https')` in production.
- Outbound SSH on port 22 is blocked, so `origin` uses SSH over 443
  (`ssh://git@ssh.github.com:443/...`) with a repo deploy key.

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
(`config.php`, `routes-v7.php`, `events.php`, `blade-icons.php`). Because config
and routes are then frozen, **re-run `php artisan optimize` after any change to
`.env`, `config/*`, or the route files** — otherwise the change is not picked up.
Use `php artisan optimize:clear` to drop all caches (e.g. when debugging).

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
