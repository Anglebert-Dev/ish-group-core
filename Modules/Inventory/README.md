# Inventory

Finished goods and materials with sizes and colours, several stores, and a movement log behind every stock change: receive, adjust and transfer, with full history. Other modules move stock only through it.

**Requires:** no other module.

## Install on Ubuntu

The commands use `/var/www/ish-core` as the app folder. Use your own path if it differs.

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module install Inventory
```

You can also install it from **Super Admin → Modules**.

To update it after new code is on the server:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module update Inventory
```

New migrations run only if the `version` in `module.json` went up.

## `.env`

Nothing to add.

## Set it up

1. Turn the module on for the business. New businesses get a default store called **Main store**.
2. Set units under **Inventory → Settings**.
3. Add stores, then items, then receive opening stock.

## Check it works

Receive one item and confirm it appears in the movement log.
