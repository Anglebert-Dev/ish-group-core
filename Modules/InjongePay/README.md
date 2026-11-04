# InjongePay

Mobile-money collection through GTBank PaymentBridge: merchant codes (wallets) per business, payments split into the business's share and the platform fee, a ledger that matches the bank, transfers, cash-outs to verified bank accounts, and daily reconciliation against GTBank.

**Requires:** no other module.

Businesses do everything except merchant codes. Inhills creates merchant codes and sets KYC, fees and limits. It also handles reconciliation, unclear cash-outs and money to allocate.

## Server packages

Needs PHP 8.3 with `bcmath`, because all money maths uses it. On Ubuntu 24.04:

```bash
sudo apt update
sudo apt install -y php8.3-fpm php8.3-cli php8.3-pgsql php8.3-bcmath php8.3-curl php8.3-gd php8.3-intl php8.3-mbstring php8.3-xml php8.3-zip
php -m | grep bcmath
```

## Install on Ubuntu

The commands use `/var/www/ish-core` as the app folder. Use your own path if it differs.

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module install InjongePay
```

You can also install it from **Super Admin → Modules**.

To update it after new code is on the server:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module update InjongePay
```

New migrations run only if the `version` in `module.json` went up.

## `.env`

| Key | Required | What it is |
|---|---|---|
| `GTBANK_BASE_URL` | Yes, for live | GTBank PaymentBridge address. Empty means test mode: nothing goes to GTBank, and every page says so. |
| `GTBANK_CLIENT_ID` | Yes, for live | Client ID from GTBank |
| `GTBANK_CLIENT_SECRET` | Yes, for live | Client secret from GTBank |
| `GTBANK_PARTICIPANT_NAME` | Yes, for live | Participant name at GTBank |
| `GTBANK_SSL_VERIFY` | No | Defaults to `true`. Leave it on in production. |
| `GTBANK_WEBHOOK_SECRET` | No | Optional. It is read directly from the environment, so it has no effect once `php artisan config:cache` is used. Generate the secret in **Injonge Pay settings** in that case. |

