# Purchasing

Suppliers, purchase orders and goods received. Receiving, in parts or in full, puts stock in at cost through the Inventory movement log. Also covers supplier balances and payments, and a reorder list from reorder levels.

**Requires:** Inventory. Install these first.

## Install on Ubuntu

The commands use `/var/www/ish-core` as the app folder. Use your own path if it differs.

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module install Inventory
sudo -u www-data php artisan core:module install Purchasing
```

You can also install it from **Super Admin → Modules**.

To update it after new code is on the server:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module update Purchasing
```

New migrations run only if the `version` in `module.json` went up.

## `.env`

Nothing to add.

## Set it up

1. Turn the module on for the business.
2. Add suppliers.
3. Set reorder levels on items in Inventory if you want the reorder list.
4. Create purchase orders. Numbering defaults are `PO`, `GRN` for goods received and `SP` for supplier payments.

## Check it works

Receive a small order and confirm the stock movement at cost.
