# Injonge Pay: setting it up, step by step

How to go from an empty ISH Core install to a business taking payments and cashing out. For each step: where to click, and what it replaces in the old Injonge Pay app.

## The idea in one minute

In the old app, everything lived in one place: you created an *institution*, its first user, its wallets and its settlement destinations from the same admin screens.

In ISH Core the work is split in three layers. You always go through them in this order:

| Layer | Who | Where in the app | What lives there |
|---|---|---|---|
| **1. The server** | whoever runs the server | `.env`, cron, queue | GTBank credentials, the scheduler and the queue |
| **2. The core** | superadmin | **Super admin** menu (Businesses, Modules, Platform settings) | businesses and their owners, which modules a business has, security rules, roles |
| **3. Injonge Pay** | Inhills staff | the **Injonge Pay platform** group in the sidebar | KYC, fees, merchant codes, bank accounts, reconciliation, cash-outs, partners |

Businesses (the old institutions) run themselves: their own users and roles, settlement accounts, payments, transfers, cash-outs, reports. The one thing only Inhills does is **create merchant codes** (they are registered at GTBank), along with the fee, KYC and limits that go with them.

Inhills itself is the **primary business** (the one created when the app was installed). Its staff are "platform staff", and the platform's own fee wallets belong to it.

## Old app → new app at a glance

| Old app | New app |
|---|---|
| Settings → Institutions → create | Super admin → Businesses → **Add business** (name, **type**, owner) |
| Institution TIN, contacts, fee % | Injonge Pay platform → **Businesses (KYC & fees)** |
| First user of the institution | The **owner** entered when adding the business |
| Institution users | The business's own **Users** and **Roles** |
| Wallet create + "Retry registration" | Injonge Pay platform → **Merchant codes** → New (sent to GTBank by itself) |
| "Sync from GTBank" | Same button on the merchant code, and it runs by itself every 15 minutes |
| Settlement destinations (with OTP) | The business's own **Injonge Pay → Settlement accounts** (PIN; GTBank checks the holder name, then it is usable at once) |
| Platform settings: fee, bank cut, webhook secret | Injonge Pay platform → **Injonge Pay settings** |
| Webhook secret on/off switch | Gone. GTBank is recognised by its address (`10.22.198.46`) or by the secret |
| Sync transactions → Save all | Automatic every 10 minutes; button in **Reconciliation with GTBank** |
| Record payment (manual) | Gone. Money that couldn't be credited waits in **Money to allocate** |
| Refunds | Gone |
| OTP email on every transfer/cash-out | **PIN** every time; an extra code only when something is unusual |
| Cash-out "Retry" / "Reverse" | Never needed by the business. Unclear cash-outs go to Inhills in **Cash-outs (all)** |
| API credentials on the institution | The business's own **Integrations → Connect a system** (Inhills only registers the partner systems) |
| Financial monitor | Injonge Pay platform → **Money overview** |
| API call logs | **GTBank call log** (bank) and the call list in **Partners & API** (POS) |

---

## Trying it on your laptop first

Leave `GTBANK_BASE_URL` empty: the app is then in **test mode** and says so on every Injonge Pay page. Nothing goes to GTBank:
- merchant codes are active at once
- settlement accounts are accepted with the name you type
- payments are available at once
- cash-outs complete by themselves within a minute

Keep `php artisan schedule:work` running; with `QUEUE_VIA_SCHEDULER=true` it also runs the queue. To fake a payment from GTBank, add `127.0.0.1` to *GTBank's addresses* (step B3), then:

```
curl -X POST http://127.0.0.1:9000/api/payments/webhook -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"transactionId":"TEST-1","merchantId":"55500001","amount":10000,"status":"SUCCESS","initiatingPartyName":"MTN","debtorName":"JOHN DOE","debtorAccountId":"250788111222"}'
```

Use a new `transactionId` each time. Set the idle sign-out to `0` while testing.

## Part A: the server (once)

### A1. GTBank connection in `.env`

Copy these from the old server's `.env`. The names are the same:

```
GTBANK_BASE_URL=https://10.22.198.46:9024/prodpaymentbridge
GTBANK_CLIENT_ID=...
GTBANK_CLIENT_SECRET=...
GTBANK_PARTICIPANT_NAME=...
GTBANK_SSL_VERIFY=true
EBM_API_KEY=...            # only if the old EBM lookup is still used
```

- If GTBank's certificate is private, copy `storage/key/gtbank-ca.pem` from the old server too.
- **Leave `GTBANK_BASE_URL` empty on your laptop.** That is test mode: nothing goes to GTBank, merchant codes become active at once, and cash-outs complete at once.
- Production also needs `APP_ENV=production`, an `https://` `APP_URL` and `SESSION_SECURE_COOKIE=true`.