After changing `.env`:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan queue:restart
```

## GTBank's certificate

If GTBank gave you a CA file, put it here:

```bash
sudo mkdir -p /var/www/ish-core/storage/key
sudo cp gtbank-ca.pem /var/www/ish-core/storage/key/gtbank-ca.pem
sudo chown www-data:www-data /var/www/ish-core/storage/key/gtbank-ca.pem
sudo chmod 640 /var/www/ish-core/storage/key/gtbank-ca.pem
```

## Scheduler and queue

Both are required. Without them merchant codes don't register, cash-outs don't go out, and payments don't get confirmed.

The times below are the defaults. Change them, or switch a job on or off, on the **Background jobs** page. No server change is needed: a change applies from the next minute.

| When | Command | Does |
|---|---|---|
| Every minute | `injongepay:process-inbox` | Processes stored GTBank webhooks |
| Every minute | `injongepay:check-cashouts` | Asks GTBank about open cash-outs |
| Every 10 minutes | `injongepay:mirror` | Reads GTBank's transaction list |
| Every 10 minutes | `injongepay:sweep-fees` | Credits fees to the platform wallets |
| Every 10 minutes | `injongepay:expire-approvals` | Expires waiting approvals |
| Every 15 minutes | `injongepay:sync-merchants` | Syncs waiting merchant codes, then live codes not checked for 6 hours, at most 100 per run |
| Daily 01:30 | `injongepay:close-day` | Closes yesterday and checks the books |
| Daily 02:00 | `injongepay:sync-merchants --all` | Syncs every merchant code |
| Daily 03:30 | `injongepay:prune-logs` | Deletes old GTBank call logs |

Add the scheduler once for the whole app. Skip this if the server already has it.

```bash
echo '* * * * * www-data cd /var/www/ish-core && php artisan schedule:run >> /dev/null 2>&1' | sudo tee /etc/cron.d/ish-core
sudo chmod 644 /etc/cron.d/ish-core
```

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

## Checking with GTBank by hand

Every request to GTBank has its own button, so nobody has to wait for a scheduled job. The jobs are only the safety net. If GTBank gives an error or no answer, the page says so and you can press it again.

| You see | Where | Button | Who |
|---|---|---|---|
| A cash-out not final yet | The cash-out's page | **Check status now** | The business and Inhills |
| A merchant code pending, or with an error | The code's page | **Check status now** (business), **Sync from GTBank** and **Register at GTBank** (Inhills) | The business and Inhills |
| A payment still pending | The payment's page | **Check status now** | The business |
| A settlement account GTBank didn't answer about | Settlement accounts, or Fee earnings for Inhills | **Verify at GTBank** | The business, or Inhills |
| Webhooks waiting | Money to allocate | **Process waiting webhooks** | Inhills |
| GTBank's list for today and yesterday | Reconciliation | **Read GTBank's list now** | Inhills |

A check only asks. A cash-out is never sent twice. Every press is in the audit log. A business can press these 10 times a minute, and only on its own records.

## Background jobs page

Open **Injonge Pay platform → Background jobs**. You need the `update injongepay_platform.setting` permission.

**Set each job:** on or off, and how often. Daily jobs take a time of day, in the server's timezone (`APP_TIMEZONE`). Saving asks for your password, and every change goes in the audit log.

A job that is off stops that work completely until it is on again or someone presses its button. With the mirror off, for example, payments stay pending and can't be cashed out.

**See if it is working:**

| Status | Meaning |
|---|---|
| Working | Its last run finished on time |
| Running now | It is running at this moment |
| Failed | Its last run ended with an error, or GTBank gave no answer to the mirror or the daily close |
| Late | It should have run by now. If every job is late, the scheduler has stopped |
| Off | Someone switched it off |
| Waiting for its first run | It hasn't run since it was installed |

The top of the page shows the **Scheduler** and the **Queue worker**. If either has a problem, the page shows the command to start it.

**How you get told:**
- A banner appears on Injonge Pay pages for Inhills staff who hold that permission, when a job is failed, late or off, or when the scheduler or queue has a problem.
- A job that fails sends one notification and email to those staff and to superadmins. It alerts again only if it fails again after working.
- Superadmins also see the core banner about the scheduler and queue on every page.

**Limits:**
- If the scheduler stops, nothing can send an email, because the scheduler is what would send it. It shows on the page and in the banners, so someone has to open the app. An outside uptime monitor is the only way to be told without opening it.
- A stopped queue worker shows only once work has waited more than 5 minutes, and only when `QUEUE_CONNECTION=database`. To check it directly: `sudo supervisorctl status ish-core-queue`
- The page covers this module's jobs only. The token clean-up in InjongePayConnect and jobs of other modules aren't listed.

## When GTBank is slow or down

A call to GTBank waits 20 seconds at most, then we leave. After 3 calls in a row with no answer, that job stops and tries again on its next turn. Nothing is lost, and the server isn't left waiting. The only longer wait is sending a cash-out, which allows 60 seconds because GTBank can take longer and still process it.

If the server restarts while a job is running, that job's lock frees itself within 60 minutes and the job carries on. To free it right away:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan schedule:clear-cache
```

## Disk space

**GTBank call logs:** deleted after 90 days by default. Change it on the Background jobs page, 30 days at least. Calls about cash-outs are always kept. The audit log is never deleted.

**Queue log:** rotate it, or it grows forever.

```bash
sudo tee /etc/logrotate.d/ish-core > /dev/null <<'EOF'
/var/www/ish-core/storage/logs/*.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
    copytruncate
    su www-data www-data
}
EOF
sudo logrotate -d /etc/logrotate.d/ish-core
```

Watch the disk: `df -h`

## Webhooks

GTBank must call these two addresses over https:

- `https://your-domain/api/payments/webhook`
- `https://your-domain/api/gtbank/disbursement-status`

GTBank is recognised by the secret header or by its IP address. Production logs show it sends no secret, so add its addresses in **Injonge Pay settings**, under **GTBank's addresses**.

## Set it up

1. Open **Injonge Pay settings** and set the fee and the bank's cut.
2. Fill in **GTBank's addresses** on the same page.
3. Under the platform's Security settings, set **Sign staff out after (minutes idle)** to 2.
4. Create the business and turn on **InjongePay** on its page.
5. Set its KYC and fee.
6. Create its merchant codes under **Merchant codes**, one at a time or with **Import from CSV**.
7. Each person who moves money sets a PIN under **My PIN & devices**.

## Check it works

- Without GTBank: leave `GTBANK_BASE_URL` empty. Merchant codes become active at once and cash-outs complete at once.
- With GTBank: register one merchant code, read one day's list, and make one small cash-out.
- Confirm the jobs run: open **Background jobs**, or run `sudo -u www-data php artisan schedule:list`
- Confirm the worker runs: `sudo supervisorctl status ish-core-queue`

More detail: `docs/injonge-pay-setup-guide.md`.
