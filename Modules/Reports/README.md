# Reports

Reports for a date range, read from the history: sales by product and customer, production output and rejects, stock value at cost, stock movements and purchases. Each report appears only when its module is in use, with PDF and Excel export.

**Requires:** Inventory. Install these first.

## Install on Ubuntu

The commands use `/var/www/ish-core` as the app folder. Use your own path if it differs.

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module install Inventory
sudo -u www-data php artisan core:module install Reports
```

You can also install it from **Super Admin → Modules**.

To update it after new code is on the server:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module update Reports
```

New migrations run only if the `version` in `module.json` went up.

## `.env`

Nothing to add.

## Set it up

1. Turn the module on for the business.
2. Give staff access under **Roles**.
3. Open the **Reports** menu.

## Check it works

Export one report to PDF and to Excel. Big exports need `memory_limit` of at least 256M.
