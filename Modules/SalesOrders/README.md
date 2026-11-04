# SalesOrders

Customer orders with numbered documents, due dates and product lines. Lines can change only while the order is pending, confirmed orders reserve stock, and delivery takes it out through the Inventory movement log.

**Requires:** Inventory. Install these first.

## Install on Ubuntu

The commands use `/var/www/ish-core` as the app folder. Use your own path if it differs.

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module install Inventory
sudo -u www-data php artisan core:module install SalesOrders
```

You can also install it from **Super Admin → Modules**.

To update it after new code is on the server:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module update SalesOrders
```

New migrations run only if the `version` in `module.json` went up.

## `.env`

Nothing to add.

## Set it up

1. Turn the module on for the business.
2. Create orders from the **Sales** menu. Numbers start with `SO` by default, and each business can change that.

## Check it works

Create and confirm an order, then confirm the stock shows as reserved.
