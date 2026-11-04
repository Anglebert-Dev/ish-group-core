# Invoicing

Sales invoices and customer payments. An invoice takes goods out of stock unless they already left with a delivered sales order. Payments are capped at the amount due, cancelling issues a credit note, and it covers tax rate, PDF invoices and customer balances.

**Requires:** Inventory. Install these first.

## Install on Ubuntu

The commands use `/var/www/ish-core` as the app folder. Use your own path if it differs.

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module install Inventory
sudo -u www-data php artisan core:module install Invoicing
```

You can also install it from **Super Admin → Modules**.

To update it after new code is on the server:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module update Invoicing
```

New migrations run only if the `version` in `module.json` went up.

## `.env`

Nothing to add.

## Set it up

1. Turn the module on for the business.
2. Set the tax rate and numbering under **Invoicing → Settings**. Defaults are `INV`, `CN` for credit notes and `RC` for receipts.
3. Create invoices from the **Sales** menu or from a sales order.

## Check it works

Create an invoice, record a payment and download the PDF.
