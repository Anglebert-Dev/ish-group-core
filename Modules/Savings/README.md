# Savings

Personal finance: accounts, a balanced ledger, budgets, expected income and recurring bills, debts, commitments, savings goals, available-to-spend, a cash-flow forecast with shortfall warnings, reconciliation, reports, alerts, import, backup and restore.

**Requires:** no other module.

## Install on Ubuntu

The commands use `/var/www/ish-core` as the app folder. Use your own path if it differs.

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module install Savings
```

You can also install it from **Super Admin → Modules**.

To update it after new code is on the server:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module update Savings
```

New migrations run only if the `version` in `module.json` went up.

## `.env`

Nothing to add.

## Scheduler and queue

| When | Command | Does |
|---|---|---|
| Daily 06:00 | `savings:alerts` | Creates coming recurring items and sends alerts |

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

1. Turn the module on for the business.
2. Set the currency and budget period under **Savings → Settings**. The default is RWF, monthly from the 1st.
3. Add accounts, then income and recurring bills.

## Check it works

Run `sudo -u www-data php artisan savings:alerts` once and confirm it finishes without errors.
