# InjongePay

Mobile-money collection through GTBank PaymentBridge: merchant codes (wallets) per business, payments split into the business's share and the platform fee, a ledger that matches the bank, transfers, cash-outs to verified bank accounts, and daily reconciliation against GTBank.

**Requires:** no other module.

Businesses do everything except merchant codes. Injonge Pay creates merchant codes and sets KYC, fees and limits. It also handles reconciliation, unclear cash-outs and money to allocate.

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
| A cash-out not final yet | The cash-out's page | **Check status now** | The business and Injonge Pay |
| A merchant code pending, or with an error | The code's page | **Check status now** (business), **Sync from GTBank** and **Register at GTBank** (Injonge Pay) | The business and Injonge Pay |
| A payment still pending | The payment's page | **Check status now** | The business |
| A settlement account GTBank didn't answer about | Settlement accounts, or Fee earnings for Injonge Pay | **Check with GTBank** | The business, or Injonge Pay |
| Webhooks waiting | Money to allocate | **Process waiting webhooks** | Injonge Pay |
| GTBank's list for today and yesterday | Reconciliation | **Read GTBank's list now** | Injonge Pay |

A check only asks. A cash-out is never sent twice. Every press is in the audit log. A business can press these 10 times a minute, and only on its own records.

## Every safeguard

This is the full list of what protects the money, how each part works, and its limits. The rule behind all of it: **GTBank is the truth, and when it isn't clear, no money moves.**

### Money coming in

| Safeguard | How it works |
|---|---|
| Nothing is lost | Each webhook is saved first, then processed. If processing fails, the every-minute job finishes it |
| A payment counts once | `transactionId` is unique in the database, so a repeat returns the payment we already have |
| Pending, then available | A webhook puts money in **pending**. It becomes **available** only when GTBank's own list shows the same transaction, amount and code. A forged webhook can't be cashed out |
| A missed webhook is found | The transaction list reads every 10 minutes and adds what we missed |
| A payment for a code that can't take it | Parked under **Money to allocate** instead of refused. Staff place it |
| Only `SUCCESS` credits | Other statuses are recorded and credit nothing |
| A reversal at GTBank | The list finds it and takes the money back. If it was already spent, nothing goes negative and staff get an item |
| Fees wait too | Fees reach the platform wallets only after GTBank confirms the payment |
| Not confirmed after 24 hours | Stays pending and goes to staff |

### The books

| Safeguard | How it works |
|---|---|
| Whole cents | Money is stored as whole cents, never as decimals that can round |
| One writer | Only the ledger changes a balance, and it locks the wallet first |
| Never negative | A movement that would go below zero is refused |
| No double entry | Each entry has a unique key, so a repeat does nothing |
| Entries aren't edited | Corrections are new entries |
| Daily close | Each code's day is compared with GTBank, count and total |
| Books check | Each balance is recomputed from its entries, and the platform total is checked: payments = balances + fees waiting + money out |
| Statement check | GTBank's monthly CSV is compared with the books. The closing balance must agree |

### Who can move money

| Safeguard | How it works |
|---|---|
| PIN | 5 digits, stored hashed. 3 wrong tries lock it for 30 minutes |
| Changing a PIN | Needs the authenticator or an emailed code |
| Extra code | Asked on a new device, a large amount, the first payout to an account, 5 or more requests in an hour, a high hourly total, or a PIN changed in the last 24 hours |
| Limits | Per transaction, per day and per month, set per business |
| Second person | Above a set amount a second person approves with their own PIN. Nobody approves their own request |
| Requests expire | One not approved within 24 hours ends by itself |
| Checked accounts | An account can be used once GTBank's name for it matches ours |
| Businesses are separate | A business only reaches its own wallets and records. Transfers stay between its own wallets |
| Idle sign-out | Staff are signed out after the idle time set in the platform settings |
| Audit | Every action is logged per business |

### A request can't be made twice

| Layer | How it works |
|---|---|
| One-time token | Each cash-out and transfer form has a token. A double click or browser resend returns the same request |
| No identical repeats | The same amount, wallet and destination within 2 minutes is refused, even from another tab. The check runs under the wallet's lock |
| Database rule | The database refuses a used token, and a second cash-out or transfer on one approval |

Tested with 8 processes at the same instant on Postgres: one form gave 1 cash-out and 1 debit, and 8 tabs gave 1 cash-out and 7 refusals.

### A cash-out can't go to GTBank twice

| Layer | How it works |
|---|---|
| One reference | Each cash-out has one unique reference, sent once |
| One worker | A worker takes it under a lock. Tested with 8 workers: 1 took it |
| No queue retry | The sending job has one try |
| No resend, ever | After a timeout or unclear answer, nothing sends it again. **Check status now** and the jobs only ask |
| New money needs a new request | If GTBank never paid, the business makes a new cash-out with a new reference |

### When GTBank doesn't answer clearly

A timeout, an error, an odd status, a garbled reply or no reply are all treated the same: **no money moves, and we ask again.** When GTBank does answer, the next check, button or webhook updates us.

