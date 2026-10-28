# ISH Core

Laravel 13 base for multi-business apps. Everything app-specific is a module in `Modules/`.

## Install

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan core:install     # first business + superadmin
```

## Run locally

Keep these running in separate terminals:

```bash
php artisan serve            # the app: http://localhost:8000
php artisan queue:work       # sends emails — without it, no email goes out
php artisan schedule:work    # daily jobs (billing renewals, suspensions)
```

## Modules

```bash
php artisan core:module list
php artisan core:module install|update|enable|disable|uninstall <Name> [--purge]
php artisan module:make <Name>     # new module; see Modules/Frontend as the example
```

- **Frontend**: a public website per business at `/s/<slug>`, edited from **Website** in the sidebar.
- **Billing**: packages decide which modules and limits each business gets; payments are offline and you approve them. Public plans are shown at `/pricing`, and signing up from there starts that package.
- **Communicator** (Super Admin): send announcements to business admins, in-app and/or by email.
- **Backups** (Super Admin): database + uploads zipped to `storage/app/private/backups`; optional daily run. Copy them off the server too.

## Demo data (test server)

```bash
php artisan core:install                              # platform + superadmin first
php artisan db:seed --class=DemoFactorySeeder --force
```

Creates **Kigali Sun Garments**, a garment factory on the "Garment Factory" package (paid and approved), with five weeks of history: stock, purchases, quotes, orders, production with rejects and piece work, deliveries, invoices and payments. Runs once.
Logins (password `Demo-Factory-2026!`): `owner@`, `sales@`, `floor@`, `store@kigalisun.test`.

- **Inventory** is the switch for the whole stock system: turning it off turns off Sales Orders, Production, Invoicing, Quotations, Delivery, Purchasing, Garments and Reports (data kept).

## Audit log

Every action must be traceable. Each business sees only its own entries (**Audit log**); the superadmin sees all of them, filterable by business (**Super Admin → Audit log**).

- Records extending `App\Models\BaseModel` log create/update/delete automatically. Give tenant tables a `business_id`.
- For anything else (completing a sale, approving, exporting, sending…), call:
  `audit('sale_completed', "Sale #{$sale->number} completed", $sale, ['total' => $sale->total]);`
- Optional label: `Modules/<Name>/lang/en/audit.php` → `'sale_completed' => 'Sale completed'`.

## Email

Set the SMTP details in `.env`. `MAIL_FROM_ADDRESS` must be the SMTP account itself; each business's emails use that business's name as the sender and its contact email for replies.

Emails are queued: **without a running `php artisan queue:work`, nothing is sent**. On the server, keep it running under Supervisor or systemd.

## Billing: required setup

Renewals and suspensions are done by a daily job. On the server, add the scheduler to cron:

```
* * * * * php artisan schedule:run
```

Locally, `php artisan schedule:work` does the same, or run `php artisan billing:run` by hand.

Billing events are sent in-app and by email. A business with no subscription can still use the core screens; set **Billing → Settings → Users without a package** to limit them.

## Tests

```bash
php artisan test
```
