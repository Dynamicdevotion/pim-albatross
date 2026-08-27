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
│       ├── Concerns/HandlesProductTranslations.php  # per-locale load/save
│       ├── Schemas/ProductForm.php          # create/edit form schema (+ translation tabs)
│       └── Tables/ProductsTable.php         # list table: columns, filters, actions
├── Http/Controllers/ProductsController.php
├── Models/Product.php
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
| `sku` | string | unique, required |
| `external_id` | string | nullable |
| `status` | string | default `draft` (`draft` / `active` / `archived` in the UI) |
| `created_at` / `updated_at` | timestamp | |

Mass-assignable: `sku`, `external_id`, `status`. Translatable content
(`name`, `description`) lives in the Localization module — see below.

### Admin routes

| Route | Name |
|---|---|
| `GET /admin/products` | `filament.admin.resources.products.index` |
| `GET /admin/products/create` | `filament.admin.resources.products.create` |
| `GET /admin/products/{record}/edit` | `filament.admin.resources.products.edit` |

All behind auth; unauthenticated requests redirect to `/admin/login`.

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
  column read as `"Taxonomy: Term"`.

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
  committed to the repo.
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