1. The money is **held**. It leaves the available balance so it can't be spent twice, but nothing says it left. A payment shows as cashed out only once GTBank has paid.
2. We ask about the same reference: after 1, 2, 5, 10, 20, 30 and then every 60 minutes.
3. Anyone can press **Check status now** at any time.

| GTBank says | What happens |
|---|---|
| Paid, twice | Completed |
| Rejected twice, 10 minutes apart, and it had clearly accepted the send | The money returns |
| Rejected, and the send wasn't clear | Staff decide, the money stays held |
| Never heard of it | Staff decide after 2 hours, the money stays held |
| Another amount, account or reference | Staff decide, nothing is applied |
| Nothing clear after 2 hours | Staff decide, we keep asking |

Pressing the button twice counts as one rejection. Money comes back once at most.

### After a cash-out closes

We keep asking GTBank about that reference for 7 days, every hour if the money came back and once a day if it was paid. A webhook is caught at any time. These checks only read.

| GTBank says | What happens |
|---|---|
| Paid, after the money came back | The wallet **freezes** and staff are alerted |
| Reversed, after it was paid | Staff are alerted and nothing moves. **Give the money back** appears on the cash-out and works once |

A frozen wallet still receives payments, but no cash-out or transfer leaves it, including one waiting in the queue. Staff lift the freeze on the wallet's page, with their password and a reason.

### Batches and limits

These stop big volumes from overloading GTBank or the server.

| Work | Limit |
|---|---|
| Merchant codes from a CSV | Up to 1,000 lines. Every line is checked first, and if one is wrong nothing is created |
| Registering those codes | One every 3 seconds |
| Reading GTBank's list | One call for all merchant codes |
| Daily close | One call for all merchant codes |
| Merchant code sync | At most 100 per run, oldest first, skipping any checked in the last 10 minutes |
| Live code check | Each active code at least every 6 hours |
| Open cash-out check | At most 100 per run |
| Closed cash-out check | At most 100 per run |
| Waiting on GTBank | 20 seconds per call. Sending a cash-out allows 60, because GTBank has taken longer and still paid |
| GTBank not answering | After 3 calls in a row with no answer, the job leaves and tries on its next turn |
| A job already running | It can't start twice |
| Server restart mid-job | The lock frees itself within 60 minutes. To free it now: `sudo -u www-data php artisan schedule:clear-cache` |
| Old GTBank call logs | Deleted after 90 days, 1,000 at a time. Calls about cash-outs are kept |
| Buttons | **Check status now**: 10 presses a minute |
| Webhooks | 600 requests a minute per address |

### Seeing that it works

- **Background jobs** shows each job, the scheduler and the queue.
- Injonge Pay staff see a banner when a job has failed, is late or is off.
- A failed job sends one alert.
- **Reconciliation** lists every open difference with what to check.
- Every call to GTBank is logged, and you can open it.

### What this can't protect

- **Staff decisions.** Marking a cash-out as failed returns the money. Confirm with GTBank first.
- **After 7 days.** A contradiction after that isn't caught by these checks. The monthly statement check is the backstop.
- **Money already gone.** If it was cashed out before a freeze, recovering it is a job for people.
- **A stopped scheduler.** Nothing can email you about it. It shows in the app, so use an outside uptime monitor to be told without opening it.
- **A stopped queue worker.** It shows once work has waited more than 5 minutes, and only with `QUEUE_CONNECTION=database`.
- **GTBank's real behaviour.** All of this was tested against imitated GTBank answers based on your production logs and its documentation. The first live cash-out still has to confirm it.

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
- A banner appears on Injonge Pay pages for Injonge Pay staff who hold that permission, when a job is failed, late or off, or when the scheduler or queue has a problem.
- A job that fails sends one notification and email to those staff and to superadmins. It alerts again only if it fails again after working.
- Superadmins also see the core banner about the scheduler and queue on every page.

**Limits:**
- If the scheduler stops, nothing can send an email, because the scheduler is what would send it. It shows on the page and in the banners, so someone has to open the app. An outside uptime monitor is the only way to be told without opening it.
- A stopped queue worker shows only once work has waited more than 5 minutes, and only when `QUEUE_CONNECTION=database`. To check it directly: `sudo supervisorctl status ish-core-queue`
- The page covers this module's jobs only. The token clean-up in InjongePayConnect and jobs of other modules aren't listed.

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

This section is the contract with GTBank. Injonge Pay has two webhooks: one for money coming in, one for cash-outs.

### Common rules

| | |
|---|---|
| Method | `POST` |
| Protocol | https only |
| Content type | `application/json` |
| Answer | JSON, within seconds |
| Rate limit | 600 requests a minute per sending address |
| Identification | The sending IP address, or the optional secret header |

**Identification.** A request is accepted if either is true:
- It comes from an IP address GTBank has given us.
- It carries the header `X-GTBank-Webhook-Secret` with the agreed value. The header is optional, but if it is sent it must be correct.

**Sending the same webhook again is safe.** Each event is recognised by its identifier and status, so a repeat changes nothing. Please resend if you get no answer, a `429` or a `500`.

