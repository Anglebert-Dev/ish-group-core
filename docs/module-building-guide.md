# Building and shipping a module

How to build a module for this core, inside this repo or on your own, and get it into a copy someone else runs. Follow it and the module will look like the rest of the system, respect each business's data, and install with one command.

Section 3 is a complete small module, **Tickets**. Copy it as your starting point: it has been installed and its tests run exactly as written here.

## Contents

1. [What a module is](#1-what-a-module-is)
2. [Where to build it](#2-where-to-build-it)
3. [The starter module](#3-the-starter-module)
4. [module.json](#4-modulejson)
5. [Database and models](#5-database-and-models)
6. [Services and controllers](#6-services-and-controllers)
7. [Routes and security](#7-routes-and-security)
8. [Permissions](#8-permissions)
9. [The menu and the dashboard](#9-the-menu-and-the-dashboard)
10. [Hooks: how modules connect](#10-hooks-how-modules-connect)
11. [The UI](#11-the-ui)
12. [Translations](#12-translations)
13. [Printing and exports](#13-printing-and-exports)
14. [Emails and notifications](#14-emails-and-notifications)
15. [Settings for each business](#15-settings-for-each-business)
16. [Commands and scheduled tasks](#16-commands-and-scheduled-tasks)
17. [Outside connections](#17-outside-connections)
18. [Audit](#18-audit)
19. [Billing and limits](#19-billing-and-limits)
20. [Tests](#20-tests)
21. [Shipping](#21-shipping)
22. [Checklist before you ship](#22-checklist-before-you-ship)
23. [Common mistakes](#23-common-mistakes)

## 1. What a module is

A module is one folder, `Modules/<Name>/`, that adds a feature to the core: its own tables, pages, menu items and permissions.

Four rules shape everything else:

- **The core never knows about a module.** Core code doesn't name any module. The core offers **hooks**, and a module answers them (section 10). Delete the folder and the core still runs.
- **Everything belongs to a business.** Many businesses share one database, so every row carries `business_id` and one business must never see another's data (section 5).
- **A module's tables appear when it's installed,** not on `php artisan migrate`. Installing runs its migrations and records its version.
- **Every admin action has a button.** A terminal command can be a twin of a button, never a replacement for it.

The stack: Laravel 13, PHP 8.3+, `nwidart/laravel-modules`, `spatie/laravel-permission`, MySQL or PostgreSQL, Tailwind from a CDN. There is no npm build step.

## 2. Where to build it

**Inside this repo:** create `Modules/<Name>/`, work, commit.

**Outside this repo:** you need a running copy of the core to build against. Build a zip with both **Developer edition** and **Standalone** ticked (site page → Install tab). It has the tests, the guides and the developer packages, and no link to any server. Then:

1. Extract it and run the installer.
2. Put your module in its `Modules/` folder.
3. Run `php artisan core:module install <Name>`.
4. Run tests with `php artisan test --filter=<Name>`.

Your module's classes load without Composer: the core maps `Modules\<Name>\...` to `Modules/<Name>/app/...` and `Modules\<Name>\Tests\...` to `Modules/<Name>/tests/...`.

**Don't start from `php artisan module:make`.** Its provider extends nwidart's class, which loads migrations into the global `php artisan migrate`. That breaks the third rule above. Copy the starter instead. If you do use it, change the provider's parent to `CoreModuleServiceProvider`.

**Names:** `<Name>` is PascalCase (`Tickets`), the alias is lowercase (`tickets`). Views, translations and config all use the alias.

## 3. The starter module

Tickets lets staff log a support ticket and close it. It shows every piece in use: table, model, status enum, service, controller, permissions, menu, dashboard figure, views, translations, audit and tests.

To start your own module, copy it and replace `Tickets`, `tickets` and `ticket` with your names.

```text
Modules/Tickets/
├── module.json
├── composer.json
├── config/config.php
├── app/
│   ├── Enums/TicketStatus.php
│   ├── Http/Controllers/DataController.php
│   ├── Http/Controllers/TicketController.php
│   ├── Models/Ticket.php
│   ├── Providers/TicketsServiceProvider.php
│   ├── Providers/RouteServiceProvider.php
│   └── Services/TicketService.php
├── database/migrations/2026_01_01_000001_create_tickets_table.php
├── lang/en/tickets.php
├── lang/en/audit.php
├── resources/views/index.blade.php
├── routes/web.php
└── tests/Feature/TicketsTest.php
```

**`Modules/Tickets/module.json`**

```json
{
    "name": "Tickets",
    "alias": "tickets",
    "description": "Support tickets: log a request and close it when it is done.",
    "keywords": ["support", "tickets"],
    "version": "1.0.0",
    "category": "operations",
    "menu_group": "Support",
    "priority": 0,
    "providers": [
        "Modules\\Tickets\\Providers\\TicketsServiceProvider"
    ],
    "files": []
}
```

**`Modules/Tickets/composer.json`**

```json
{
    "name": "ish-group/tickets",
    "description": "Support tickets for the ISH core.",
    "extra": {
        "laravel": {
            "providers": [],
            "aliases": {}
        }
    },
    "autoload": {
        "psr-4": {
            "Modules\\Tickets\\": "app/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Modules\\Tickets\\Tests\\": "tests/"
        }
    }
}
```

**`Modules/Tickets/config/config.php`**

```php
<?php

return [
    'name' => 'Tickets',
    'prefix' => 'TK',
];
```

**`Modules/Tickets/app/Providers/TicketsServiceProvider.php`**

```php
<?php

namespace Modules\Tickets\Providers;

use App\Support\Modules\CoreModuleServiceProvider;

class TicketsServiceProvider extends CoreModuleServiceProvider
{
    protected string $name = 'Tickets';

    protected string $nameLower = 'tickets';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
```

**`Modules/Tickets/app/Providers/RouteServiceProvider.php`**

```php
<?php

namespace Modules\Tickets\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Tickets';

    public function map(): void
    {
        Route::middleware(['web', 'module:Tickets'])->group(module_path($this->name, '/routes/web.php'));
    }
}
```

**`Modules/Tickets/database/migrations/2026_01_01_000001_create_tickets_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('subject', 200);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('insert_by')->nullable();
            $table->unsignedBigInteger('update_by')->nullable();
            $table->unsignedBigInteger('delete_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
```

**`Modules/Tickets/app/Enums/TicketStatus.php`**

```php
<?php

namespace Modules\Tickets\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return __('tickets::tickets.status_'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Closed => 'success',
        };
    }
}
```

**`Modules/Tickets/app/Models/Ticket.php`**

```php
<?php

namespace Modules\Tickets\Models;

use App\Models\BaseModel;
use App\Models\Concerns\BelongsToBusiness;
use Modules\Tickets\Enums\TicketStatus;

class Ticket extends BaseModel
{
    use BelongsToBusiness;

    protected $fillable = ['subject', 'details'];

    protected function casts(): array
    {
        return ['status' => TicketStatus::class];
    }
}
```

**`Modules/Tickets/app/Services/TicketService.php`**

```php
<?php

namespace Modules\Tickets\Services;

use App\Services\BaseService;
use App\Support\Documents\DocumentNumber;
use Modules\Tickets\Enums\TicketStatus;
use Modules\Tickets\Models\Ticket;

class TicketService extends BaseService
{
    public function __construct(protected DocumentNumber $numbers) {}

    /**
     * @param  array{subject: string, details?: ?string}  $data
     */
    public function create(array $data): array
    {
        $ticket = new Ticket($data);
        $ticket->number = $this->numbers->next('ticket', (string) config('tickets.prefix', 'TK'));
        $ticket->status = TicketStatus::Open;
        $ticket->save();

        return $this->success($ticket, __('tickets::tickets.created', ['number' => $ticket->number]));
    }

    public function close(Ticket $ticket): array
    {
        if ($ticket->status === TicketStatus::Closed) {
            return $this->failure(__('tickets::tickets.already_closed'));
        }

        $ticket->status = TicketStatus::Closed;
        $ticket->save();

        // A business event gets its own line in the audit log.
        audit('tickets_closed', __('tickets::tickets.audit_closed', ['number' => $ticket->number]), $ticket);

        return $this->success($ticket, __('tickets::tickets.closed', ['number' => $ticket->number]));
    }
}
```

**`Modules/Tickets/app/Http/Controllers/TicketController.php`**

```php
<?php

namespace Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tickets\Models\Ticket;
use Modules\Tickets\Services\TicketService;

class TicketController extends Controller
{
    public function __construct(protected TicketService $tickets) {}

    public function index(): View
    {
        return view('tickets::index', ['tickets' => Ticket::query()->latest('id')->paginate(25)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $result = $this->tickets->create($request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'details' => ['nullable', 'string', 'max:5000'],
        ]));

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }

    public function close(Ticket $ticket): RedirectResponse
    {
        $result = $this->tickets->close($ticket);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
```

**`Modules/Tickets/app/Http/Controllers/DataController.php`**

```php
<?php

namespace Modules\Tickets\Http\Controllers;

use App\Helpers\AuthHelper;
use Modules\Tickets\Enums\TicketStatus;
use Modules\Tickets\Models\Ticket;

/**
 * Hooks the core calls through ModuleManager::hook().
 */
class DataController
{
    public function user_permissions(): array
    {
        return [
            'access_modules' => ['tickets' => ['ticket']],
            'module_labels' => ['tickets' => 'Tickets'],
            'controller_labels' => ['ticket' => 'Tickets'],
        ];
    }

    public function modifyAdminMenu(): array
    {
        return [[
            'label' => 'tickets::tickets.title',
            'route' => 'tickets.index',
            'active' => 'tickets.*',
            'permission' => 'list tickets.ticket',
            'order' => 10,
            'icon' => 'M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z',
        ]];
    }

    public function dashboard_stats(): array
    {
        if (! AuthHelper::can('list tickets.ticket')) {
            return [];
        }

        return [[
            'label' => __('tickets::tickets.dashboard_open'),
            'value' => Ticket::query()->where('status', TicketStatus::Open->value)->count(),
            'href' => route('tickets.index'),
        ]];
    }
}
```

**`Modules/Tickets/routes/web.php`**

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\Tickets\Http\Controllers\TicketController;

Route::prefix('app/tickets')->name('tickets.')
    ->middleware(['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'])
    ->group(function () {
        Route::get('/', [TicketController::class, 'index'])->middleware('permission:list tickets.ticket')->name('index');
        Route::post('/', [TicketController::class, 'store'])->middleware('permission:create tickets.ticket')->name('store');
        Route::post('/{ticket}/close', [TicketController::class, 'close'])->middleware('permission:update tickets.ticket')->name('close');
    });
```

**`Modules/Tickets/lang/en/tickets.php`**

```php
<?php

return [
    'title' => 'Tickets',
    'description' => 'Requests from your team and customers.',
    'number' => 'Number',
    'subject' => 'Subject',
    'details' => 'Details',
    'add' => 'Add ticket',
    'close' => 'Close',
    'close_confirm' => 'Close this ticket?',
    'none' => 'No tickets yet.',
    'status_open' => 'Open',
    'status_closed' => 'Closed',
    'created' => 'Ticket :number created.',
    'closed' => 'Ticket :number closed.',
    'already_closed' => 'This ticket is already closed.',
    'audit_closed' => 'Ticket :number closed',
    'dashboard_open' => 'Open tickets',
];
```

**`Modules/Tickets/lang/en/audit.php`**

```php
<?php

return [
    'tickets_closed' => 'Ticket closed',
];
```

**`Modules/Tickets/resources/views/index.blade.php`**

```blade
<x-layouts.app :title="__('tickets::tickets.title')">
    <x-common.page-header :title="__('tickets::tickets.title')" :description="__('tickets::tickets.description')" />

    @can('create tickets.ticket')
        <form action="{{ route('tickets.store') }}" method="POST" class="bg-card border border-line rounded-xl p-5 mb-6 space-y-3 max-w-2xl">
            @csrf
            <x-common.input name="subject" :label="__('tickets::tickets.subject')" required />
            <x-common.textarea name="details" :label="__('tickets::tickets.details')" :rows="3" />
            <x-common.button type="submit" size="sm">{{ __('tickets::tickets.add') }}</x-common.button>
        </form>
    @endcan

    <div class="bg-card border border-line rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-muted uppercase tracking-wide border-b border-line bg-surface/60">
                        <th class="px-5 py-3 font-semibold">{{ __('tickets::tickets.number') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('tickets::tickets.subject') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('body.status') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('body.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse ($tickets as $ticket)
                        <tr class="hover:bg-surface">
                            <td class="px-5 py-3.5 font-medium text-ink">{{ $ticket->number }}</td>
                            <td class="px-5 py-3.5">{{ $ticket->subject }}</td>
                            <td class="px-5 py-3.5"><x-common.badge :color="$ticket->status->color()">{{ $ticket->status->label() }}</x-common.badge></td>
                            <td class="px-5 py-3.5 text-right">
                                @if ($ticket->status->value === 'open')
                                    <x-common.action-button :action="route('tickets.close', $ticket)" color="success"
                                        data-confirm data-confirm-title="{{ __('tickets::tickets.close_confirm') }}" data-confirm-variant="brand">
                                        {{ __('tickets::tickets.close') }}
                                    </x-common.action-button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-10 text-center text-muted">{{ __('tickets::tickets.none') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-common.pagination :paginator="$tickets" />
    </div>
</x-layouts.app>
```

**`Modules/Tickets/tests/Feature/TicketsTest.php`**

```php
<?php

namespace Modules\Tickets\Tests\Feature;

use App\Models\ActivityLog\ActivityLog;
use App\Services\Module\ModuleManager;
use Modules\Tickets\Enums\TicketStatus;
use Modules\Tickets\Models\Ticket;
use Tests\TestCase;

class TicketsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(ModuleManager::class)->install('Tickets');
    }

    public function test_a_ticket_is_created_listed_and_closed_once(): void
    {
        $this->actingAsStaff();

        $this->post(route('tickets.store'), ['subject' => 'Printer is down'])->assertSessionHas('success');
        $ticket = Ticket::query()->firstOrFail();
        $this->assertStringStartsWith('TK', $ticket->number);

        $this->get(route('tickets.index'))->assertOk()->assertSee('Printer is down')->assertSee(__('tickets::tickets.status_open'));

        $this->post(route('tickets.close', $ticket))->assertSessionHas('success');
        $this->assertSame(TicketStatus::Closed, $ticket->fresh()->status);
        $this->assertTrue(ActivityLog::query()->where('action', 'tickets_closed')->exists());

        $this->post(route('tickets.close', $ticket))->assertSessionHas('error');
    }

    public function test_another_business_cannot_see_or_touch_a_ticket(): void
    {
        $this->actingAsStaff();
        $this->post(route('tickets.store'), ['subject' => 'Private']);
        $ticket = Ticket::query()->firstOrFail();

        $this->flushSession();
        $this->actingAsStaff();

        $this->get(route('tickets.index'))->assertOk()->assertDontSee('Private');
        $this->post(route('tickets.close', $ticket))->assertNotFound();
    }

    public function test_a_member_needs_the_permission(): void
    {
        [$business] = $this->makeBusiness();

        $this->actingAs($this->makeMember($business), 'staff');
        $this->get(route('tickets.index'))->assertForbidden();

        $this->flushSession();
        $this->actingAs($this->makeMember($business, ['list tickets.ticket']), 'staff');
        $this->get(route('tickets.index'))->assertOk();
        $this->post(route('tickets.store'), ['subject' => 'Nope'])->assertForbidden();
    }
}
```

Install and test it:

```bash
php artisan core:module install Tickets
php artisan test --filter=TicketsTest
```

"Tickets" is now under "Support" in the sidebar, its permissions are on the Roles page, and it has its own page on Super Admin → Modules. That page reads your pages, permissions, printed documents and tables from the module itself.

## 4. module.json

| Field | Meaning |
|---|---|
| `name` | The folder name, PascalCase. |
| `alias` | Lowercase. Used for `tickets::` views and translations and `config('tickets.…')`. |
| `description` | Shown on the module's card and its page on Super Admin → Modules. Say what it does in a sentence or two. |
| `version` | `major.minor.patch`. **Raise it with every migration** (section 5). |
| `category` | The section on Super Admin → Modules: `sales`, `operations`, `finance`, `reports`, `website` or `platform`. Any other word gets its own section, after these. |
| `keywords` | Found by the search on the Modules page. |
| `requires` | Other modules this one can't run without, e.g. `["SalesOrders"]`. Installing refuses until they're installed; turning one off turns this off too. |
| `menu_group` | The sidebar section. Modules with the same value share one. Without it, the module's own name is used. |
| `priority` | Load order. Keep `0`, unless the module extends another one in its group: then use `1`. |
| `billing_exempt` | `true` means always free and never sold in packages. Every business gets it, with no switch to turn on. |
| `providers` | Your service provider. |

## 5. Database and models

**Table names.** Prefix them with the module (`tickets`, `delivery_notes`, `sav_accounts`) so they can't clash with the core or another module.

**A main table has these columns:**

```php
$table->id();
$table->uuid('uuid')->nullable()->unique();
$table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
// your columns
$table->unsignedBigInteger('insert_by')->nullable();
$table->unsignedBigInteger('update_by')->nullable();
$table->unsignedBigInteger('delete_by')->nullable();
$table->timestamps();
$table->softDeletes();
```

**A main model** extends `App\Models\BaseModel` and uses `BelongsToBusiness`. That gives you:

| Feature | How it works |
|---|---|
| Business isolation | Every query is limited to the signed-in user's business, and `business_id` is set on create. |
| Safe URLs | Routes use `uuid`, never the database id. An id from another business gives 404. |
| Soft delete | `delete()` keeps the row, and records who deleted it. |
| Who and when | `insert_by` and `update_by` fill themselves in. |
| Audit | Create, update and delete are logged by themselves. |

**A child table** (order lines, for example) can stay simple: extend Laravel's `Model`, still use `BelongsToBusiness`, and keep `business_id` on the table. See `DeliveryNoteLine`.

**Rules:**

- Never put `business_id` in `$fillable`. Let `BelongsToBusiness` set it; set it by hand only when it comes from a trusted parent row.
- Never use `withoutGlobalScopes()` in code a business user can reach. If you truly need it, always add your own `where('business_id', …)`.
- Enforce uniqueness per business: `$table->unique(['business_id', 'number'])`, never `unique('number')`.
- Money is `decimal(15, 2)`, quantities `decimal(15, 3)`. Never floats.
- Use `$table->string('status', 20)` with a PHP enum that has `label()` and `color()`. No database enums; they're hard to change.
- Only reference core tables or tables of modules in `requires` in foreign keys.
- Migrations must run on both MySQL and PostgreSQL. Avoid raw SQL that only one understands.

**Changing the schema after release:**

1. Add a **new** migration. Never edit one that has shipped.
2. **Raise `version` in module.json.** Without this, `core:module update` says "already up to date" and the migration never runs.
3. Run `php artisan core:module update <Name>`.

**Platform-level data** isn't owned by a business (Distribution's customer sites, for example): leave out `business_id` and `BelongsToBusiness`, and only reach it from Super Admin routes.

**Uninstalling:** `core:module uninstall X` keeps the tables; `--purge` rolls back the migrations, so write a correct `down()`.

## 6. Services and controllers

**Controllers stay thin:** validate, call a service, redirect. All logic goes in `app/Services/`.

**Services extend `App\Services\BaseService`** and return `$this->success($data, $message)` or `$this->failure($message)`. Both give an array with `status`, `data` and `message`. The controller then does:

```php
return back()->with($result['status'] ? 'success' : 'error', $result['message']);
```

The layout shows that message as a toast.

- Wrap writes to more than one table in `DB::transaction()`.
- Business mistakes ("already closed") are a `failure()`, not an exception.
- Numbered documents use `App\Support\Documents\DocumentNumber`: `$this->numbers->next('ticket', 'TK')`. It keeps a separate sequence per business, and each business can change its prefix.
- Use `$request->validate()` in the controller, or a FormRequest for long forms.

## 7. Routes and security

**Staff pages** use this exact stack:

```php
Route::prefix('app/tickets')->name('tickets.')
    ->middleware(['auth:staff', 'auth.session', 'exclusive.portal:staff', 'business.active', 'no-back-cache'])
    ->group(function () { /* ... */ });
```

Your `RouteServiceProvider` adds `web` and `module:<Name>` around the file. The `module:` part gives a 404 when the module is off, or sends the business to the upgrade page when it isn't in their package.

**Every route gets its own permission,** even inside a group:

```php
Route::get('/', …)->middleware('permission:list tickets.ticket');
```

| Middleware | Use it for |
|---|---|
| `permission:<action> <group>.<thing>` | Every staff route. |
| `password.confirm.staff` | Dangerous actions: deleting a lot, downloading secrets, changing money settings. Shows the password popup. |
| `throttle:10,1` | Anything that sends an email or calls an outside service. |
| `quota:<key>` | The route that creates one more of something limited (section 19). The key must be registered first. |
| `superadmin` | Platform pages. Add it after the staff stack, and keep routes under `app/super-admin/...`. |

**Super Admin pages** need `superadmin` in the stack, `<x-superadmin.nav />` at the top of the view, and a tab from the `superadmin_menu` hook.

**Customer-facing pages** (a storefront or portal) are the Frontend module's area: see `Modules/Frontend`. Customers sign in with their own `customer` guard, never `staff`.

**Always:**

- Only use GET for reading. Anything that changes data is POST, PUT or DELETE with `@csrf`.
- Escape output with `{{ }}`. Use `{!! !!}` only on HTML you built yourself, with values passed through `e()`.
- Files that shouldn't be public go on the `local` disk and are served through a route with a permission.

## 8. Permissions

Permission names are `<action> <group>.<thing>`, for example `list tickets.ticket`.

The actions come from `config/access.php`: `list`, `read`, `create`, `update`, `delete`, `manage`, `approve`, `assign`, `upload`, `download`.

Declare yours in `DataController::user_permissions()`. Each thing gets every action:

```php
return [
    'access_modules' => ['tickets' => ['ticket', 'report']],
    'module_labels' => ['tickets' => 'Tickets'],
    'controller_labels' => ['ticket' => 'Tickets', 'report' => 'Ticket reports'],
    'extra_permissions' => ['export tickets'],  // optional: single permissions, any name
];
```

They're created when the module is installed or updated from the Modules page or `core:module`. A new permission on a running site needs one of those, or nothing can be granted.

**Check them** with `permission:` on routes, `AuthHelper::can('list tickets.ticket')` in PHP, and `@can('list tickets.ticket')` in views.

A business's Admin role passes every check. Everyone else needs the permission on their role, so **always test as a member** as well as the owner.

## 9. The menu and the dashboard

**Sidebar:** `modifyAdminMenu()` returns a list of items:

| Key | Meaning |
|---|---|
| `label` | A translation key. |
| `route` | Route name. An item is hidden if the route doesn't exist. |
| `active` | Pattern that highlights the item, e.g. `tickets.*`. |
| `permission` | Hidden without this. Required. |
| `order` | Position inside its section. |
| `icon` | The `d` value of a 24×24 outline SVG path. |
| `group` | Optional: override the section. |

A business can rename, reorder or hide items under Branding → Menu, so don't rely on the label or position. These choices can only hide things, never reveal what a permission blocks.

**Super Admin tab:** `superadmin_menu()` returns `[['label' => __('…'), 'route' => 'superadmin.x.index', 'active' => 'superadmin.x.*']]`.

**Dashboard.** Each module gets its own section. Check permissions in all three hooks.

| Hook | Returns |
|---|---|
| `dashboard_stats()` | `[['label' => '…', 'value' => '…', 'href' => '…']]` |
| `dashboard_quick_actions()` | `[['label' => '…', 'route' => '…', 'permission' => '…', 'icon' => '…']]` |
| `dashboard_widget()` | Rendered HTML, or `null`. |

## 10. Hooks: how modules connect

A hook is a method on your `Modules\<Name>\Http\Controllers\DataController`. When something happens, the core calls that method on every active module, and it does nothing for modules without the method.

- Modules switched off for the current business aren't called.
- If your hook throws, the error is logged and the page carries on, so a broken module can't take the app down. It also means failures are silent; test your hooks.
- Hooks run on page loads. Keep them fast and don't send emails from display hooks.

**Hooks the core offers:**

| Hook | Argument | Return | Purpose |
|---|---|---|---|
| `user_permissions` | | array | Declare permissions (section 8). |
| `modifyAdminMenu` | | list | Sidebar items. |
| `superadmin_menu` | | list | Super Admin tabs. |
| `dashboard_stats` | | list | Dashboard figures. |
| `dashboard_quick_actions` | | list | Dashboard buttons. |
| `dashboard_widget` | | HTML or null | Dashboard panel. |
| `printables` | | `['key' => Class]` | Printed documents (section 13). |
| `after_business_created` | `['business', 'owner', 'context']` | | Set up defaults for a new business. |
| `before_business_deleted` | `['business']` | | Clean up what a cascade won't. |
| `customer_panel` | `$customer` | HTML | Panel on the customer page. |
| `customer_links` | `$customer` | int | How many of your records use this customer. Above 0 blocks deleting them. |
| `customer_portal_url` | `?$customer` | URL | Where a customer's portal is. |
| `superadmin_business_panel` | `$business` | HTML | Panel on the Super Admin business page. |
| `staff_register_form` | `$request` | HTML | Extra part of the registration form. |
| `registration_redirect` | `?$business` | URL | Where to send someone after registering. |
| `landing_page` | | callable or null | Serve the home page. |

**Hooks other modules offer:**

| Hook | Offered by | Argument |
|---|---|---|
| `sales_order_panel` | SalesOrders | `$order` |
| `work_order_panel` | Production | `$workOrder` |
| `invoice_created` | Invoicing | `$invoice` |
| `inventory_reserved` | Inventory | `$variantIds` |

**Offering your own hook,** so other modules can plug into yours:

```php
$results = app(ModuleManager::class)->hook('ticket_closed', $ticket);
```

**Connecting to another module.** Pick by how much you need it:

| You need | Do this |
|---|---|
| The other module always | Add it to `requires`, then use its services and models directly: `Modules\Inventory\Services\StockService`. |
| It only if it's there | Use a hook, or check `app(ModuleManager::class)->isActive('Inventory')` before touching its classes. |
| The core's data | Use it directly: `App\Models\Customer\Customer`, `App\Models\User`, `App\Models\Business\Business`. |

Go through another module's **service**, not its tables. Stock, for example, only ever changes through `StockService::record()`, so the movement log stays right. Never let the core or a module outside your `requires` name your classes.

## 11. The UI

There is one layout and one component kit. Use them and your pages get branding, dark mode, mobile layout and themes with no extra work.

**A page:**

```blade
<x-layouts.app :title="__('tickets::tickets.title')">
    <x-common.page-header :title="…" :description="…">
        <x-slot:actions><x-common.button href="…" size="sm">…</x-common.button></x-slot:actions>
    </x-common.page-header>

    {{-- content --}}
</x-layouts.app>
```

**Colours: use the names, never real colours.** A business can change every colour, and each user can pick dark mode, so `bg-white`, `text-gray-500`, `text-blue-600` or `#1a2b3c` will break on someone's screen.

| Use | For |
|---|---|
| `bg-page` | The page behind everything (the layout does this). |
| `bg-card` | Cards, panels, tables. |
| `bg-surface` | Slightly different areas: table headers, hover. |
| `border-line` | Every border. |
| `text-ink` | Headings and strong text. |
| `text-body` | Normal text. |
| `text-muted` | Hints and secondary text. |
| `text-brand`, `bg-brand`, `text-on-brand` | Main action. Text on a filled colour always uses `text-on-<colour>`. |
| `text-link` | Links in text. |
| `text-success`, `text-warning`, `text-danger`, `text-info` | Status. Also `bg-success/10`, `border-danger/30`. |

Also use `rounded-xl` for cards, `rounded-button` for custom buttons, and `font-display` for headings.

**Components** (`resources/views/components/common/`):

| Component | Use |
|---|---|
| `x-common.page-header` | Title, description, action buttons. |
| `x-common.button` | `variant`: primary, secondary, ghost, danger. `size`: sm, md, lg. `href` makes it a link. |
| `x-common.action-button` | Small table action. `:action` plus `method` makes a form, `:href` a link. `color`: brand, success, warning, danger, muted. |
| `x-common.input` | `name`, `label`, `type`, `value`, `hint`, `required`. Add `amount` for money. Shows its validation error itself. |
| `x-common.textarea`, `x-common.select`, `x-common.checkbox`, `x-common.choice` | Form fields. `select` takes `:options="[value => label]"`. |
| `x-common.file-input` | Upload with preview. |
| `x-common.phone-input` | Phone with country. |
| `x-common.badge` | `color`: brand, success, warning, danger, muted. Pair it with the enum's `color()`. |
| `x-common.table` | Table wrapper with `:headers`. |
| `x-common.pagination` | `:paginator`. |
| `x-common.filter-panel` | Collapsible filters above a list. |
| `x-common.export-buttons` | Print, PDF and Excel. |
| `x-common.empty-state` | Nothing to show yet. |
| `x-common.info-card` | Grey detail card. |
| `x-common.details.tabs` | Tabs (below). |
| `x-common.wizard` | Multi-step forms. |

**Confirming a dangerous action:** add these to a button or `x-common.action-button`:

```blade
data-confirm data-confirm-title="{{ __('…') }}" data-confirm-variant="danger"
```

**Tabs:** the active tab goes in `?tab=`, so a redirect can return to it. See `Modules/Distribution/resources/views/sites/show.blade.php`.

```blade
<div data-details-page>
    <x-common.details.tabs :active="$tab" :tabs="[['id' => 'one', 'label' => __('…')], ['id' => 'two', 'label' => __('…')]]" />
    <div data-details-panel="one">…</div>
    <div data-details-panel="two" class="hidden">…</div>
</div>
```

**Display helpers:** `money($amount)`, `short_date_format()` for date formats, `@datetime($value)`. Don't hard-code a currency or date format; each business has its own.

**Layout rules:**

- Wrap every table in `overflow-x-auto`.
- Phones matter: use `sm:` and `lg:` grids, and check every page at phone width.
- Check every page in light and dark.
- A list with no rows says so.
- One screen, one job. Split long pages into tabs or a separate page.

**Scripts:** there's no build step. Write small plain JavaScript in the view, inside `@once`. Tailwind classes work anywhere because they come from the CDN.

## 12. Translations

**No visible text in code.** Every word a person reads goes through `__()`: pages, buttons, placeholders, `aria-label`s, messages, emails and PDF text.

- Your file is `Modules/<Name>/lang/en/<alias>.php`, used as `__('tickets::tickets.title')`.
- Check `body.*` first (`resources/lang/en/body.php`) for common words: `body.status`, `body.actions`, `body.delete`, `body.cancel`, `body.search`.
- Use `:name` placeholders; never join pieces of a sentence together, because word order differs between languages.
- Enum labels call `__()` in `label()`.
- Example text in a placeholder is translated too.
- Menu labels are translation **keys**. Permission labels are plain English and get translated from a JSON language file.
- Adding a language later means copying `lang/en` to `lang/fr`. Nothing else changes.

## 13. Printing and exports

**Printed documents** (invoices, delivery notes) use the core print engine, so they follow the business's letterhead and print settings.

1. Create `app/Printing/TicketPrintable.php` extending `App\Support\Printing\Printable`, with `label()`, `permission()`, `find($key)` and `document($model)`. `find()` must go through your scoped model.
2. Register it: `public function printables(): array { return ['ticket' => TicketPrintable::class]; }`.
3. Put `<x-print.buttons type="ticket" :id="$ticket->getRouteKey()" />` on the page. For your own link, use `route('print.show', ['ticket', $ticket->uuid])`, and `print.pdf` for the PDF.

`Modules/Delivery/app/Printing/DeliveryNotePrintable.php` is a short example, and `docs/branding-print-build-guide.md` has the details.

**List exports** (PDF and Excel of a table):

- Build an `App\Libraries\ListExport` in your service.
- Return `ListExportLibrary::pdf($export)` and `ListExportLibrary::excel($export)` from two controller routes.
- Add `<x-common.export-buttons :pdf="…" :excel="…" />` to the page.

`Modules/Invoicing/app/Http/Controllers/InvoiceController.php` shows the routes.

## 14. Emails and notifications

**In-app notifications** (the bell): `App\Services\Notification\NotificationService`.

- `notify(…)`: one user.
- `notifyByPermission('update tickets.ticket', $title, $body, …)`: everyone who holds a permission.
- `notifySuperadmins($title, $body, …)`: the platform team.

**Emails:** use `App\Notifications\BrandedNotification` or `App\Support\Mail\BrandedMail`, so they carry the business's logo and colours. See `Modules/Billing/app/Services/BillingNotifier.php`.

Emails are queued and only go out while a worker or the scheduler runs. Write every email in `__()`, with a plain subject.

## 15. Settings for each business

```php
app(BusinessSettings::class)->get('tickets.default_owner', null);
app(BusinessSettings::class)->set('tickets.default_owner', $userId);
```

The class is `App\Support\Settings\BusinessSettings`. Keys start with your alias. Defaults live in code as the second argument; only changes are stored.

**Module-wide config** that isn't per business goes in `config/config.php`, read with `config('tickets.prefix')`. Read secrets from `env()` there, never in other code.

## 16. Commands and scheduled tasks

Register them in your service provider:

```php
protected array $commands = [
    \Modules\Tickets\Console\RemindCommand::class,
];

protected function configureSchedules(Schedule $schedule): void
{
    $schedule->command('tickets:remind')->dailyAt('06:00')->withoutOverlapping();
}
```

**The most important rule here: a command has no signed-in user, so no current business, so `BelongsToBusiness` filters nothing.** A query in a command sees **every** business's rows. Always loop:

```php
public function handle(ModuleManager $modules): int
{
    if (! $modules->isActive('Tickets')) {
        return self::SUCCESS;
    }

    foreach (Business::query()->where('is_active', true)->get() as $business) {
        if (! $business->canUseModule('Tickets')) {
            continue;
        }

        app()->forgetScopedInstances();
        app(CurrentBusiness::class)->set($business);

        // Work for this business only.
    }

    return self::SUCCESS;
}
```

The same goes for queued jobs and webhooks: set the business before touching scoped data, or set `business_id` yourself.

Scheduled tasks need the server's cron, `* * * * * php artisan schedule:run`. Long or repeating work must be safe to run twice.

## 17. Outside connections

**Calling an outside service (a bank, an SMS provider):**

- Put the calls in one client class, e.g. `app/Services/AcmeClient.php`, and nowhere else.
- Read the address and keys from `config/config.php`, which reads `env()`. Never hard-code them, never commit them, and never put your own server's address in a module.
- Set timeouts: `Http::timeout(15)`.
- With no credentials, run in a clear **test mode** that says so on the page. Never call a real service from a test. Look at how InjongePay does this.
- List every `.env` key the module needs in its own setup guide. The installer doesn't ask for them.

**Receiving webhooks:**

- Put the route in `routes/api.php`, added in your `RouteServiceProvider` with `['api', 'module:<Name>']`. There's no session or user.
- Check the signature before anything else, using `hash_equals`.
- Find the business from the signed data, then set it (section 16).
- Save first, process after: the same webhook may come twice, so make processing safe to repeat.

**Offering an API to other systems:**

- Use `routes/api.php` under a versioned prefix: `api/tickets/v1`.
- Give each client its own credentials, and store only hashes.
- Limit every route with `throttle`.
- Check that each record belongs to the caller's business.

`Modules/InjongePayConnect` is the full example.

**Packages:** a module can list Composer packages in its own `composer.json`. They're merged into the core's. Run `composer update` in the repo and commit `composer.lock`. A copy that receives only the module folder must run `composer update` too, so use few packages. A module with none can be dropped in anywhere.

## 18. Audit

**Every feature leaves a trail.** `BaseModel` logs create, update and delete by itself. Any other meaningful event gets a line of its own:

```php
audit('tickets_closed', __('tickets::tickets.audit_closed', ['number' => $ticket->number]), $ticket);
```

The arguments are the action, a description, the subject model, a `details` array, and the business id if it isn't the current one.

- Start the action with your alias.
- Give it a readable name in `lang/en/audit.php`: `'tickets_closed' => 'Ticket closed'`.
- Log money movements, status changes, security changes, exports of personal data and anything done on someone's behalf.
- Never put passwords, tokens or full card or account secrets in `details`.

## 19. Billing and limits

Your module doesn't need to know whether Billing is installed. The core has one contract, `App\Contracts\Entitlements`. With no Billing module, everything is allowed.

- **Whole module:** handled by `module:<Name>` on routes. `$business->canUseModule('Tickets')` in commands.
- **Limits:** register how to count them in your provider's `boot()`, with `app(Quota::class)->register('tickets.open', fn (Business $b) => …)`. Then `quota:tickets.open` on the create route shows "limit reached" for you. `app(Quota::class)->limit($business, 'tickets.open')` reads a limit; `null` means none. Without Billing there's no limit.
- **A paid feature inside a module:** `app(Entitlements::class)->allowsCapability($business, 'tickets.reports')`.
- **Free or sold:** Super Admin → Modules, or `billing_exempt` in module.json to make it always free.

Never reference `Modules\Billing` from your module. Billing and Distribution never ship to customers, so a copy without them would crash.

## 20. Tests

Tests live in `Modules/<Name>/tests/Feature/` and extend `Tests\TestCase`. Install your module in `setUp()`, and any it requires first.

```php
app(ModuleManager::class)->install('Tickets');
```

Helpers from `Tests\TestCase`:

| Helper | Gives you |
|---|---|
| `makeBusiness()` | `[$business, $owner]` |
| `makeStaff()` | A business owner. |
| `makeSuperAdmin()` | A platform superadmin. |
| `makeMember($business, ['list tickets.ticket'])` | A non-admin with exactly those permissions. |
| `actingAsStaff($user)` | Signs in; creates an owner if you pass none. |
| `withConfirmedPassword()` | Passes `password.confirm.staff`. |

**Every module tests at least these:**

- The main path works, from the route down.
- **Another business gets 404 or sees nothing.** This is the most important test you'll write.
- A member without the permission gets 403.
- A wrong action fails with a message (closing twice, for example).
- Pages render: `assertOk()` on each one.
- Outside calls are faked with `Http::fake()`.

When you ask for a second person in one test, call `$this->flushSession()` first. Run the whole suite before shipping, not just your module.

## 21. Shipping

**A zip is built from the last commit.** Put the module in `Modules/`, commit it, then build.

### Add a module to a copy that's already running

This works on any copy, standalone or not:

1. Copy the folder to `Modules/<Name>/` on that server, with anything in its `requires`.
2. Run `php artisan core:module install <Name>`, or use Super Admin → Modules → Install.
3. Set any `.env` keys it needs.

### Update a module on a running copy

1. Raise `version` in module.json.
2. Replace the folder.
3. Run `php artisan core:module update <Name>`.

Take a database backup first: Super Admin → Backups.

### Choose a zip

All of these come from the site page → Install tab, or `php artisan distribution:build "<site>"`. Assign modules to the site first; anything in `requires` is added for you.

| Zip | Link to you | Updates | Needs | Use it for |
|---|---|---|---|---|
| **Normal** | Yes | Yes | Paid licence (or a test site), signing key | A customer you keep supporting. |
| **Developer edition** | Yes | Yes | Paid licence (or a test site), signing key | A customer who also changes the code. |
| **Standalone** | None | None | Nothing | Handing a copy over for good. |
| **Standalone + Developer** | None | None | Nothing | Handing over the code, or building a module outside. |

Terminal twin: add `--standalone` or `--developer`.

**A Standalone copy** has no token, no address of your server, no signing keys, no update check and no Updates page, and it can't be linked later. You won't see whether it's installed, and fixes can only reach it by hand. It still loads Tailwind from the public CDN and calls any outside service its modules use.

**Normal and Developer zips also get updates.** To release a new version:

1. Raise `version` in module.json and commit.
2. Publish it: Distribution → Releases, or `php artisan distribution:publish <Name> --changelog="…"`.
3. Each site receives it on its next check, automatically or when its admin clicks Update, depending on the site's setting.

A customised copy of a module should be held on the customer's Updates page, because an update replaces the whole module folder. Better still, put their changes in a separate module.

### Never in a zip

- `Billing` and `Distribution` (`never_ship` in `config/distribution.php`).
- Anything uncommitted: `.env`, keys, local files.
- Guides for modules the zip doesn't contain. If you add a guide for one module, list it in `MODULE_DOCS` in `BuildService`.

## 22. Checklist before you ship

**Structure**

- [ ] Provider extends `CoreModuleServiceProvider`.
- [ ] `requires` lists every module you use directly.
- [ ] `version` raised if there's a new migration.
- [ ] No reference to Billing, Distribution, or any module outside `requires`.

**Data**

- [ ] Every table has `business_id`; every model uses `BelongsToBusiness`.
- [ ] Uniqueness is per business.
- [ ] Commands and jobs loop over businesses.
- [ ] Migrations run on MySQL and PostgreSQL, and `down()` works.

**Security**

- [ ] Every route has a `permission:` check.
- [ ] Dangerous actions use `password.confirm.staff`.
- [ ] Webhooks check a signature; no secrets in code.

**UI**

- [ ] Only colour names, no real colours.
- [ ] Checked in light, dark and at phone width.
- [ ] Every word goes through `__()`.
- [ ] Empty lists say so.

**Finish**

- [ ] Every action has a button.
- [ ] Meaningful events reach the audit log.
- [ ] Tests cover other businesses and missing permissions.
- [ ] The full test suite passes.
- [ ] Install, use, turn off, turn on and uninstall all work on a fresh copy.

## 23. Common mistakes

| What you see | Why | Fix |
|---|---|---|
| "Already up to date", but a column is missing | The migration has no version bump. | Raise `version`, run `core:module update`. |
| Table missing after `php artisan migrate` | Module migrations only run on install. | `core:module install`. |
| Tables created on plain `migrate` | The provider extends nwidart's class. | Extend `CoreModuleServiceProvider`. |
| A command touches every business's rows | No current business in a command. | Loop and set `CurrentBusiness` (section 16). |
| Page is unreadable in dark mode | Real colours such as `bg-white`. | Use `bg-card`, `text-ink`, `border-line`. |
| Permission isn't on the Roles page | Permissions sync on install or update. | Run `core:module update`, or reinstall in a test copy. |
| Menu item doesn't show | Permission missing, route name wrong, or the module is off for this business. | Open the module's page on Super Admin → Modules: it says if it's off for your business. Then check `permission` and `route`. |
| A hook does nothing, with no error | Errors in hooks are logged, not shown. | Check `storage/logs`. Confirm the class is named `DataController`. |
| Works for the owner, 403 for staff | The Admin role passes everything. | Test with `makeMember()`. |
| Another business's id gives a 500 on PostgreSQL | Routing by id instead of uuid. | Extend `BaseModel`. |
| Class not found after moving a module | The folder and namespace differ. | They must match exactly, including case. Linux servers are case-sensitive. |
| Module page works locally, crashes on a copy | It uses a module the copy doesn't have. | Add it to `requires`, or check `isActive()`. |
