# ISH Core

A reusable Laravel 13 base for building apps as **modules**. The core ships only what every app needs:

- Staff authentication: login, MFA (TOTP and recovery codes), password reset, session hardening
- **Multi-tenancy**: businesses, with users and roles scoped to their business
- Roles & permissions (spatie), with a locked `Admin#<id>` role per business
- **Superadmin** panel: businesses, modules, platform settings
- **Module system** (nwidart/laravel-modules): install / update / uninstall / enable / disable, plus a hook bus
- UI kit: layouts, Blade components, sidebar, dashboard, notifications, audit log, PDF/Excel list exports

Everything app-specific lives in `Modules/<Name>`. The bundled **Frontend** module is the reference example:

- Every business gets a public website at `/s/<slug>`, editable from the **Website** section of the sidebar.
- Each site has its own customer accounts.
- When the primary business publishes its site, `/` shows it.

The design follows `docs/modules-roles-superadmin-reference.txt` (blueprint §5) and avoids the pitfalls listed in its §6.

---

## Install

```bash
composer install
cp .env.example .env && php artisan key:generate
npm install && npm run build

php artisan core:install          # migrates, then asks for the first business + superadmin
php artisan serve                 # http://localhost:8000 → staff login
```

`core:install` accepts `--business= --name= --email= --password=` for non-interactive use. Passwords must be at least 12 characters, with mixed case, a number and a symbol, and must not appear in a known data breach.

You can also open `/app/register` on an empty database: the first sign-up becomes the superadmin.

Want public websites? Install the Frontend module (see below), then use **Website → Edit website** to fill in and publish your site. Until the primary business publishes, `/` goes straight to the staff login.

---

## Concepts

### Businesses (tenants)

- `businesses` holds the tenant and its branding (name, colour, logo, favicon).
- `users.business_id` ties every staff user to one business.
- `App\Support\Tenancy\CurrentBusiness` resolves the tenant on every request from the signed-in staff user. It is never cached in the session, so changes apply on the next request.
- Tenant-owned models use the `App\Models\Concerns\BelongsToBusiness` trait. It adds a global scope and fills `business_id` on create.
  - `User` is deliberately **not** globally scoped: the auth guard loads it.
  - Use `UserRepository::forBusiness()` instead.
- `{user}` and `{role}` route bindings only resolve inside the current business.
- `BusinessSetupService::createWithOwner()` is the **only** way a business is created. Self-registration, the superadmin panel and `core:install` all go through it. It creates the business, its owner and the `Admin#<id>` role, then fires the `after_business_created` hook.

### Roles & permissions

- Permission names follow `"{action} {module}.{controller}"`, e.g. `list users.user`.
  - Core permissions are in `config/access.php`.
  - Modules add theirs through the `user_permissions` hook (same shape).
- Roles are stored as `Name#<business_id>` with a `business_id` column. `$role->display_name` strips the suffix.
- `Gate::before` (in `AppServiceProvider`) has two branches:
  - Platform abilities (`superadmin`, `manage_modules`, `manage_businesses`) are granted **only** to users with `is_superadmin`.
  - Everything else is automatically **true** for the business's `Admin#<id>` role, including permissions added later by modules. Everyone else falls through to spatie.
- Protect routes with `->middleware('permission:list users.user')`. A pipe means OR: `'permission:create x.y|update x.y'`.
- Rules:
  - Only an Admin can grant Admin.
  - A business always keeps at least one active Admin.
  - The Admin role can't be edited or deleted.

### Superadmin

- A boolean `users.is_superadmin` (not a role, not an `.env` list). The first user created is the superadmin.
- `/app/super-admin` (middleware `superadmin`):
  - **Businesses**: create, activate or deactivate, per-business module toggles, reset the owner's password.
  - **Modules**: install / update / uninstall / enable / disable.
  - **Platform settings**: allow business sign-ups; auto-activate new businesses.
- Deactivating a business or suspending a user takes effect on their **next request** (`business.active` middleware).

### Modules

A module is **active** only when both of these hold:

1. Its code is enabled (nwidart, `modules_statuses.json`).
2. It is installed: the `system` table has a `<lowername>_version` row.

Installing runs the module's own migrations. Plain `php artisan migrate` does **not** run module migrations (see `App\Support\Modules\CoreModuleServiceProvider`).

```bash
php artisan core:module list
php artisan core:module install Frontend
php artisan core:module update Frontend            # when module.json "version" is higher
php artisan core:module disable Frontend           # keeps data
php artisan core:module enable Frontend
php artisan core:module uninstall Frontend         # keeps tables
php artisan core:module uninstall Frontend --purge # rolls back its migrations
```

A superadmin can also switch an installed module off for a single business (`businesses.enabled_modules`).

---

## Billing module (packages & subscriptions)

Install it with `php artisan core:module install Billing`. Once it's installed, **packages decide what each business can use**:

