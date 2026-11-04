# Quotations

Quotes and samples before an order: follow-up dates, deposits as a list, samples sent from stock and returned to it, and **Convert to order**, which copies the customer, products and prices. Deposits are applied to the order's invoice automatically.

**Requires:** Inventory, SalesOrders. Install these first.

## Install on Ubuntu

The commands use `/var/www/ish-core` as the app folder. Use your own path if it differs.

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module install Inventory
sudo -u www-data php artisan core:module install SalesOrders
sudo -u www-data php artisan core:module install Quotations
```

You can also install it from **Super Admin → Modules**.

To update it after new code is on the server:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module update Quotations
```

New migrations run only if the `version` in `module.json` went up.

## `.env`

Nothing to add.

## Set it up

1. Turn the module on for the business.
2. Create quotes from the **Sales** menu. Quotes are numbered `QT` and valid for 30 days by default.

## Check it works

Create a quote and convert it to an order.
