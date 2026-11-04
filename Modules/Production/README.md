# Production

Work orders that move through the business's own stages. Counts only go forward, a later stage never exceeds an earlier one, and finished pieces go into stock through the Inventory movement log. Materials can be issued to a work order, with daily targets per production line.

**Requires:** Inventory. Install these first.

## Install on Ubuntu

The commands use `/var/www/ish-core` as the app folder. Use your own path if it differs.

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module install Inventory
sudo -u www-data php artisan core:module install Production
```

You can also install it from **Super Admin → Modules**.

To update it after new code is on the server:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module update Production
```

New migrations run only if the `version` in `module.json` went up.

## `.env`

Nothing to add.

## Set it up

1. Turn the module on for the business.
2. Set the stages under **Production → Settings**. Defaults are Cutting, Sewing, Finishing and Packing, and the last stage adds stock.
3. Create work orders.

## Check it works

Move a small work order through to the last stage and confirm stock rises.