### A2. The scheduler and the queue. Without them nothing moves

Most of Injonge Pay runs in the background:

- registering merchant codes
- sending cash-outs
- asking GTBank for their status
- reading GTBank's list
- closing the day
- crediting fees

On the server:

```
* * * * * cd /path/to/app && php artisan schedule:run      # cron
php artisan queue:work                                    # kept running by Supervisor/systemd
```

If the server has cron only, set `QUEUE_VIA_SCHEDULER=true` in `.env` and the queue then runs from cron. On a laptop, run `php artisan schedule:work` and `php artisan queue:work` in two terminals.

What runs, and when:

| Every | Job |
|---|---|
| minute | process stored GTBank webhooks; check open cash-outs |
| 10 minutes | read GTBank's transaction list (one call for all merchant codes); credit fees to the platform wallets |
| 15 minutes | sync merchant codes waiting on GTBank, then live codes not checked for 6 hours (at most 100 per run, oldest first) |
| 01:30 | close yesterday for every merchant code (one call to GTBank); check the books |
| 02:00 | sync every merchant code |
| 03:30 | delete old GTBank call logs (90 days by default; calls about cash-outs are kept) |

Each job also has a button on its screen.

Those are the defaults. **Injonge Pay platform → Background jobs** sets each job on or off and how often, and shows whether it is working, along with the scheduler and the queue.

### A3. Install the modules

**Super admin → Modules**: install **InjongePay**, then **InjongePayConnect** (only needed if POS/EBM systems connect).

---

## Part B: the platform, once (as superadmin)

### B1. Security

**Super admin → Platform settings → Security**:

- **Sign staff out after (minutes idle)**: `2`.
- **Businesses manage their own staff and roles**: leave **on** (the default). Each business manages its own users and roles.

### B2. The Inhills team (platform staff)

Inhills staff are users of the **Inhills** business. In Inhills, open **Users → Roles** and create roles with the **"Injonge Pay platform (operator only)"** permissions. These permissions only exist in the Inhills business; a customer business's Admin never gets them. For example:

| Role | Give it |
|---|---|
| Onboarding officer | Businesses (KYC and fees), Merchant codes, GTBank registration/sync |
| Finance officer | Reconciliation, Cash-outs (approve), Fee rates, Fee earnings |
| Support | read only on the above, GTBank call log, Partners & API (integrators, call log) |

The Inhills Admin (owner) has everything automatically.

### B3. Injonge Pay settings

**Injonge Pay platform → Injonge Pay settings**:

1. **Fees**: the platform fee % and the bank cut (as a % *of the fee*). Up to 4 decimals. A business can have its own fee later (step C3).
2. **GTBank's addresses**: `10.22.198.46`. GTBank sends no secret header, so its webhooks are recognised by this address.
3. **Webhook secret**: optional. Generate one only if GTBank agrees to send it.
4. **Check the line under it**:
   - "Connected": `GTBANK_BASE_URL` is set.
   - "Not connected": test mode.
5. The two **webhook addresses** shown are the ones GTBank must call. They are the same paths as the old app (`/api/payments/webhook`, `/api/gtbank/disbursement-status`), so if the domain stays the same, GTBank changes nothing.

### B4. Partners (only if a POS/EBM connects)

**Injonge Pay platform → Partners & API → Integrators → Add integrator**: name `Injonge EBM`, contract `pos-ebm-v1`. You do this once per partner system. Businesses then connect to it themselves.

---

## Part C: each new business

Do these in order. Steps C1–C2 are core; C3–C7 are Injonge Pay.

### C1. Create the business

**Super admin → Businesses → Add business**:

- the business name
- its **type** (school, hospital, fuel station, …)
- the owner's name, email and password

The owner becomes the business's Admin.

### C2. Give it the module

On the business's page (**Super admin → Businesses → the business**), tick **InjongePay** (and **InjongePayConnect** if a POS will connect) under *Modules for this business*. If the Billing module is installed, the business's **package** decides instead.

### C3. KYC and fee

**Injonge Pay platform → Businesses (KYC & fees) → the business → KYC & fee**:

- **TIN** (9 digits), owner or representative, contact email.
- **Contact phone as `+250XXXXXXXXX`.** GTBank refuses any other format.
- **Fee (%)**: blank means the platform rate.
- The business can also edit its own contacts and address later (Injonge Pay → Business details). TIN, fee and limits stay with Inhills.
- **Limits and approvals**, in RWF, blank means no limit:
  - *Most per operation / day / month*
  - *Extra check above*: the PIN is not enough above this amount; the authenticator or emailed code is needed too.
  - *Second person above*: another user must approve.

### C4. Merchant codes (the old wallets)

**Injonge Pay platform → Merchant codes → New merchant code**: choose the business, give it a name ("Main till", "Pump 1"). You only fill in the owner, TIN, phone or email if this code differs from the business's KYC.

