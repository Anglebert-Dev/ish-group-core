# Injonge Pay — System Analysis (module by module)

> Generated 2026-11-03 from the code on branch `refactor/code-quality` (commit `1b89672`).
> Everything below was read from the source, not from the older `ARCHITECTURE_REPORT.txt` / `DEVELOPMENT_PLAN.md`, which are out of date (they describe PostGIS, a `staff` table and other things that no longer exist).
> File references are relative to the repo root.

---

## Table of contents

0. [TL;DR](#0-tldr)
1. [System overview](#1-system-overview)
2. [Module map](#2-module-map)
3. Modules
   - [M1 Foundation (base model, audit, UUIDs, helpers)](#m1-foundation)
   - [M2 Authentication & session](#m2-authentication--session)
   - [M3 Access control (roles, permissions, tenancy)](#m3-access-control)
   - [M4 Users & monitoring](#m4-users--monitoring)
   - [M5 Institutions (tenants)](#m5-institutions-tenants)
   - [M6 Settlement destinations](#m6-settlement-destinations)
   - [M7 Wallets & merchant codes](#m7-wallets--merchant-codes)
   - [M8 Ledger (the money core)](#m8-ledger-the-money-core)
   - [M9 Payments in (buyer payments + fee split)](#m9-payments-in)
   - [M10 OTP authorization (financial approvals)](#m10-otp-authorization)
   - [M11 Transfers](#m11-transfers)
   - [M12 Refunds](#m12-refunds)
   - [M13 Cash-outs & disbursement](#m13-cash-outs--disbursement)
   - [M14 GTBank integration layer](#m14-gtbank-integration-layer)
   - [M15 Inbound webhooks](#m15-inbound-webhooks)
   - [M16 Platform settings & fee history](#m16-platform-settings--fee-history)
   - [M17 PartnerConnect (POS / EBM partner API)](#m17-partnerconnect-pos--ebm-partner-api)
   - [M18 Legacy EBM lookup API](#m18-legacy-ebm-lookup-api)
   - [M19 Reporting & dashboards](#m19-reporting--dashboards)
   - [M20 Observability (logs & audit)](#m20-observability)
   - [M21 Notifications](#m21-notifications)
4. [End-to-end data flows](#4-end-to-end-data-flows)
5. [State machines](#5-state-machines)
6. [Data model (all tables)](#6-data-model)
7. [Reference & identifier formats](#7-reference--identifier-formats)
8. [Route inventory](#8-route-inventory)
9. [Configuration & environment](#9-configuration--environment)
10. [Findings: gaps, bugs, risks](#10-findings-gaps-bugs-risks)
11. [Building the InjongePay client module — what you need to know](#11-building-the-injongepay-client-module)

---

## 0. TL;DR

- **What it is:** a Laravel 12 / PHP 8.2 / PostgreSQL multi-tenant collection platform operated by Injonge Pay. Tenants are **Institutions** (merchants, identified by TIN). Each institution has one or more **Wallets**; each wallet has a GTBank **merchant code** (`555xxxxx`) that customers pay into via mobile money (MTN/Airtel) through **GTBank PaymentBridge**.
- **Money in:** GTBank posts a **webhook** → `PaymentService::receive()` creates a `buyer_payments` row, takes a **fee**, and posts **ledger credits** to the merchant wallet (net) and to two internal wallets (bank cut + platform revenue).
- **Money moves:** inside the system only through `LedgerService::post()` — **transfers** (wallet → wallet), **refunds** (debit wallet), **cash-outs** (debit wallet + GTBank disbursement to a bank account). Every outgoing operation needs an emailed **6-digit OTP**.
- **Money out to partners (your module):** the **PartnerConnect** module exposes `api/partner/v1/*` — OAuth-style client credentials → list merchant accounts → list payments → `confirm` a payment against a POS/EBM invoice (or `unconfirm`). It **never touches** balances or `buyer_payments`; confirmations live in their own table.
- **No background jobs, no scheduler, no tests** beyond Laravel's examples. Missed webhooks are recovered by an admin clicking "sync" against GTBank.
- **Important for you:** the contract in `injonge-emb.md` (reserve → confirm → release) is **outdated**. The live API is **invoice-first: confirm / unconfirm** (see `app/Modules/PartnerConnect/API.md`). Several behaviours of the live API will surprise a POS client — see §11.

---

## 1. System overview

### 1.1 Actors

| Actor | How they get in | What they do |
|---|---|---|
| **Super Admin** (Injonge Pay) | email + password + OTP/2FA | Everything; `Gate::before` grants all abilities |
| **Platform staff** (Injonge Pay users with `institution_id = NULL`) | email + password + OTP/2FA | Manage institutions, wallets, platform fees, logs — per their role's permissions |
| **Support staff** | same as platform staff | A platform user assigned to an institution via `institutions.support_staff_id`; treated as a member of it |
| **Institution users** (merchant staff) | **TIN + username** + password + OTP/2FA | Work inside their own institution: wallets, transfers, refunds, cash-outs, reports, users |
| **GTBank PaymentBridge** | shared secret header | Sends payment and disbursement-status webhooks; answers our outbound API calls |
| **POS / EBM partner** (your module) | `client_id` / `client_secret` → bearer token | Reads merchant accounts & payments; links payments to invoices |
| **Legacy EBM** | single `X-EBM-Api-Key` | Looks up one payment by reference |

### 1.2 Tech stack

- Laravel 12, PHP ^8.2, PostgreSQL (uses Postgres **sequences** for merchant codes; SQLSTATE `23505` handling).
- `spatie/laravel-permission` (RBAC), `pragmarx/google2fa` + `bacon/bacon-qr-code` (TOTP 2FA), Guzzle via Laravel HTTP client, Sanctum (installed, only used by the default `/api/user` route).
- Blade views on the Spruha Bootstrap admin theme.
- Timezone `Africa/Kigali`; currency is always `RWF`.
- Cache store: `database` (GTBank token is cached there). Queue: `database` but nothing is queued — notifications are sent synchronously (login OTP is sent with `dispatch(...)->afterResponse()`).

### 1.3 Big picture

```
                 ┌──────────────────────────── INJONGE PAY ────────────────────────────┐
 Customer pays   │                                                                      │
 *182# / MoMo ──►│  GTBank ──webhook──► PaymentWebhookController ─► PaymentService      │
 to merchant     │  PaymentBridge          (secret header)            │                 │
 code 555xxxxx   │      ▲                                             ▼                 │
                 │      │ outbound API                         LedgerService::post()    │
                 │      │ (GTBankClient)                ┌─────────┼──────────┐          │
                 │      │                               ▼         ▼          ▼          │
                 │      │                        merchant     bank-cut   platform-rev    │
                 │      │                        wallet(net)  wallet     wallet          │
                 │      │                               │                                │
                 │      │   OTP-approved operations:    │                                │
                 │      ├── cash-out (disbursement) ◄───┤ debit                          │
                 │      │                               ├── transfer → other wallet      │
                 │      │                               └── refund (ledger only)         │
                 │                                                                      │
 POS / EBM ─────►│  PartnerConnect API  ── reads buyer_payments, writes only            │
 (your module)   │  api/partner/v1           partner_payment_confirmations              │
                 └──────────────────────────────────────────────────────────────────────┘
```

---

## 2. Module map

| # | Module | Main files |
|---|---|---|
| M1 | Foundation | `app/Models/BaseModel.php`, `Models/Concerns/HasUuid.php`, `Models/Concerns/LogsActivity.php`, `Models/CustomBuilder.php`, `Http/Controllers/Controller.php`, `Libraries/GeneralLibrary.php` |
| M2 | Auth & session | `Http/Controllers/Auth/*`, `Http/Middleware/App.php`, `Models/User.php`, `Services/Auth/TwoFactorAuthenticationService.php` |
| M3 | Access control | `config/access.php`, `Providers/AuthServiceProvider.php`, `Users/RoleController.php`, `Institution::_can()/isMember()` |
| M4 | Users & monitoring | `Users/AccountController.php`, `Users/MonitorController.php`, `Institution/InstitutionUserController.php` |
| M5 | Institutions | `Models/Institution/Institution.php`, `Settings/InstitutionController.php`, `Institution/InstitutionController.php` |
| M6 | Settlement destinations | `Models/Institution/InstitutionSettlementDestination.php`, `Services/Institution/SettlementDestinationService.php`, `Institution/InstitutionSettlementDestinationController.php` |
| M7 | Wallets | `Models/Wallet/Wallet.php`, `Settings/WalletController.php`, `Institution/InstitutionWalletController.php`, `Services/GTBank/GTBankMerchantService.php` |
| M8 | Ledger | `Services/Ledger/LedgerService.php`, `Services/Ledger/InternalAccounts.php`, `Models/Wallet/LedgerEntry.php` |
| M9 | Payments in | `Services/Payment/PaymentService.php`, `Models/Payment/Payment.php`, `Services/GTBank/GTBankTransactionSyncService.php` |
| M10 | OTP authorization | `Services/Authorization/OtpAuthorizationService.php`, `Models/OtpAuthorization.php` |
| M11 | Transfers | `Services/Transfer/TransferService.php`, `Institution/InstitutionTransferController.php` |
| M12 | Refunds | `Services/Refund/RefundService.php`, `Institution/InstitutionRefundController.php` |
| M13 | Cash-outs | `Services/Cashout/CashoutService.php`, `Services/Cashout/CashoutDisbursementService.php`, `Institution/InstitutionCashoutController.php` |
| M14 | GTBank client | `Services/GTBank/GTBankClient.php` |
| M15 | Inbound webhooks | `Api/PaymentWebhookController.php`, `Api/DisbursementStatusWebhookController.php` |
| M16 | Platform settings | `Models/PlatformSetting.php`, `Models/FeeRateChange.php`, `Settings/PlatformSettingsController.php` |
| M17 | PartnerConnect | `app/Modules/PartnerConnect/**` |
| M18 | Legacy EBM lookup | `Api/EbmController.php` |
| M19 | Reports & dashboards | `DashboardController`, `InstitutionDashboardController`, `InstitutionReportController`, `Settings/FinancialMonitorController` |
| M20 | Observability | `Models/ApiCallLog.php`, `Models/UserActivity.php`, `Models/UserLogin.php`, `Settings/ApiCallLogController.php`, `config/logging.php` (`gtbank` channel) |
| M21 | Notifications | `Notifications/UserNotifications.php`, `Notifications/NewUserCredentialsNotification.php` |

---

## 3. Modules

### M1 Foundation

**BaseModel** (`app/Models/BaseModel.php`) — every domain model except `User`, `UserActivity`, `UserLogin`, `ApiCallLog`, `FeeRateChange`, `PlatformSetting` extends it. It adds:

- **`LogsActivity` trait** — on `created` / `updated` / `deleted` writes a row to `user_activities` with actor, IP, user agent, URL, institution, and `params = {model, model_id, model_uuid, changes}`. `password`, `remember_token`, `otp` are redacted. Each model can narrow `$trackedActions` (e.g. `Payment` logs created+updated; `LedgerEntry`, `Transfer`, `Refund`, `Cashout`, `OtpAuthorization` log only created; `Wallet` and `Institution` log all three).
- **`CustomBuilder`** — overrides `insert()` / `insertOrIgnore()` so bulk inserts are audited too (`bulk_insert` action, records stored in params).

**HasUuid** — fills a public `uuid` on create. The integer `id` stays the PK and is **never** exposed in URLs or APIs; every route uses UUIDs.

**Controller helpers** (`app/Http/Controllers/Controller.php`): `institutionOrFail(uuid)`, `walletOrFail(institution, uuid)` (scopes wallet to institution), `authorizeOrFail(bool)`, `failBack(msg)` (throws a redirect-back with flash error).

**GeneralLibrary::normalizePhoneNumber()** — turns any input into E.164 (`+2507...`) using the country's calling code from `config/countries.php` (default `RW`). All stored phones are E.164 (migration `2026_10_21_100000` normalised old data).

**Convention:** models expose `static _validateAndSave*()` methods that return `['status' => bool, 'message' | 'model']` instead of throwing. Services throw `RuntimeException` with a translated message; controllers catch and flash it.

---

### M2 Authentication & session

Two login doors, one second-factor pipeline.

```
 /login (email+pwd)            /login/institution (TIN + username + pwd)
        │                                   │ institution must exist & be active
        └───────────────┬───────────────────┘
                        ▼
            attemptLogin(): Hash::check(password)
              ├─ is_approved !== 1  → error
              ├─ ban_date set       → error
              └─ OK → session['login_attempt'] = {user_id, timestamp}   (TTL 600 s)
                        │
          ┌─────────────┴──────────────┐
          ▼ 2FA confirmed               ▼ otherwise
  /login/verify-2fa                /login/verify-otp
  TOTP code or recovery code       6-digit email OTP (hashed, 10 min, max 5 tries)
                                   or one-click link ?key=<otp_key> (same browser only)
          │ wrong → failed_login_attempts++ (3 within 24 h → automatic ban)
          ▼ right
   completeLogin(): Auth::login, user_logins row, clearOTP, reset failures,
                    session regenerate, redirect intended('/')
```

- **OTP** is generated by `User::generateOTP()` — stored as a bcrypt hash in `users.otp`, with `otp_expires` (unix ts), `otp_key` (UUID for the magic link), `otp_attempts`. Sent by **email** (`UserNotifications`, mail + database). `config/main.php` says channel `sms` and an `SMSService` (IonPlus) exists in `app/Providers/SMSService.php`, but **nothing calls it** — OTPs only go by email.
- **2FA (TOTP)** is opt-in from `/profile/2fa`: generate secret → scan QR → confirm with a code → 8 single-use recovery codes (encrypted cast). Admins with `update user.account` can reset someone's 2FA.
- **Brute force:** only second-factor failures count toward the ban (3 in a day). A wrong **password** does not increment anything, and `/login` has no throttle.
- **Ban:** `ban_date`/`ban_reason`/`banned_by`. The `app` middleware logs a banned user out on their next request.
- **Temp passwords:** users created by an admin get a random 12-char password by email and `is_temp_password = 1`; the `app` middleware forces them to `/change_password`.
- **First-run registration:** `/register` is only routed while the `users` table is empty; the first user becomes `SuperAdmin`.
- **Password reset:** stock Laravel broker (`password_reset_tokens`).

**`App` middleware** (`app/Http/Middleware/App.php`, alias `app`) runs on every authenticated web route: ban check → locale from session → `version.txt` → if `users.otp` is set redirect to `/otp` → if temp password redirect to change-password.

---

### M3 Access control

- **Permission names** are generated from `config/access.php` as `"{action} {module}.{controller}"`:
  - actions: `list, read, create, update, delete, approve`
  - modules → controllers:
    - `user`: home, access, role, account, monitor
    - `system`: dashboard
    - `settings`: institution, wallet, financial_operations, platform
    - `institution`: user, wallet, merchant_code, payment, transfer, refund, cashout, report, api_credential, profile, settlement_destination
  - e.g. `create institution.cashout`, `update settings.platform`.
- `RoleController::initPermissions()` inserts any missing permissions whenever the roles page is opened (and when the default institution role is ensured).
- **SuperAdmin** passes every check (`Gate::before` in `AuthServiceProvider`).
- **InstitutionAdmin** (`Institution::DEFAULT_ROLE_NAME`) is auto-created with every `* institution.*` permission when the first user of an institution is created.
- **Roles and permissions are global** (Spatie without teams). Tenant isolation is done in code, not by Spatie:

```php
// Institution::_can($permission)
if user can('update settings.institution')  → allowed (platform admin)
if !isMember(user)                          → denied
return user->can($permission)

// Institution::isMember(user)
support_staff_id == user.id                 → member (even if institution inactive)
institution inactive                        → not member
user.institution_id == institution.id       → member
```

- **Institution context** (which institution the sidebar shows) — `Institution::currentForUser()`: from URL (`/institution/{uuid}/…`) → else, for non-platform users, the session `sidebar_institution_uuid` or their own / supported institution. Shared to every view by `AppServiceProvider`'s view composer.
- Note: the PartnerConnect credential screen checks `update settings.institution`, **not** the `institution.api_credential` permission the README mentions — so institution users can never manage their own API credentials.

---

### M4 Users & monitoring

- **Platform accounts** `/users/account` (perm `* user.account`): CRUD, ban/unban (`approve`), reset 2FA. Only a SuperAdmin can assign/edit SuperAdmin. Duplicate phone or email rejected globally; username unique per institution.
- **Institution users** `/institution/{uuid}/users` (perm `* institution.user` via `_can`): same `User::_validatedAndSaveUser()` with `institution_id` forced to the URL's institution. Username is **required** when `institution_id` is set (it's their login name together with the TIN).
- **Profile** `/profile`, `/change_password`, `/profile/2fa/*` for everyone.
- **Monitor** `/users/monitor` (perm `* user.monitor`): last 200 `user_activities` and `user_logins`, filters by email/action/status/date.

---

### M5 Institutions (tenants)

**Table `institutions`** — `uuid, code, name, short_name, tin, type, is_internal, fee_percentage, country, city, address, contact_person, contact_person_phone, contact_person_email, support_staff_id, params, is_active, insert/update/delete_by(_name), timestamps, deleted_at`. Unique `(country,name)`, `(country,tin)`, `(country,code)`.

**Lifecycle**

1. Platform admin creates it at `/settings/institution/edit` (perm `create settings.institution`). Validation: TIN exactly 9 digits, type from `config('main.institution_types')`, contact person/phone/email required, optional `fee_percentage` 0–100, optional support staff (a platform user).
2. `code` auto-generated `INJP-YYYYMMDD-{count+1}`. A soft-deleted institution with the same name/TIN is **revived** instead of duplicated.
3. Optional **first user** in the same form → created with `InstitutionAdmin` role, credentials emailed.
4. `fee_percentage` changes are written to `fee_rate_changes` (scope `institution_fee`).
5. Activate/deactivate (`approve settings.institution`). Inactive ⇒ its users can't log in by TIN, aren't members, and its partner credentials stop issuing/validating tokens.
6. Institution users with `update institution.profile` may edit name/short name/city/address/contact only (`_validateAndSaveProfile` never reads TIN, type, country, fee or support staff from the request).

**Internal institution** — migration `2026_10_13_090100` inserts `"INJONGE-PAY Platform"` (`is_internal = true`, fixed UUID `1028e901-…`) with two wallets: **Bank Cut Account** (`1b661cf6-…`) and **Platform Revenue Account** (`f55bfe7b-…`). They are hidden from institution lists, can't be edited/deleted via the UI, and are resolved by UUID from `config('main.internal_accounts')`.

---

### M6 Settlement destinations

Bank accounts an institution can cash out to. Table `institution_settlement_destinations` (`institution_id, bank_name, account_number, account_name, is_active`, soft deletes). Several per institution; account number unique per institution.

**Every change is OTP-gated** (`SettlementDestinationService`): `initiateCreate / initiateUpdate / initiateToggleActive` → `OtpAuthorization` with `operation_type = settlement_destination` and `payload.action ∈ {create, update, deactivate, reactivate}` → user enters code → `execute()` applies it → `markExecuted`.

The account is **not** validated against GTBank when saved; validation happens at cash-out time (`/disbursement/lookup`), and the cash-out page has a "verify destination" button that calls the same lookup.

---

### M7 Wallets & merchant codes

**Table `wallets`** — `uuid, institution_id, name, merchant_code (unique), merchant_status, gtbank_registration_error, gtbank_registered_name, gtbank_registered_owner, gtbank_business_owner, gtbank_tin, gtbank_contact_email, gtbank_synced_at, gtbank_name_synced_at, gtbank_name_sync_error, notification_phone, available_balance decimal(18,2), is_active, audit cols, deleted_at`.

A wallet = one collection point (branch, till, island). The partner API calls it a **merchant account**.

**Creation** (platform admin only, `create settings.wallet`, at `/settings/institution/{uuid}/wallets/edit`):
- `merchant_code = '555' + 5-digit nextval('wallet_merchant_code_seq')` → e.g. `55500007`. Internal wallets use a separate `internal_wallet_merchant_code_seq` starting at `900001`.
- `merchant_status = PENDING`, `is_active = 1`, balance 0.
- `notification_phone` required for non-internal institutions (it is sent to GTBank as `contactPhone`).
- **Creating a wallet does not call GTBank.** An admin must click **"Retry registration"** on the wallet page (shown for PENDING/FAILED/INACTIVE).

**GTBank merchant lifecycle** (`GTBankMerchantService`):

```
register(wallet):
  lookup merchantId=merchant_code ──found──► applyLookupResult (sync status/name/owner)
        │ not found
        ▼
  KYC complete? (businessName=institution.name, businessOwner, TIN, contactPhone, contactEmail;
                 wallet-level gtbank_* values override institution values)
        │ no  → merchant_status=FAILED, gtbank_registration_error="missing …"
        ▼ yes
  POST /merchants/register {merchantId, …details, channel=participant_name}
        ├─ ok    → merchant_status=PENDING, store details
        └─ error → merchant_status=FAILED, error text saved

checkStatus(wallet)  ("Sync from GTBank"):
  lookup → map ACTIVE/PENDING/CLOSED/INACTIVE → merchant_status
         → is_active = (merchant_status == ACTIVE)          ← mirrors GTBank
         → gtbank_registered_name / owner, gtbank_synced_at
  if registered name ≠ institution name → redirect admin to "Update GTBank name"

updateMerchantDetails(wallet, details):   (wallet edit form)
  if never accepted by GTBank (FAILED) → store locally only
  else POST /merchants/update first; only store if GTBank accepts
```

**A wallet accepts payments only when** `is_active` and (if GTBank is configured) `merchant_status = ACTIVE`.

**Institution-side wallet screens** (`/institution/{uuid}/wallets/...`, perms `* institution.wallet`): list with consolidated balance, wallet dashboard (today/month payment counts & gross totals, pending cash-outs, last 10 ledger/payments/cash-outs), payments (with PartnerConnect confirmation shown per row), payment detail (with matching GTBank API logs), invoice history, ledger + entry detail (resolves the source model), cash-outs, transfers.

---

### M8 Ledger (the money core)

`LedgerService::post()` is **the only place a balance changes**. Nothing else writes `wallets.available_balance`.

```php
LedgerService::post(wallet, 'credit'|'debit', amount, source, description, reference,
                    customerName, customerPhone, sourceModel, status='COMPLETED')
  amount must be > 0
  DB::transaction:
    lock wallet row FOR UPDATE
    newBalance = balance + credit - debit          (bcmath, 2 decimals)
    newBalance < 0  → RuntimeException             (no overdrafts, ever)
    insert wallet_ledger_entries {reference (unique), credit, debit, balance_after,
                                  source, source_type, source_id, insert_by…}
    wallet.available_balance = newBalance
```

- `source` values used: `payment`, `bank_cut`, `platform_revenue`, `transfer_out`, `transfer_in`, `refund`, `cashout`, `cashout_reversal`.
- `source_type/source_id` is a polymorphic pointer to the Payment / Transfer / Refund / Cashout.
- Reference defaults to `LED-YYYYMMDDHHMMSS-NNNN`; callers usually pass their own (`TRF-…-DR`, `TRF-…-CR`, `CSH-…`, `CSH-…-REV`, `RFD-…`).
- `balance_after` makes every entry a running-balance snapshot.
- **Internal accounts** (`InternalAccounts::bankCutWallet()`, `platformRevenueWallet()`) are ordinary wallets resolved by UUID, so the fee split is just more ledger credits.

**Invariant you can reconcile against:**
`Σ all wallet balances = Σ gross payments − Σ refunds − Σ cash-outs (non-reversed)` — transfers net to zero. There is no automated reconciliation job; this is a manual check.

---

### M9 Payments in

`PaymentService::receive(array $data)` — single entry point, called from three places:

| Caller | `received_via` | `insert_by_name` |
|---|---|---|
| GTBank payment webhook (`POST /api/payments/webhook`) | `WEBHOOK` | `bank webhook` |
| Admin "Sync transactions → Save all" (`GTBankTransactionSyncService::saveAll`) | `MANUAL_SYNC` | admin's name |
| Admin "Record payment" form (`/settings/.../record-payment`) | `WEBHOOK` (default — see §10) | admin's name |

**Steps**

```
1. merchant_code required, amount > 0
2. wallet = by merchant_code (not deleted) → must exist, be active,
   and (GTBank configured) merchant_status == ACTIVE
3. feeRate = institution.fee_percentage
             ?? platform_settings.payment_fee_percentage
             ?? config main.payment.default_fee_percentage (2.0)
   fee  = round(gross × feeRate / 100, 2)
   net  = round(gross − fee, 2)            (must be > 0)
   bankCutRate = platform_settings.bank_cut_percentage ?? config (0.0)
   bankCut  = round(fee × bankCutRate / 100, 2)
   platform = round(fee − bankCut, 2)
4. bank_reference already exists (any wallet) → reject "duplicate"
5. DB::transaction:
     insert buyer_payments {… gross, fee_rate, fee, bank_cut, platform, net,
                            payment_status=AVAILABLE, bank_status=SUCCESS, received_via}
     ledger CREDIT merchant wallet  net        source=payment
     ledger CREDIT bank-cut wallet  bankCut    (if > 0)
     ledger CREDIT platform wallet  platform   (if > 0)
   unique violation 23505 → "duplicate bank reference"
```

**Worked example** — customer pays 10,000 RWF, fee 2 %, bank cut 0 %:
fee 200 → merchant wallet +9,800, bank-cut wallet +0 (skipped), platform wallet +200.

**Stored per payment** (`buyer_payments`): `uuid, wallet_id, institution_id, merchant_code, reference (PAY-…), bank_reference (GTBank transactionId, unique), channel (initiatingPartyName, e.g. MTN), buyer_name (debtorName), buyer_phone (debtorAccountId), value_date, initiating_party_code, gross/fee_rate/fee/bank_cut/platform_revenue/net amounts, payment_status, bank_status, received_via, ledger_entry_id, cashout_id, insert_by(_name), timestamps`.

The fee rate is **frozen on the payment** at receive time; changing fees only affects future payments.

**Backfill / manual sync** (`/settings/institution/{i}/wallets/show/{w}/transactions`): for one day it calls GTBank `/transactions?merchantId&date[&debtorAccountId]`; for a range it calls `/participant/transactions?startDate&endDate` and filters to this merchant code. Each row is flagged saved / not saved. "Save all" re-reads from GTBank (never trusts the browser), skips already-saved and non-SUCCESS rows, and calls `receive()` for the rest. Returns `{saved, skipped, failed}`.

---

### M10 OTP authorization

`OtpAuthorizationService` approves **one specific operation's payload**, separate from the login OTP.

```
request(user, operation_type, payload, description)
   → otp_authorizations {user_id, institution_id (user's), operation_type, payload JSON,
                         description, otp=hash(6 digits), otp_expires=now+10min,
                         otp_attempts=0, status=PENDING}
   → email the code to the user

verify(uuid, code, user)      must be same user, PENDING, not expired, < 5 attempts
   → status=VERIFIED, verified_at

<Service>::execute(auth)      requires VERIFIED && executed_at null
   → performs the operation inside a DB transaction
   → markExecuted(auth)       executed_at = now (replay protection)

resend(auth)                  new code, new 10 min, attempts reset (PENDING only)
```

`operation_type` values: `transfer`, `refund`, `cashout`, `settlement_destination`. The payload is the snapshot that executes — e.g. `{wallet_id, amount, destination_id}` — and it is re-validated at execute time (balance, active destination).

---

### M11 Transfers

Wallet → wallet by **merchant code**, OTP-approved (`create institution.transfer`).

```
create form: enter merchant_code + amount + description
  → resolveDestination(code): exists, active, not the same wallet   (preview step)
  → "confirmed" → TransferService::initiate: amount > 0, source active,
                   amount ≤ source balance → OtpAuthorization(transfer)
verify:  OTP ok → execute():
  DB::transaction, lock both wallets
  wallet_transfers row (TRF-…, COMPLETED)
  ledger DEBIT  source  reference TRF-…-DR  source=transfer_out
  ledger CREDIT dest    reference TRF-…-CR  source=transfer_in
  store from/to ledger entry ids, markExecuted
  email initiator
```

- The destination can be **any active wallet of any institution** (cross-institution transfers are allowed, including the internal wallets).
- Transfers do **not** change `payment_status` of any payment.

---

### M12 Refunds

From a payment's page (`create institution.refund`):

```
initiate(payment, reason): payment_status == AVAILABLE, wallet active,
                            payment.gross_amount ≤ wallet balance → OtpAuthorization(refund)
execute(): lock payment + wallet, re-check refundable
  payment_refunds row (RFD-…, COMPLETED)
  ledger DEBIT wallet gross_amount  source=refund
  payment.payment_status = REFUNDED
  email initiator
```

- The **gross** amount is debited although only **net** was credited — the merchant absorbs the fee; the internal fee wallets are not reversed.
- A refund is **ledger-only**: no GTBank call, no money is sent to the buyer by the system. Paying the buyer back happens outside Injonge Pay.

---

### M13 Cash-outs & disbursement

Move wallet balance to an institution bank account via GTBank (`create institution.cashout`).

```
create form: pick active settlement destination, amount (default = full balance)
  optional "verify destination" → GTBank /disbursement/lookup (shows account holder)
initiate(): wallet active, balance > 0, 0 < amount ≤ balance,
            GTBank configured → /disbursement/lookup(account) must succeed
            → OtpAuthorization(cashout, {wallet_id, amount, destination_id})

verify (OTP ok) → CashoutService::execute():
  ┌ createCashout() — DB::transaction, lock wallet, re-check destination & balance
  │   wallet_cashouts {reference CSH-…, amount, settlement_* snapshot,
  │                    status = PENDING (GTBank configured) | COMPLETED (not configured)}
  │   ledger DEBIT wallet amount  source=cashout
  │   every AVAILABLE payment of this wallet → CASHED_OUT, cashout_id = this
  │   markExecuted
  └ submitDisbursement() (if GTBank configured and no gtbank_status yet)
      POST /disbursement/transfer {reference, beneficiaryAccount, amount (int, rounded),
                                   currency RWF, remarks, initiatorName "<wallet>-<code>"}
        ok        → gtbank_status = response.status (PENDING), gtbank_reference = identifier
        "already exist" → ask /disbursement/status and apply it
        other error → failure_reason = message (cash-out stays PENDING, money already debited)
      email initiator (pending / completed / failed)
```

**Final status** comes from `CashoutDisbursementService::handleStatusUpdate()`, used by **both** the GTBank webhook (`POST /api/gtbank/disbursement-status`) and the manual "Check status" button:

| GTBank status | Effect |
|---|---|
| `ACSC` | cash-out `COMPLETED` |
| `CANC` / `RJCT` | **reverse**: ledger CREDIT back (`CSH-…-REV`, source `cashout_reversal`), its payments back to `AVAILABLE` with `cashout_id = null`, cash-out `FAILED` with `failure_reason` |
| anything else (`PENDING`, …) | store `gtbank_status`, nothing else |

Already-terminal cash-outs ignore redelivered updates. Updates are matched by our `reference` **or** GTBank's `gtbank_reference`; the `cbs_transaction_reference` (core-banking ref, only from `/disbursement/status`) is stored when present.

**Retry after an ambiguous failure** (timeout etc.): the verify page shows "Retry"; `execute()` finds the existing cash-out, asks `/disbursement/status` first, and only resubmits if that's inconclusive (GTBank rejects duplicate references).

---

### M14 GTBank integration layer

`GTBankClient` is pure transport; business decisions live in the callers.

- **Configured?** `GTBANK_BASE_URL` non-empty. When it's empty (your local `.env`), payments don't require ACTIVE merchants, cash-outs complete instantly, and GTBank calls are skipped.
- **Auth:** `POST {base}/auth/token` (form) `grant_type=client_credentials, client_id, client_secret` → token cached under `gtbank_access_token` for `expires_in − 60` s (min 60).
- **Every request:** `Authorization: Bearer`, `X-Participant-Name: {GTBANK_PARTICIPANT_NAME}`, JSON, 15 s timeout, TLS verify with optional custom CA `storage/key/gtbank-ca.pem` (or off via `GTBANK_SSL_VERIFY=false`).
- **Envelope:** `responseCode "00"` = success, `"01"` = not found; any other code without `data` → exception with `responseMessage`. HTTP non-2xx → exception.
- **Logging:** every call (including token calls and failures) → `api_call_logs` (redacted) **and** `storage/logs/gtbank-YYYY-MM-DD.log` (90 days).

| Method | Endpoint | Used by |
|---|---|---|
| POST | `/merchants/register` | wallet registration |
| POST | `/merchants/update` | wallet details / name update |
| GET | `/merchants/lookup?merchantId=` | registration pre-check, status sync |
| POST | `/disbursement/transfer` | cash-out |
| GET | `/disbursement/lookup?identifier=` | validate beneficiary account |
| GET | `/disbursement/status?reference=` | cash-out status check / retry |
| GET | `/transactions?merchantId&date[&debtorAccountId]` | single-day sync |
| GET | `/participant/transactions?startDate&endDate` | range sync |

---

### M15 Inbound webhooks

Both live in `routes/api.php` (prefix `/api`, `api` middleware: force JSON, 60 req/min throttle).

**Shared auth:** header `X-GTBank-Webhook-Secret` compared with `hash_equals` against `platform_settings.gtbank_webhook_secret` (fallback env `GTBANK_WEBHOOK_SECRET`). The check can be **switched off** from Platform Settings (`gtbank_webhook_secret_check_enabled = '0'`) — then any request is accepted (logged as a warning).

**`POST /api/payments/webhook`**

| Field | Rule | Stored as |
|---|---|---|
| `transactionId` | required | `bank_reference` |
| `merchantId` | required | resolves wallet by `merchant_code` |
| `amount` | required, ≥ 0.01 | `gross_amount` |
| `valueDate` | date | `value_date` |
| `status` | optional (default SUCCESS) | non-SUCCESS ⇒ 200 "Acknowledged", **ignored** |
| `initiatingPartyName` | | `channel` |
| `initiatingPartyId` | | `initiating_party_code` |
| `debtorName` / `debtorAccountId` | | `buyer_name` / `buyer_phone` |
| `participantName`, `creditorName`, `creditorAccountId` | validated, not stored | |

Responses: `201` with `{reference, wallet_merchant_code, gross_amount, fee_amount, net_amount, status}`; `401` bad secret; `422` validation or business rejection (unknown/inactive wallet, duplicate transactionId); `500` unexpected.

**`POST /api/gtbank/disbursement-status`** — `identifier`, `status` required (+ optional amount, beneficiaryAccount, receiverNames, creditTime, completedAt) → `CashoutDisbursementService::handleStatusUpdate()`. Unknown identifiers are acknowledged with no action.

Every inbound call is written to `api_call_logs` (direction `inbound`, provider `gtbank`, caller = IP).

---

### M16 Platform settings & fee history

- `platform_settings` is a key/value table; a stored non-empty value overrides config. Keys: `payment_fee_percentage`, `bank_cut_percentage`, `gtbank_webhook_secret`, `gtbank_webhook_secret_check_enabled`.
- `/settings/platform` (perm `update settings.platform`): edit global fee % and bank-cut %, **generate** a new 40-char webhook secret (no free-text field), toggle the secret check, shows the webhook URL to give GTBank.
- GTBank API credentials are deliberately **not** editable in the UI — env only.
- Every fee change → append-only `fee_rate_changes` (`scope ∈ global_fee | global_bank_cut | institution_fee`, old/new, who). Viewable at `/settings/fee-rate-history`.

---

### M17 PartnerConnect (POS / EBM partner API)

Self-contained module in `app/Modules/PartnerConnect/`, wired only by `PartnerConnectServiceProvider` (registered in `config/app.php`). It loads its own migrations, routes, views (`partnerconnect::`) and middleware aliases `partner.auth`, `partner.log`.

#### Tables

| Table | Columns |
|---|---|
| `institution_api_credentials` | `uuid, institution_id, client_id (unique, "injp_" + 24 chars), client_secret_hash (bcrypt), is_enabled, last_used_at, audit cols` |
| `institution_api_tokens` | `institution_api_credential_id, institution_id, token_hash (sha256, unique), expires_at, created_at` |
| `partner_payment_confirmations` | `uuid, payment_id, wallet_id, institution_id, status (CONFIRMED / UNCONFIRMED), confirmation_idempotency_key, invoice_no (NOT unique), is_partial, note, invoice_total, balance_after, ebm_receipt_no, sdc_id, confirmed_by, confirmed_by_name, confirmed_at, unconfirmed_by(_name), unconfirmed_at, unconfirm_reason, timestamps` |

(`partner_payment_reservations` was created in v1 and dropped by the `simplify` migration.)

#### Credential management (admin UI)

`/institution/{uuid}/api-credentials` — only users with `update settings.institution` (platform staff). Generate (secret shown **once** via flash), rotate secret (deletes all live tokens), enable/disable (disable deletes live tokens). An institution can have several credentials.

#### Auth flow

```
POST /api/partner/v1/oauth/token   grant_type=client_credentials, client_id, client_secret
  credential enabled + bcrypt match + institution active
  → random 64-char token, store sha256, expires now+3600 s (config main.partner_api.token_ttl_seconds)
  → {token_type: Bearer, access_token, expires_in}

partner.auth middleware on every other route:
  bearer → sha256 lookup, not expired → credential still enabled → institution still active
  → request.attributes.partner_institution = institution   (else 401 unauthorized)
```

#### Endpoints (`/api/partner/v1`, middleware `api` + `partner.log`)

| Method | Path | Behaviour |
|---|---|---|
| POST | `/oauth/token` | see above |
| GET | `/institution` | `{id (uuid), name, tin, status ACTIVE/INACTIVE}` |
| GET | `/merchant-accounts` | all wallets of the institution: `{id, merchant_code, name, provider:"GTBANK", status: merchant_status}` |
| GET | `/merchant-accounts/{id}/payments` | filters `status, amount (exact), date_from, date_to, updated_since, search (phone/name/bank ref), page, per_page (≤200, default 50)`; newest first |
| GET | `/merchant-accounts/{id}/totals?date=Y-m-d` | `{date, count, gross_total, fee_total, net_total}` for payments created that day |
| GET | `/payments/{id}` | one payment |
| POST | `/payments/{id}/confirm` | link to invoice (see below) |
| POST | `/payments/{id}/unconfirm` | undo link; `reason` required, optional `manager{id,name}` |
| GET | `/invoices/{invoice_no}/payments` | all confirmations (CONFIRMED **and** UNCONFIRMED) for an invoice in this institution, oldest first |

All IDs are UUIDs. Every lookup is scoped to the token's institution — another institution's UUID returns `404`.

**Payment shape returned**

```json
{ "id": "<payment uuid>", "amount": 5000.0, "fee_amount": 100.0, "currency": "RWF",
  "network": "MTN", "payer_name": "…", "payer_phone": "…", "provider_reference": "<GTBank transactionId>",
  "received_at": "<created_at ISO-8601 +02:00>", "status": "AVAILABLE|CONFIRMED|REVERSED",
  "invoice_no": null, "is_partial": null, "note": null, "invoice_total": null,
  "balance_after": null, "confirmed_by_name": null, "confirmed_at": null }
```

Status derivation (`PaymentController::paymentPayload`):
`payment_status = REFUNDED → REVERSED` · else active confirmation → `CONFIRMED` · else `AVAILABLE` (this includes `CASHED_OUT` payments).

**confirm** body: `invoice_no` (required), `ebm_receipt_no, sdc_id, partial, note, invoice_total, balance_after, cashier{id,name}, confirmed_at`; header `Idempotency-Key`.

```
DB::transaction, lock payment row
  active CONFIRMED row exists?
    same Idempotency-Key AND same invoice_no → return it (200, safe retry)
    otherwise → 409 {error:{code:"ALREADY_CONFIRMED", message, invoice_no:<existing>}}
  insert CONFIRMED row
```

**unconfirm** → active row becomes `UNCONFIRMED` (kept for audit); `409 conflict` if none.

**Error envelope** (different from the core `ApiController` envelope): `{"error": {"code", "message"}}`. Codes: `invalid_request` 400, `invalid_client` 401, `unauthorized` 401, `not_found` 404, `ALREADY_CONFIRMED` 409, `conflict` 409, `validation_error` 422.

**Logging:** `LogPartnerApiCall` writes every call (including `/oauth/token`) to `api_call_logs` (provider `partner_connect`, caller = institution name or client_id, secrets redacted).

**Inside Injonge Pay**, confirmations are shown on the institution's payments list, payment detail and an invoice-history page.

---

### M18 Legacy EBM lookup API

`GET /api/ebm/payments/lookup?reference=…|bank_reference=…`, header `X-EBM-Api-Key` = env `EBM_API_KEY`. Returns one payment `{reference, bank_reference, merchant_code, buyer_name, buyer_phone, gross/fee/net, payment_status, bank_status, paid_at}` using the core `{success, message, data}` envelope. **Not scoped to an institution** — one shared key can read any payment. Superseded by PartnerConnect.

---

### M19 Reporting & dashboards

| Screen | Who | Data |
|---|---|---|
| `/` global dashboard | `read system.dashboard` | static view; others are redirected to their institution dashboard |
| `/institution/{uuid}/dashboard` | members / platform | wallet count, consolidated balance |
| `/institution/{uuid}/reports` | `list institution.report` | date-filtered totals: payments (count, gross, fees, net), transfers in/out, cash-outs; consolidated balance; last 50 mixed history rows |
| `/settings/financial-operations` | `list settings.financial_operations` | per institution: balance, payments total/count, fee/bank-cut/platform totals, transfers in/out, cash-outs; plus internal wallet balances |
| `/settings/financial-operations/{uuid}` | same | last 200 transfers and cash-outs with OTP authorization info |
| `/settings/wallets` | `list settings.wallet` | all non-internal wallets, search by name/code/institution |
| Wallet dashboard | wallet perms | today / this month payment count & gross, pending cash-outs |

All reports are live SQL aggregates on `created_at`; there are no materialised tables.

---

### M20 Observability

| Store | What | Viewer |
|---|---|---|
| `user_activities` | every create/update/delete on audited models (actor, IP, UA, URL, diff) | `/users/monitor` |
| `user_logins` | successful logins (IP, UA, `otp_source` email/totp, logout time) | `/users/monitor` |
| `api_call_logs` | every GTBank outbound call, GTBank inbound webhook, PartnerConnect inbound call (redacted payloads, status, duration) | `/settings/api-call-logs` (perm `read settings.platform`); also embedded on payment and cash-out detail pages (matched by reference) |
| `fee_rate_changes` | fee history | `/settings/fee-rate-history` |
| `storage/logs/gtbank-*.log` | GTBank traffic, 90-day rotation | server |
| `notifications` | database copy of every `UserNotifications` | (no UI found) |

Redacted keys: `client_secret, access_token, password, otp, authorization, x-gtbank-webhook-secret`.

---

### M21 Notifications

- `UserNotifications(subject, message, url, button)` → **mail + database**. Used for login OTP, financial OTP, transfer/refund completion, cash-out pending/completed/failed.
- `NewUserCredentialsNotification(password)` → mail with TIN + username (institution users) or email (platform users) and the temp password.
- `User::notify()` retries twice (1 s, 3 s) on SMTP transport errors; `notifySafely()` never lets a failed email break the operation.

---

## 4. End-to-end data flows

### 4.1 Onboarding a merchant

```
Platform admin                         Injonge Pay                                GTBank
──────────────                         ───────────                                ──────
create institution (TIN, KYC) ───────► institutions row, code INJP-…
  + first user ──────────────────────► users row (InstitutionAdmin), temp pwd email
create wallet ───────────────────────► wallets row, merchant_code 555xxxxx, PENDING
"Retry registration" ────────────────► lookup ──────────────────────────────────► /merchants/lookup
                                       register ────────────────────────────────► /merchants/register
"Sync from GTBank" (later) ──────────► lookup → ACTIVE, is_active=1 ◄──────────── status ACTIVE
institution user: add settlement destination (OTP) ► institution_settlement_destinations
platform admin: generate API credential ─► institution_api_credentials (secret shown once → give to POS)
platform admin: give GTBank webhook URL + secret (Platform settings)
```

### 4.2 Life of one payment (with POS)

```
Customer ─ pays 10,000 to 55500007 ─► MoMo/GTBank
GTBank ── POST /api/payments/webhook ──► PaymentService::receive
            buyer_payments: PAY-…, gross 10000, fee 200, net 9800, AVAILABLE
            ledger: wallet +9800, platform wallet +200
POS ───── GET  /merchant-accounts/{w}/payments?status=AVAILABLE&amount=10000 ─► sees it
POS ───── (signs EBM invoice INV-42 as "eKash unconfirmed")
POS ───── POST /payments/{p}/confirm {invoice_no: INV-42, cashier…} ─► partner_payment_confirmations CONFIRMED
          (buyer_payments untouched; payment_status still AVAILABLE)
Later, institution user cashes out ─► wallet −9800, payment → CASHED_OUT
          (POS still sees it as CONFIRMED via GET /payments/{p}; but see §11 on list filters)
```

### 4.3 Money conservation across operations

| Operation | Merchant wallet | Other wallet | Internal wallets | `payment_status` change | External effect |
|---|---|---|---|---|---|
| Payment received | +net | — | +bankCut, +platform | → AVAILABLE | money already at GTBank |
| Transfer | −amount | +amount | — | none | none |
| Refund | −gross | — | none | AVAILABLE → REFUNDED | **none** (manual payback) |
| Cash-out | −amount | — | — | all AVAILABLE → CASHED_OUT | GTBank disbursement to bank |
| Cash-out reversed | +amount | — | — | CASHED_OUT → AVAILABLE | — |
| POS confirm/unconfirm | — | — | — | none | confirmation row only |

---

## 5. State machines

**`buyer_payments.payment_status`**
```
            refund (OTP)
AVAILABLE ───────────────► REFUNDED          (terminal)
    │  ▲
    │  │ cash-out CANC/RJCT (reversal)
    ▼  │
CASHED_OUT
```
`bank_status` is always `SUCCESS` today (`PENDING/FAILED/REVERSED` constants exist but nothing sets them).

**`partner_payment_confirmations.status`** (one active row per payment)
```
(none) ──confirm──► CONFIRMED ──unconfirm──► UNCONFIRMED (history)   → payment can be confirmed again
```

**`wallets.merchant_status`**
```
PENDING ──register ok──► PENDING ──sync──► ACTIVE / INACTIVE / CLOSED
   │                                         ▲
   └──register error / missing KYC──► FAILED ┘ (retry)
is_active is forced to (merchant_status == ACTIVE) on every sync
```

**`wallet_cashouts.status`** (+ `gtbank_status`)
```
COMPLETED                         (GTBank not configured)
PENDING ──ACSC──► COMPLETED
   └────CANC/RJCT──► FAILED (+ ledger reversal)
PENDING + failure_reason, gtbank_status NULL  = ambiguous submit → "Retry"
```

**`otp_authorizations.status`**
```
PENDING ──verify──► VERIFIED ──execute──► (executed_at set)
(CANCELLED constant exists, nothing sets it; expiry is time-based, not a status)
```

---

## 6. Data model

```
users ──(institution_id)──► institutions ◄──(support_staff_id)── users
                               │ 1
        ┌──────────────────────┼─────────────────────────────┬─────────────────────────────┐
        ▼ n                    ▼ n                           ▼ n                           ▼ n
     wallets      institution_settlement_destinations   institution_api_credentials   fee_rate_changes
        │ 1                                                  │ 1
        ├─► wallet_ledger_entries (source_type/source_id ─► payment|transfer|refund|cashout)
        ├─► buyer_payments ──► payment_refunds
        │        │  └─(cashout_id)─► wallet_cashouts
        │        └─► partner_payment_confirmations
        ├─► wallet_transfers (from_wallet_id / to_wallet_id)
        └─► wallet_cashouts                          institution_api_tokens ◄─┘
otp_authorizations ◄── transfers / refunds / cashouts (otp_authorization_id)
user_activities, user_logins, api_call_logs, platform_settings, notifications,
roles / permissions / model_has_roles / role_has_permissions (Spatie), cache, cache_locks,
password_reset_tokens, personal_access_tokens, failed_jobs
```

Money columns are `decimal(18,2)`; rates are `decimal(5,2)`. Foreign keys mostly `cascade` on wallet/institution and `set null` on users and ledger links. `wallets`, `institutions`, `users`, `institution_settlement_destinations` are soft-deleted.

Key uniqueness: `wallets.merchant_code`, `buyer_payments.reference`, `buyer_payments.bank_reference`, `wallet_ledger_entries.reference`, `wallet_transfers.reference`, `payment_refunds.reference`, `wallet_cashouts.reference`, `(institution_id, username)` on users, `users.phone_number`, `users.email`.

---

## 7. Reference & identifier formats

| Thing | Format | Example |
|---|---|---|
| Institution code | `INJP-YYYYMMDD-N` | `INJP-20261013-4` |
| Merchant code (tenant) | `555` + 5 digits (Postgres sequence) | `55500007` |
| Merchant code (internal) | 900001+ | `900002` |
| Payment | `PAY-YYYYMMDDHHMMSS-NNNN` | `PAY-20261103101530-4821` |
| Ledger entry (default) | `LED-…` | |
| Transfer | `TRF-…`, legs `TRF-…-DR` / `-CR` | |
| Refund | `RFD-…` | |
| Cash-out | `CSH-…`, reversal `CSH-…-REV` | |
| Partner client id | `injp_` + 24 lowercase alnum | |
| Partner token | 64 random chars (sha256 stored) | |
| Public IDs | UUID v4 everywhere | |

---

## 8. Route inventory

**Guest:** `/login` (GET/POST), `/login/institution`, `/otp`, `/login/verify-otp` (GET/POST), `/login/resend-otp`, `/login/verify-2fa` (GET/POST), `/forgot-password`, `/reset-password/{token}`, `/reset-password`; `/register` only while no users exist.

**Authenticated – self:** `/`, `/home`, `/profile`, `/change_password`, `/profile/2fa`, `/profile/2fa/{enable,confirm,disable,recovery-codes/regenerate}`, `/logout`.

**Authenticated – platform:** `/users/role/*`, `/users/account/*`, `/users/monitor/*`, `/settings/financial-operations[/{uuid}]`, `/settings/fee-rate-history`, `/settings/api-call-logs[/{uuid}]`, `/settings/platform` (+ webhook secret generate/toggle), `/settings/wallets`, `/settings/institution/*`, `/settings/institution/{uuid}/wallets/*` (show, transactions, save-all, record-payment, edit, status, delete, gtbank retry / check-status / update-name).

**Authenticated – institution workspace** (`/institution/{uuid}/…`): `dashboard`, `details`, `edit`, `users/*`, `reports`, `wallets/*` (show, payments, payment detail, invoice history, ledger, ledger entry, cash-outs list, transfers list), `wallets/{w}/transfer[/verify/{auth}]`, `wallets/{w}/payments/{p}/refund[/verify/{auth}]`, `wallets/{w}/cashout[/verify/{auth}]`, `wallets/{w}/cashout/{c}[/check-status]`, `settlement-destinations/*`, `api-credentials/*`.

**API (`/api`):** `GET /user` (Sanctum, unused), `POST /payments/webhook`, `GET /ebm/payments/lookup`, `POST /gtbank/disbursement-status`.

**Partner API (`/api/partner/v1`):** see M17.

---

## 9. Configuration & environment

| Key | Where | Meaning |
|---|---|---|
| `GTBANK_BASE_URL`, `GTBANK_CLIENT_ID`, `GTBANK_CLIENT_SECRET`, `GTBANK_PARTICIPANT_NAME`, `GTBANK_SSL_VERIFY` | `config/services.php` | GTBank connection; empty base URL = "not configured" mode |
| `GTBANK_WEBHOOK_SECRET` | same | fallback webhook secret (DB value wins) |
| `EBM_API_KEY` | same | legacy EBM lookup key |
| `IONPLUS_SMS_*` | same | SMS gateway (unused) |
| `INTERNAL_INSTITUTION_UUID`, `INTERNAL_BANK_CUT_WALLET_UUID`, `INTERNAL_PLATFORM_REVENUE_WALLET_UUID` | `config/main.php` | internal accounts (defaults match the migration) |
| `main.payment.default_fee_percentage` | `config/main.php` | 2.0 |
| `main.payment.default_bank_cut_percentage` | same | 0.0 |
| `main.partner_api.token_ttl_seconds` | same | 3600 |
| `main.institution_types` | same | school, university, bus_owner, hospital, … other |
| `APP_TIMEZONE` | `.env` | `Africa/Kigali` |
| `storage/key/gtbank-ca.pem` | file | optional custom CA for GTBank TLS |
| API rate limit | `RouteServiceProvider` | 60/min keyed by user id, else IP |

---

## 10. Findings: gaps, bugs, risks

Ordered by how much they matter. **★ = affects the InjongePay / POS integration directly.**

### High

1. **★ Cash-outs hide payments from the partner list filters.** A cash-out flips **every** `AVAILABLE` payment of the wallet to `CASHED_OUT` (`CashoutService::createCashout`). `PartnerPaymentService::listPayments` maps `status=AVAILABLE` and `status=CONFIRMED` to `payment_status = 'AVAILABLE'`. So after any cash-out, payments the POS hasn't matched yet **disappear** from `?status=AVAILABLE`, and already-confirmed ones disappear from `?status=CONFIRMED`. Without a status filter they still come back (a cashed-out, unconfirmed payment shows `status: "AVAILABLE"`). Fix: base AVAILABLE/CONFIRMED on `payment_status IN (AVAILABLE, CASHED_OUT)`.
2. **★ `updated_since` misses confirm/unconfirm.** The module never writes `buyer_payments`, so confirming a payment doesn't change its `updated_at`. A second POS terminal polling with `updated_since` won't see that another terminal confirmed it. API.md promises the opposite.
3. **★ `confirm` doesn't check the payment's state.** A `REFUNDED` payment can still be confirmed against an invoice. And (README "Open decision") a payment already confirmed in the POS can still be refunded inside Injonge Pay.
4. **Admin "Record payment" creates real balance without bank proof** and labels it `received_via = WEBHOOK` (`WalletController::recordPayment` doesn't pass `received_via`; `bank_reference` is optional). Anyone with `create settings.wallet` can mint balance that can then be cashed out to a bank account.
5. **Webhook secret check can be disabled from the UI.** With it off, anyone who can reach `/api/payments/webhook` can credit any active wallet.
6. **Refunds move no money.** They debit the merchant wallet (gross) but nothing is sent to the buyer and the fee isn't reversed. If that's intended, it should be documented for merchants; otherwise a GTBank reversal/disbursement is missing.

### Medium

7. **Cash-out amount rounding.** Ledger debits `amount` with 2 decimals; GTBank is sent `(int) round(amount)`. 9,800.50 → bank sends 9,801. Fees at 2 % routinely create cents.
8. **Partial cash-out marks all payments `CASHED_OUT`.** Cashing out 1,000 of a 50,000 balance flags every available payment, which also blocks refunding any of them.
9. **★ Partner rate limit is per IP, not per token.** The `api` limiter keys on `$request->user()?->id ?: ip`; partner calls have no Laravel user, so all POS terminals behind one NAT share 60 req/min.
10. **Duplicate webhook → 422.** A redelivered GTBank webhook for an already-saved `transactionId` gets a 422 error instead of an idempotent 200, which may make GTBank keep retrying or raise alerts.
11. **Bank-side reversals are ignored.** Non-SUCCESS webhooks are acknowledged and dropped; `bank_status` is never anything but `SUCCESS`.
12. **New wallets aren't registered with GTBank automatically** and show `PENDING` (a status that looks like "waiting for GTBank") although nothing was sent. `isRegisteredWithGtbank()` treats that unsent `PENDING` as registered, so editing GTBank details on such a wallet calls `/merchants/update` for a merchant GTBank doesn't know.
13. **Login OTP redirect loop.** If a logged-in user starts a new login elsewhere, `users.otp` gets set; the first session's `app` middleware redirects to `/otp` (guest-only) → guest middleware redirects to `/home` → `app` → `/otp`… until the other login finishes.
14. **No password brute-force protection.** Wrong passwords are not counted and `/login` isn't throttled.
15. **Cached GTBank token isn't refreshed on 401.** If GTBank revokes a token early, calls fail until the cache entry expires.
16. **Legacy EBM lookup is global** — one key reads any institution's payments.

### Low / housekeeping

17. `institution_api_tokens` rows are never cleaned up (one per `/oauth/token` call).
18. `Institution::generateCode()` uses `count()+1` — not concurrency-safe and can collide after deletes (there's a unique index, so it would error).
19. `received_at` in the partner API is our `created_at`, not GTBank's `valueDate` — manually synced payments show the sync time.
20. Wallet balance updates are audited (`Wallet` tracks `updated`), so each payment writes 4–8 `user_activities` rows (payment created + updated, one ledger entry and one wallet update per credited wallet); that table will grow fast.
21. README says the credential screen uses permission `institution.api_credential`; the code checks `update settings.institution`.
22. Unused: `SMSService`, `OtpAuthorization::STATUS_CANCELLED`, `Payment::BANK_STATUS_*` except SUCCESS, `config/custom.php`, Sanctum route; no tests; no scheduler (expired OTPs/tokens are just left in place).

---

## 11. Building the InjongePay client module

What a POS-side `InjongePay` module has to deal with, based on the live code (not `injonge-emb.md`).

### 11.1 The contract changed — drop reserve/release

`injonge-emb.md` §2 describes `reserve → confirm / release` with `reservation_token` and TTLs. That was v1 and has been **removed**. The live flow is:

1. Sign the EBM invoice immediately (payment marked "eKash: unconfirmed" on your side).
2. Pick the matching incoming payment.
3. `POST /payments/{id}/confirm` with `invoice_no` (+ `Idempotency-Key`).
4. If the sale is voided / the payment line is deleted in the POS: `POST /payments/{id}/unconfirm` with a `reason` (API.md asks you to do this automatically, e.g. `"Deleted in POS"`).

So your local link table needs statuses like `confirmed`, `confirm_pending` (call failed, retry), `unconfirmed`, `failed` — not `reserved`/`released`. Drop `reservation_token`, `reservation_ttl_seconds`.

### 11.2 Endpoints to wrap in your client

| Client method | Call |
|---|---|
| `token()` | `POST /oauth/token` (form-encoded), cache ~55 min, on any 401 re-fetch once and retry |
| `testConnection()` | `GET /institution` |
| `merchantAccounts()` | `GET /merchant-accounts` → link to local payment accounts by `id` (UUID); show `merchant_code` and `status` |
| `payments(account, filters)` | `GET /merchant-accounts/{id}/payments` |
| `payment(id)` | `GET /payments/{id}` |
| `confirm(id, invoice, …)` | `POST /payments/{id}/confirm` |
| `unconfirm(id, reason, manager)` | `POST /payments/{id}/unconfirm` |
| `invoicePayments(invoice_no)` | `GET /invoices/{invoice_no}/payments` (filter `status == CONFIRMED` yourself) |
| `dailyTotals(account, date)` | `GET /merchant-accounts/{id}/totals?date=` (end-of-day reconciliation) |

Base URL: `https://<host>/api/partner/v1`. Errors: `{"error":{"code","message"}}`.

### 11.3 Handling the sharp edges (until §10 items 1–3 and 9 are fixed server-side)

- **Don't rely only on `status=AVAILABLE`.** After the merchant cashes out, unmatched payments vanish from that filter. Either fetch without `status` and filter `status == "AVAILABLE"` client-side, or combine `date_from` + no status filter for the checkout popup.
- **Use `amount=` (exact match) + a recent `date_from`** to keep the checkout list short; `search` matches payer phone, name or GTBank reference across all time.
- **Don't trust `updated_since` for confirmation changes.** Refresh a payment with `GET /payments/{id}` right before you confirm, and treat a `409 ALREADY_CONFIRMED` as normal: if its `invoice_no` is yours, your earlier attempt succeeded — mark it confirmed; otherwise another invoice took it — tell the cashier.
- **Always send `Idempotency-Key`** (e.g. `pos:<business_id>:<invoice_no>:<payment_id>`). The server returns the existing confirmation only when **both** key and `invoice_no` match.
- **Partial payments:** call `confirm` once per payment with the same `invoice_no`, `partial: true`, `invoice_total`, `balance_after`, `note`. Rebuild an invoice's history with `GET /invoices/{invoice_no}/payments`.
- **Check `REVERSED` yourself** before confirming (the server will let you confirm a refunded payment).
- **Rate limit:** 60 requests/min **per public IP** shared by every terminal of the business. Poll per merchant account at a modest interval and back off on `429`.
- **Credentials:** issued per institution by Injonge Pay platform staff (not self-service). The secret is shown once. Rotation or disabling kills live tokens immediately → expect a 401 and re-authenticate; if re-auth fails with `invalid_client`, surface "credentials revoked".
- **Merchant account status:** only `ACTIVE` accounts can receive new payments; `PENDING/FAILED/INACTIVE/CLOSED` can't. Show it next to the linked account.
- **Amounts** are floats in JSON, RWF, 2 decimals; `amount` is gross (what the customer paid); `fee_amount` is Injonge Pay's fee. Book the gross on the invoice.
- **No sandbox:** API.md §8 — the partner API runs against production. Test payments come from the admin "Record payment" action (see §10 item 4 for why that action should be restricted).
- **No push:** Injonge Pay never calls the POS. Everything is pull.

### 11.4 Suggested local tables (POS side)

| Table | Key columns |
|---|---|
| `injonge_institutions` | `business_id` (unique), `is_enabled`, `client_id`, `client_secret` (encrypted), `remote_institution_id`, `name`, `tin`, `status`, `last_verified_at` |
| `injonge_account_links` | `business_id`, `account_id` (unique), `remote_merchant_account_id` (UUID), `merchant_code`, `remote_name`, `provider`, `remote_status`, `synced_at` |
| `injonge_payment_links` | `business_id`, `remote_payment_id` (unique), `account_id`, `transaction_id`, `transaction_payment_id`, `invoice_no`, `amount`, `payer_name`, `payer_phone`, `provider_reference`, `network`, `is_partial`, `idempotency_key`, `status` (confirmed / confirm_pending / unconfirmed / failed), `confirmed_by`, `confirmed_at`, `last_error`, `raw` (json) |

### 11.5 Server-side fixes worth asking for before go-live

1. Treat `CASHED_OUT` as AVAILABLE/CONFIRMED in the partner list filters (§10.1).
2. Touch `buyer_payments.updated_at` (or filter `updated_since` on confirmations too) when confirming/unconfirming (§10.2).
3. Reject `confirm` on `REFUNDED` payments; block refunds of POS-confirmed payments (§10.3).
4. Key the partner rate limiter on the token/credential (§10.9).
5. Return the REVERSED status for bank-side reversals once GTBank sends them (§10.11).
