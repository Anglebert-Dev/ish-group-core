# ISH Core

Laravel 13 base for multi-business apps. Everything app-specific is a module in `Modules/`.

## Install

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan core:install     # first business + superadmin
php artisan storage:link     # makes uploaded logos and files visible
```

## Run locally

Keep these running in separate terminals:

```bash
php artisan serve            # the app: http://localhost:8000
php artisan queue:work       # sends emails — without it, no email goes out
php artisan schedule:work    # daily jobs (billing renewals, suspensions)
```

## Core

- **Businesses**: each business sees only its own data. The superadmin manages them all (Super Admin), can log in as a user, and can delete a business.
- **Users & roles**: per-business roles built from module permissions.
- **Customers** (Management → Customers): every business has them; a customer with linked orders or invoices can't be deleted (make them inactive).
- **Audit log**, **notifications**, **backups**, **Communicator** (announcements to business admins).

## Modules

```bash
php artisan core:module list
php artisan core:module install|update|enable|disable|uninstall <Name> [--purge]
php artisan module:make <Name>     # new module; see Modules/Frontend as the example
```

| Module | What it does | Needs |
|---|---|---|
| **Frontend** | A website per business at `/s/<slug>`; customer login and sign-up are switched on per business (Website → General). | — |
| **Billing** | Packages decide which modules and limits each business gets. Payments are offline: the business sends proof, you approve. Public plans at `/pricing`. | — |
| **Inventory** | Products and materials with sizes/colours, several stores, stock history. Every stock change is logged; stock never goes below zero. | — |
| **SalesOrders** | Customer orders: pending → confirmed (stock reserved) → in production → ready → delivered. | Inventory |
| **Production** | Work orders through each business's own stages, quality rejects, daily targets per line. | Inventory |
| **Invoicing** | Invoices with tax and PDF, payments (never above the balance), credit notes, customer balances. | Inventory |
| **Quotations** | Quotes and samples, deposits, "Convert to order". | SalesOrders |
| **Delivery** | Delivery notes, part deliveries, receiver signature. | SalesOrders |
| **Purchasing** | Suppliers, purchase orders, goods received at cost, supplier balances, reorder list. | Inventory |
| **GarmentPack** | Styles with bill of materials and costing, piece-rate pay, subcontracting (CMT), bundles, measurements. | Production, Inventory |
| **Reports** | Sales, production, stock value, movements and purchases for any period; PDF/Excel. | Inventory |
| **Savings** | Personal finance, **private to each user** (colleagues and the business owner never see it): accounts, a balanced ledger, budgets on any period (e.g. 28th→27th), expected income and recurring bills, money owed and to put back, commitments, goals, available to spend, cash-flow forecast with shortfall warnings, reconciliation, reports, alerts, CSV import, backup/restore. Daily `savings:alerts` (scheduler). | — |

On/off is stored in the database (not in a file), so switching modules on a server or on your machine never changes the code.

**One switch:** a module can't run without the modules it needs. Turning **Inventory** off turns off everything built on it (data kept); turning a module on turns on what it needs. Leaving a module out of a package hides it, and everything that needs it, for that business.

## Building a module

- `module.json`: `"requires": ["Inventory"]` for dependencies.
- `Http/Controllers/DataController.php` answers the core's hooks: `user_permissions`, `modifyAdminMenu`, `dashboard_stats`, `dashboard_quick_actions`, `customer_links`, `customer_panel`, `after_business_created`, …
- Shared services:
  - `app(StockService::class)->record(...)` — the only way to change stock.
  - `app(DocumentNumber::class)->next('sales_order', 'SO')` — `SO-2026-00001`, per business, never reused.
  - `app(BusinessSettings::class)->get/set('module.key')` — per-business settings.
  - `money($amount)` — amount in the business's currency.
- Tenant tables get a `business_id`; models use `BelongsToBusiness` and extend `BaseModel`.
- Migrations run when the module is installed, not with `php artisan migrate`.

## Demo data (test server)

```bash
php artisan core:install                              # platform + superadmin first
php artisan db:seed --class=DemoFactorySeeder --force
```

Creates **Kigali Sun Garments**, a garment factory on the "Garment Factory" package (paid and approved), with five weeks of history: stock, purchases, quotes, orders, production with rejects and piece work, deliveries, invoices and payments. Runs once.

| Login | Role |
|---|---|
| owner@kigalisun.test | Owner (everything) |
| sales@kigalisun.test | Sales |
| floor@kigalisun.test | Production supervisor |
| store@kigalisun.test | Store keeper |

Password for all: `Demo-Factory-2026!`

## Savings (personal finance)

Install and switch on (once per platform):

```bash
php artisan core:module install Savings     # creates its tables
php artisan core:module update Savings      # after pulling a new version
```

Then add **Savings** to a package (Super Admin → Billing → Packages); your own superadmin business has it straight away.

**Commands**

| Command | What it does | When |
|---|---|---|
| `php artisan savings:alerts` | Creates the coming dates of recurring items and sends each user their alerts (bills due, late income, overdue debts, budget 80/100%, missed goal contributions, coming shortfalls). Each alert is sent once. | Daily at 06:00 via the scheduler (`schedule:run` in cron, or `schedule:work` locally). Run by hand to test. |
| `php artisan queue:work` | Sends the alert emails. | Always running (see Email). |

**First steps for a user** (sidebar → Savings)
1. **Categories → Add starter categories** (or create your own).
2. **Accounts**: add each bank, cash, mobile money or savings account with its balance on the opening date.
3. **Finance settings**: currency, budget period (e.g. monthly from the 28th), alerts.
4. Record transactions, or **Import & backup → Import a statement** (CSV: `date, description, amount` with minus for money out, or `debit`/`credit` columns; optional `reference`).
5. **Planned & recurring**: salary (in parts if needed), bills, rent. **Budgets**: set limits for the period.

**Good to know**
- Finances are private: each user sees only their own; roles decide who may use which parts.
- Nothing is edited after it is recorded: use **Reverse** (and correct) on a transaction.
- **Import & backup → Download backup** gives one JSON file of the user's finances; **Restore** merges (nothing duplicated) or replaces.

## Audit log

Every action must be traceable. Each business sees only its own entries (**Audit log**); the superadmin sees all of them, filterable by business (**Super Admin → Audit log**).

- Records extending `App\Models\BaseModel` log create/update/delete automatically.
- For anything else (completing a sale, approving, exporting, sending…), call:
  `audit('sale_completed', "Sale #{$sale->number} completed", $sale, ['total' => $sale->total]);`
- Optional label: `Modules/<Name>/lang/en/audit.php` → `'sale_completed' => 'Sale completed'`.

## Email

Set the SMTP details in `.env`. `MAIL_FROM_ADDRESS` must be the SMTP account itself; each business's emails use that business's name as the sender and its contact email for replies.

Emails are queued: **without a running `php artisan queue:work`, nothing is sent**. On the server, keep it running under Supervisor or systemd.

## Server setup

Renewals and suspensions are done by a daily job. Add the scheduler to cron:

```
* * * * * php artisan schedule:run
```

Locally, `php artisan schedule:work` does the same, or run `php artisan billing:run` by hand.

Billing events are sent in-app and by email. A business with no subscription can still use the core screens; set **Billing → Settings → Users without a package** to limit them.

After deploying new code: `php artisan migrate --force`, then `php artisan core:module update <Name>` for each updated module, then `php artisan queue:restart`.

## Tests

```bash
php artisan test
```
