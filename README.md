# ISH Core

Laravel 13 base for multi-business apps. Everything app-specific is a module in `Modules/`.

## Install

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan core:install     # first business + superadmin
php artisan serve            # http://localhost:8000
```

## Modules

```bash
php artisan core:module list
php artisan core:module install|update|enable|disable|uninstall <Name> [--purge]
php artisan module:make <Name>     # new module; see Modules/Frontend as the example
```

- **Frontend**: a public website per business at `/s/<slug>`, edited from **Website** in the sidebar.
- **Billing**: packages decide which modules and limits each business gets; payments are offline and you approve them.

## Billing: required setup

Renewals and suspensions are done by a daily job. On the server, add the scheduler to cron:

```
* * * * * php artisan schedule:run
```

Locally, run `php artisan billing:run` by hand.

Notifications are in-app only for now (no emails). A business with no subscription can still use the core screens, with no user limit.

## Tests

```bash
php artisan test
```
