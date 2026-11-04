# Frontend

Public website for every business at `/s/{slug}`, with an editor for its content and website logins for the business's customers. When the primary business publishes its site, `/` shows it. Otherwise `/` goes to the staff login.

**Requires:** no other module.

## Install on Ubuntu

The commands use `/var/www/ish-core` as the app folder. Use your own path if it differs.

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module install Frontend
```

You can also install it from **Super Admin → Modules**.

To update it after new code is on the server:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module update Frontend
```

New migrations run only if the `version` in `module.json` went up.

## `.env`

Nothing to add.

## Set it up

1. Link public storage once, so uploaded images show: `sudo -u www-data php artisan storage:link`.
2. Turn the module on for the business.
3. Open the website editor in the **Website** menu, fill in the content and publish.

## Check it works

Open `/s/{slug}` in a private browser window.
