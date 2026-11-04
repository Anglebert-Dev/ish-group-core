# InjongePayConnect

Partner integrations for Injonge Pay: POS/EBM systems and other integrators read a business's merchant accounts and payments and link payments to their invoices. Each integrator has its own contract, credentials per business, rate limits and a call log.

**Requires:** InjongePay. Install these first.

## Install on Ubuntu

The commands use `/var/www/ish-core` as the app folder. Use your own path if it differs.

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module install InjongePay
sudo -u www-data php artisan core:module install InjongePayConnect
```

You can also install it from **Super Admin → Modules**.

To update it after new code is on the server:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan core:module update InjongePayConnect
```

New migrations run only if the `version` in `module.json` went up.

## `.env`

| Key | Required | What it is |
|---|---|---|
| `EBM_API_KEY` | Only for the old lookup | Key for `GET /api/ebm/payments/lookup`, sent as `X-EBM-Api-Key`. Empty means that endpoint refuses every call. New integrations use `/api/partner/v1`. |

After changing `.env`:

```bash
cd /var/www/ish-core
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan queue:restart
```

## Scheduler

| When | Command | Does |
|---|---|---|
| Daily 03:00 | `injongepayconnect:prune-tokens` | Removes expired tokens |

Add the scheduler once for the whole app. Skip this if the server already has it.

```bash
echo '* * * * * www-data cd /var/www/ish-core && php artisan schedule:run >> /dev/null 2>&1' | sudo tee /etc/cron.d/ish-core
sudo chmod 644 /etc/cron.d/ish-core
```

## Set it up

1. Turn on **InjongePayConnect** on the business's page.
2. As Inhills, create the integrator. Use the `pos-ebm-v1` contract for the Injonge EBM/POS.
3. Set its rate limit if the default of 120 requests a minute doesn't fit.
4. As the business, create the POS connection. The secret is shown once, so copy it then.
5. Give the partner the client ID, the secret and the API address `https://your-domain/api/partner/v1`.

## Check it works

```bash
curl -X POST https://your-domain/api/partner/v1/oauth/token \
  -H 'Content-Type: application/json' \
  -d '{"client_id":"YOUR_ID","client_secret":"YOUR_SECRET"}'
```

A valid pair returns an `access_token`, valid for one hour.