What happens next, by itself:

1. The next code (`555` + 5 digits) is taken and sent to GTBank: **Being registered**.
2. GTBank accepts it: **Waiting for GTBank**.
3. Within 15 minutes (or press **Sync from GTBank**): **Active**. Only an active code receives payments.

The merchant code's page shows every call made to GTBank and its last problem. A network error never marks the code as failed. **Registration failed** means GTBank really refused it, with the reason shown. Fix the KYC and press **Register at GTBank** again.

**Many codes at once** (a business with 50 or 500 tills): **Merchant codes → Import from CSV**. Choose the business and upload a file with a `name` column, plus `owner`, `tin`, `phone` and `email` if they differ from the KYC (**Download template** gives an example; commas or semicolons both work, and up to 1,000 lines per file). Every line is checked first. If one line is wrong (a name used twice, a bad TIN or phone), nothing is created and the page lists the lines to fix. When the file is good, all the codes are created at once and sent to GTBank from the queue, one every 3 seconds, so 500 codes take about 25 minutes to be registered. **Export CSV** on the list (filtered by the business) gives the business its names and merchant codes.

To stop a code temporarily, use **Switch off**: payments to it are held, not lost. **Close at GTBank** is final and needs a zero balance.

### C5. Hand over to the business

To help a business with anything in its own panel, use **Super admin → Businesses → the business → Log in as**. Inhills never manages a business's settlement accounts, staff or connections from the platform pages.


That's all Inhills does. The business's owner signs in and does the rest:

- **Users and Roles**: their own staff, for example:

  | Role | Give it (Injonge Pay) |
  |---|---|
  | Cashier | Overview, Payments received (list/read) |
  | Accountant | the above + Cash-outs (list/create), Transfers (list/create), Reports, Ledger, Settlement accounts |
  | Manager | the above + Cash-outs and Transfers (**approve**) |

- **Injonge Pay → Settlement accounts → Add an account**:
  - bank account or mobile wallet, and the number (wallets as `07…`)
  - the holder's name exactly as at the bank
  - their PIN

  GTBank is asked who holds the account. If the name matches, it can be used for cash-outs straight away. If not, it says what name GTBank has. The first cash-out to a new account asks for the extra code as well.

### C6. PINs

Everyone who will move money opens **Injonge Pay → My PIN & devices** and sets a 5-digit PIN. It is asked for every transfer, cash-out and approval. Turning on two-step sign-in (an authenticator app) in their profile is better than emailed codes.

### C7. The POS (if any)

The business does this itself: **Injonge Pay → Integrations → Connect a system**, then choose its POS. The **client ID and secret are shown once**; it gives them to its POS team. Renewing the secret and switching it off are on the same page.

To move a business already connected to the old app, see the data migration (its old credentials will keep working).

The business is ready.

---

## Part D: what to look at every day (Inhills)

| Screen | Look for |
|---|---|
| **Money overview** | The books line must say *every franc is accounted for*. |
| **Reconciliation with GTBank** | Open differences (each says what to check); yesterday's close per merchant code. |
| **Cash-outs (all) → Being checked with GTBank** | Cash-outs GTBank gave no final answer for. Confirm with GTBank, then **Settle**. Never guess. |
| **Money to allocate** | Payments GTBank credited to a code that couldn't take them. **Credit** them to the right wallet, or record how they were settled. |
| **GTBank call log** | When something looks wrong: every request and answer, secrets hidden. |
| **Fee earnings** | Inhills' share of the fees and GTBank's cut. Cash them out to Inhills' own settlement accounts, added on the same page, exactly as a business does: PIN, an account GTBank confirms, the extra code the first time. |

You get a notification (in the app and by email) whenever one of these needs you.

## What the business does (for their training)

1. **Dashboard and Injonge Pay overview**: available and pending money, today's payments, cash-outs on the way. Click a merchant account for its own page.
2. **Payments received**: new payments appear as *Waiting for GTBank*, then *Available* once GTBank's own list confirms them (within ~10 minutes).
3. **Settlement accounts**: add the bank account or wallet cash-outs go to (PIN; GTBank must know the holder name).
4. **Cash-outs → New cash-out** (or **Cash out** on a merchant account):
   - choose the wallet, the approved account and a whole RWF amount
   - enter the PIN, plus the extra code if asked
   - follow the status: *Sent* → *Paid*. If it says *Being checked with GTBank*, the money is safe and Inhills is on it.
5. **Transfers**: move money between their own merchant accounts. The receiving account is shown before the PIN is asked.
6. **Approvals**: a second person approves large amounts with their own PIN.
7. **Reports**: totals for any period, PDF/Excel.
8. **Integrations**: connect their POS/EBM and see which invoices it linked to which payments.