**Every answer has this shape:**

```json
{ "success": true, "message": "Text", "data": null }
```

```json
{ "success": false, "message": "Text", "errors": null }
```

### 1. Payment received

Sent when a payment reaches a merchant code.

`POST https://your-domain/api/payments/webhook`

| Field | Type | Required | Meaning |
|---|---|---|---|
| `transactionId` | string, max 64 | Yes | GTBank's unique ID for the transaction |
| `merchantId` | string, max 20 | Yes | The merchant code paid |
| `amount` | number, at least 0.01 | Yes | Amount in RWF, at most 2 decimals |
| `valueDate` | date | No | Value date |
| `status` | string | No | `SUCCESS` if left out. Only `SUCCESS` credits money |
| `participantName` | string | No | Participant name |
| `initiatingPartyName` | string | No | Channel, for example `MTN` |
| `initiatingPartyId` | string | No | Channel code |
| `debtorName` | string | No | Payer's name |
| `debtorAccountId` | string | No | Payer's account or phone |
| `creditorName` | string | No | Name on the merchant code |
| `creditorAccountId` | string | No | The merchant code |

**Example request:**

```json
{
  "transactionId": "FT26308ABCDE",
  "merchantId": "55500001",
  "amount": 10000.00,
  "valueDate": "2026-11-04",
  "status": "SUCCESS",
  "participantName": "INJONGE PAY",
  "initiatingPartyName": "MTN",
  "initiatingPartyId": "MTN",
  "debtorName": "JOHN DOE",
  "debtorAccountId": "250788111222",
  "creditorName": "Rhenium Group",
  "creditorAccountId": "55500001"
}
```

**Example answer (201):**

```json
{
  "success": true,
  "message": "Payment received.",
  "data": {
    "reference": "PAY20261104000001",
    "wallet_merchant_code": "55500001",
    "gross_amount": 10000.0,
    "fee_amount": 200.0,
    "net_amount": 9800.0,
    "status": "AVAILABLE"
  }
}
```

| HTTP | `message` | Meaning | What GTBank should do |
|---|---|---|---|
| `201` | `Payment received.` | Recorded for the first time | Nothing |
| `200` | `Payment already received.` | A repeat of a transaction we have | Nothing |
| `200` | `Payment received and held for review.` | We have it, but this merchant code can't take it now. The money isn't lost | Nothing |
| `200` | `Acknowledged.` | A status other than `SUCCESS`. Recorded, nothing credited | Nothing |
| `401` | `Unauthorized` | Unknown address, or a wrong secret | Fix the address or secret, then resend |
| `422` | `Validation failed.` or a reason | The request is wrong. `errors` lists the fields | Fix the request. Resending it unchanged won't help |
| `429` | | Too many requests | Resend later |
| `500` | `Could not process payment.` | A problem on our side. The webhook is kept and processed | Resend |

### 2. Cash-out status

Sent when a cash-out reaches a final status.

`POST https://your-domain/api/gtbank/disbursement-status`

| Field | Type | Required | Meaning |
|---|---|---|---|
| `identifier` | string, max 64 | Yes | Our cash-out reference, or the identifier GTBank gave when it accepted the transfer |
| `status` | string, max 20 | Yes | `ACSC` for paid, `RJCT` for rejected |
| `amount` | number | No | Amount in RWF. It must match the cash-out |
| `currency` | string, 3 letters | No | `RWF` |
| `beneficiaryAccount` | string | No | Account paid. It must match the cash-out |
| `receiverNames` | string | No | Name on the account paid |
| `creditTime` | string | No | When the account was credited |
| `completedAt` | string | No | When GTBank finished |

We also understand `CANC`, `SUCCESS`, `COMPLETED`, `REJECTED`, `CANCELLED`, `DECLINED` and `REVERSED`.

**Example request:**

```json
{
  "identifier": "CSH20261104000001",
  "status": "ACSC",
  "amount": 1000,
  "currency": "RWF",
  "beneficiaryAccount": "0788811607",
  "receiverNames": "Rose Mary MUSABYIMANA",
  "creditTime": "2026-11-04T15:28:17.006Z",
  "completedAt": "2026-11-04T15:28:25.123Z"
}
```

**Example answer (200):**

```json
{ "success": true, "message": "Acknowledged.", "data": null }
```

| HTTP | `message` | Meaning | What GTBank should do |
|---|---|---|---|
| `200` | `Acknowledged.` | Received. This includes an identifier we don't know, or an amount or account that doesn't match. Those are kept for our staff | Nothing |
| `401` | `Unauthorized` | Unknown address, or a wrong secret | Fix the address or secret, then resend |
| `422` | `Validation failed.` | The request is wrong. `errors` lists the fields | Fix the request. Resending it unchanged won't help |
| `429` | | Too many requests | Resend later |
| `500` | `Could not process disbursement status.` | A problem on our side | Resend |

A `200` means we received the webhook, not that the cash-out is closed. We also confirm with `/disbursement/status` before closing it.

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
