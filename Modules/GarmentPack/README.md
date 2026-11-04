# GarmentPack

For garment makers: a style library with a bill of materials per style, costing per piece and margin per size, and work orders that issue exactly the materials a style needs.

**Requires:** Inventory, Production. Install these first.

## Install on Ubuntu

The commands use `/var/www/ish-core` as the app folder. Use your own path if it differs.

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module install Inventory
sudo -u www-data php artisan core:module install Production
sudo -u www-data php artisan core:module install GarmentPack
```

You can also install it from **Super Admin → Modules**.

To update it after new code is on the server:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module update GarmentPack
```

New migrations run only if the `version` in `module.json` went up.

## `.env`

Nothing to add.

## Set it up

1. Turn the module on for the business, with Production and Inventory.
2. Set up materials in Inventory first.
3. Create styles and their bills of materials in the **Garments** menu.
4. Measurement fields default to Chest, Waist, Hips, Shoulder, Sleeve, Length, Neck and Inseam. A business can change them.

## Check it works

Create a style, then a work order from it, and confirm the materials it lists.
