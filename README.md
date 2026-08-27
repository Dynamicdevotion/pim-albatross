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

Multilingual product content (`name`, `description`), **independent from
`APP_LOCALE`** (which only sets the admin UI language). Translations live in a
satellite table, one row per `(product_id, locale)`, with **no automatic
fallback** — a missing row shows as empty.

### Supported locales — single source of truth

`Modules/Localization/app/Enums/Locale.php`:

| Code | Language | |
|---|---|---|
| `it` | Italiano | base / default |
| `en` | English | |
| `es` | Español | |
| `fr` | Français | |
| `de` | Deutsch | |

`Locale::default()` → `it`, `Locale::values()` → the code list. Migration, model,
Filament form and table all iterate `Locale::cases()` — languages are never
hardcoded elsewhere.

### `product_translations` table

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | PK |
| `product_id` | bigint | FK → `products`, cascade on delete |
| `locale` | string(5) | |
| `name` | string | required — a row exists only if it has a name |
| `description` | text | nullable; stores RichEditor HTML |
| `created_at` / `updated_at` | timestamp | |

Unique on `(product_id, locale)`.

### Model API

`Modules\Localization\Models\ProductTranslation` — `belongsTo(Product)`.
`Product` (Products module) gains:

```php
$product->translations;               // HasMany<ProductTranslation>
$product->translate('en');            // ?ProductTranslation, null if absent (no fallback)
$product->translate(Locale::German);  // the enum works too
```

### Editing translations

No dedicated Filament resource. The **Products** create/edit form has a `Tabs`
block with one tab per locale (`name` + `description` RichEditor, base-locale
name required). Load/save is handled by `HandlesProductTranslations`
(`Modules/Products/app/Filament/Resources/Products/Concerns/`): rows are written
to `product_translations` in `afterCreate` / `afterSave`, and a locale left
without a name is pruned. The products list shows the base-locale name in its
first column.

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
