# Delivery

Delivery notes for sales orders: deliver in parts, record who received the goods (name, phone, signature on screen) and print the note. Each note takes its quantities out of stock through the Inventory movement log.

**Requires:** no other module.

## Install on Ubuntu

The commands use `/var/www/ish-core` as the app folder. Use your own path if it differs.

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module install Delivery
```

You can also install it from **Super Admin → Modules**.

To update it after new code is on the server:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module update Delivery
```

New migrations run only if the `version` in `module.json` went up.

## `.env`

Nothing to add.

## Set it up

1. Turn the module on for the business.
2. Give staff access under **Roles**.
3. Open a confirmed sales order and use its delivery panel, or open the delivery notes list in the Sales menu.

## Check it works

Create a delivery note for a small quantity, then confirm the stock movement appears in Inventory.