- **Packages** (Super Admin → Billing → Packages): price, interval, trial days, included modules, limits (e.g. max users), private or only for selected businesses, popular, once per business.
- **Subscriptions**: pending → trial / active → past due (grace period, modules stay on) → suspended (paid modules locked). Paying any open invoice restores access.
  - Each subscription keeps a frozen copy of its package's terms.
  - **Upgrades** are prorated and take effect once paid; **downgrades** wait for the end of the period.
- **Invoices**: numbered and taxed (one currency, optional tax inclusive/exclusive), with coupons, account credit and a PDF.
- **Payments are offline**: the business sends proof from its Billing page and a superadmin approves it, or the superadmin records it directly.
- **Coupons**: percent or fixed amount; first invoice, first N invoices, or every invoice; usage limits, expiry, limited to certain packages.
- **Daily job**: `php artisan billing:run`. Run the scheduler in production (`* * * * * php artisan schedule:run`), or `php artisan schedule:work` locally.
- The superadmin's own business is never locked. Modules marked `"billing_exempt": true` (like Billing) are always available.

In code, the core only knows `App\Contracts\Entitlements`, which Billing implements:

- `$business->canUseModule('Inventory')` checks whether a module is included.
- `app(Quota::class)->canAdd($business, 'users')` checks a limit; `->middleware('quota:users')` enforces it on a route.
- A module adds its own limit with `app(Quota::class)->register('inventory.products', fn ($business) => ...count...)`.

## Building a module

```bash
php artisan module:make Inventory
```

Then:

1. **Provider**: extend the core base, not nwidart's:

   ```php
   use App\Support\Modules\CoreModuleServiceProvider;

   class InventoryServiceProvider extends CoreModuleServiceProvider { /* $name, $nameLower, $providers */ }
   ```

2. **`module.json`**:
   - Add a `"version": "1.0.0"` and a short `"description"`. Bump the version when you add migrations, then run `core:module update` (or click **Update** on the Modules page).
   - Optionally add `"menu_group": "Inventory"` to name the module's sidebar section.

3. **Routes** (`routes/web.php`): use the same stack as the core staff area, plus `module:<Name>`:

   ```php
   Route::prefix('app/inventory')->name('inventory.')
       ->middleware(['web', 'auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache', 'module:Inventory'])
       ->group(function () {
           Route::get('/products', [ProductController::class, 'index'])
               ->middleware('permission:list inventory.product')->name('products.index');
       });
   ```

   In the module's `RouteServiceProvider`, drop the `api` mapping unless you need it.

4. **Models**: extend `App\Models\BaseModel` (UUID route keys, audit columns, activity log) and `use BelongsToBusiness` for tenant data. Give their tables a `business_id` foreign key.

5. **Views**: use `<x-layouts.app>` and the `x-common.*` / `x-dashboard.*` components. Reference module views as `inventory::products.index`.

6. **Hooks**: create `app/Http/Controllers/DataController.php`. The core calls whichever of these methods exist, on active modules only:

   | Hook | Return | Used by |
   |---|---|---|
   | `user_permissions()` | `['access_modules' => ['inventory' => ['product']], 'extra_permissions' => [], 'module_labels' => [], 'controller_labels' => []]` | permission seeding + role form |
   | `modifyAdminMenu()` | list of `['label', 'route', 'permission', 'order', 'icon', 'active']` | the module's own sidebar section (`group` overrides it) |
   | `dashboard_stats()` | list of `['label', 'value', 'href'?, 'permission'?]` | dashboard tiles |
   | `dashboard_quick_actions()` | list of `['label', 'route', 'permission'?, 'color'?, 'icon'?]` | dashboard quick actions |
   | `dashboard_widget()` | a view name or HTML string | dashboard body |
   | `after_business_created($args)` | — (`$args['business']`, `$args['owner']`) | seed per-business data |
   | `landing_page()` | controller callable, e.g. `[HomeController::class, 'index']` | what `/` shows |

   - Hooks only run for modules that are installed, enabled, and switched on for the current business. A module that's off for a business disappears from its sidebar and dashboard.
   - To add a new hook to the core, call `app(ModuleManager::class)->hook('name', $args)` at the extension point.

7. **Tests**: put them in `Modules/<Name>/tests` (the `Modules` phpunit suite picks them up). Install the module in `setUp()`:

   ```php
   app(\App\Services\Module\ModuleManager::class)->install('Inventory');
   ```

   See `Modules/Frontend/tests/Feature/FrontendTest.php`.

---

## Layout

```
app/
  Console/Commands/        core:install, core:module
  Http/Controllers/Web/    Auth, Dashboard, User, Role, Setting, Notification, ActivityLog, SuperAdmin, Landing
  Http/Middleware/         business.active, superadmin, module, permission, security headers, ...
  Models/                  User, Business, Role (tenant), System (key/value), ActivityLog, Notification
  Services/                Business, Module (ModuleManager), Permission (AccessCatalogue), User, Role, ...
  Support/                 Menu/AdminMenu, Modules/CoreModuleServiceProvider, Tenancy/CurrentBusiness
config/access.php          core permissions
config/menu.php            core sidebar
Modules/Frontend/          example module: per-business websites (/s/{slug}), content editor, "customer" guard
```

## Tests

```bash
php artisan test
```
