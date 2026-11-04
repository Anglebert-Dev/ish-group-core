# Billing

Packages and subscriptions: which modules and limits each business gets, invoices, offline payments approved by a superadmin, coupons, trials, proration and grace periods.

**Requires:** no other module.

Billing belongs to the platform owner's server. It is never included in customer zips.

## Install on Ubuntu

The commands use `/var/www/ish-core` as the app folder. Use your own path if it differs.

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module install Billing
```

You can also install it from **Super Admin → Modules**.

To update it after new code is on the server:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module update Billing
```

New migrations run only if the `version` in `module.json` went up.

## `.env`

Nothing to add.

## Scheduler and queue

| When | Command | Does |
|---|---|---|
| Daily 01:00 | `billing:run` | Renewals and suspensions |

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

1. Open **Super Admin → Billing**.
2. Create the packages: the modules and limits each one gives.
3. Assign a package to each business.
4. Once Billing is installed, the package decides a business's modules. The on/off switches on the business's page no longer apply.

## Check it works

Run `sudo -u www-data php artisan billing:run` once and confirm it finishes without errors.
