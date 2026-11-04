# Distribution

Self-hosting: customer servers (sites), which modules each gets, signed module releases, zip builds and the update API customer servers check.

**Requires:** no other module.

For the platform owner's server only. It is never shipped to customers.

## Install on Ubuntu

The commands use `/var/www/ish-core` as the app folder. Use your own path if it differs.

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module install Distribution
```

You can also install it from **Super Admin → Modules**.

To update it after new code is on the server:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module update Distribution
```

New migrations run only if the `version` in `module.json` went up.

Build needs on Ubuntu:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo mkdir -p /var/www/.composer && sudo chown www-data:www-data /var/www/.composer
```

## `.env`

| Key | Required | What it is |
|---|---|---|
| `DISTRIBUTION_SIGNING_SECRET` | Yes | Ed25519 secret key that signs releases and update answers. Create it under Self-hosting → Releases → **Create signing key**, or with `php artisan distribution:keygen`. Back it up and keep it only in this `.env`. |
| `DISTRIBUTION_PHP` | No | Full path to PHP for builds, for example `/usr/bin/php8.3`. Set it if **Build zip** can't find PHP. |
| `DISTRIBUTION_COMPOSER` | No | Full path to Composer, for example `/usr/local/bin/composer`. Defaults to `composer`. |

After changing `.env`:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan queue:restart
```

## Scheduler and queue

| When | Command | Does |
|---|---|---|
| Daily 03:30 | `distribution:prune` | Removes old build files |
| Daily 08:00 | `distribution:support-reminders` | Support renewal reminders |

Add the scheduler once for the whole app. Skip this if the server already has it.

```bash
echo '* * * * * www-data cd /var/www/ish-core && php artisan schedule:run >> /dev/null 2>&1' | sudo tee /etc/cron.d/ish-core
sudo chmod 644 /etc/cron.d/ish-core
```

Emails are queued, so nothing is sent without a worker.

Keep one queue worker running under Supervisor. Skip this if the server already has it.

```bash
sudo apt install -y supervisor
sudo tee /etc/supervisor/conf.d/ish-core-queue.conf > /dev/null <<'EOF'
[program:ish-core-queue]
command=php /var/www/ish-core/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
stopwaitsecs=120
stdout_logfile=/var/www/ish-core/storage/logs/queue.log
redirect_stderr=true
EOF
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status ish-core-queue
```

## Set it up

1. Install Composer on the server, and make sure `www-data` can run it.
2. Open **Super Admin → Self-hosting → Releases** and create the signing key.
3. Add a site, pick its modules and build its zip.
4. To release a module, raise its `version` in `module.json`, commit, then press **Publish** under Releases.

## Check it works

Build a zip for a test site. Only committed code goes into a build.
