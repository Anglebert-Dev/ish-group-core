# Injonge Pay on ISH Core: Build Guide

Created: 03/11/2026, updated 04/11/2026 to match what is built. The plan for rebuilding Injonge Pay (in production at `~/Documents/Inhills/INJONGE-PAY`) as two ISH Core modules: `InjongePay` (the money core) and `InjongePayConnect` (partner integrations). What exists today is described in [injonge-pay-module.md](injonge-pay-module.md). GTBank reference: `~/Documents/Inhills/Docs/GT_PaymentBridge_API_Documentation_v2.5.pdf`.

Progress is tracked in the phase table at the end (✅ = built and tested).

## 1. Context

Injonge Pay is in production in `~/Documents/Inhills/INJONGE-PAY` (branch `refactor/code-quality`; analysis in `docs/injonge-pay-module.md`). It connects to GTBank PaymentBridge, collects mobile-money payments into merchant codes, splits fees, keeps wallet balances, cashes out to bank accounts and serves a POS/EBM partner API. Its logic is wrong in many places, and merchant management, transactions and cash-outs in particular are unstable. We rebuild it on ISH Core as a normal **sellable** module, using the core pieces we trust. Inhills installs core plus this module and runs it themselves; no link to our server is needed. A **database migration** from production follows as its own phase, so every table is designed for it.

Decided by the user:
- **Businesses** are the customers. The word "institution" is not used anywhere in the new code; a business's **type** (school, hospital, fuel station, … 53 kinds) is a core field on every Business (`config('core.business_types')`).
- **A business does everything itself except create merchant codes.** It manages its own users and roles, settlement accounts, POS connections, transfers, cash-outs and reports. Inhills creates the merchant codes (they are registered at GTBank) and sets each business's KYC, fee and limits.
- **Inhills helps a business with "Log in as"** (core impersonation), never from platform pages that reach into a business. Money actions (cash-outs, transfers, PIN, settlement accounts, POS secrets) are blocked while logged in as someone.
- **Data isolation:** a business never sees or reaches another business's data. Transfers only go between a business's own merchant accounts.
- External APIs stay **identical**.
- **Refunds and manual "Record payment" are dropped.**
- Audit **everything**.
- **Sessions log out after 2 minutes idle.**
- The partner API becomes a **sub-module**, because more integrators with different needs will come.

## What is wrong in production (each one is designed out below)

Money and cash-out:
1. A cash-out is sent to GTBank **inside the web request** right after the OTP. A timeout leaves it `PENDING` with a `failure_reason`, and the state is then guessed from string fields: `gtbank_status` null, "already exist" matched by text (`CashoutService::submitDisbursement`).
2. **Business users can reverse a cash-out** (`InstitutionCashoutController::reverse`). Crediting money back is in tenant hands.
3. Status handling runs on the unlocked row: `markCompleted` doesn't re-check status under its lock, and the terminal check sits outside it. The webhook's amount and account are never compared with our cash-out.
4. `(int) round(amount)` is sent to the bank while 2 decimals are debited. Floats (`float $amount`) are used across Ledger, Payment and Cashout.

Payments in:

5. A webhook for an unknown, pending or inactive wallet returns **422 and the money is left uncredited** at GTBank until someone clicks "sync".
6. References are `PREFIX-YmdHis-rand(1000..9999)` and can collide under load. `PaymentService` reports **any** `23505` (even a ledger-reference collision) as "duplicate bank reference", so the webhook returns 422 and **a real payment is dropped**.
7. The duplicate check runs before and outside the transaction.
8. Every payment locks the same 2 internal wallets, which serialises all payments across the platform.

Merchant management:

9. A network error on lookup marks the wallet **FAILED**.
10. The sync overwrites the admin's `is_active`, so one column means two things.
11. "PENDING" is shown even when nothing was sent.
12. Wallet on/off is a **GET** request.
13. Business logic sits in controllers and static services, and the cash-out controller calls GTBank directly.

Partner API:

14. `CONFIRMED`/`AVAILABLE` filtering loads every confirmed id into `whereIn`.
15. CASHED_OUT payments vanish from the filters.
16. `updated_since` misses confirmations.
17. Rate limits are per IP.
18. Tokens are never cleaned up.
19. A credential is bound to one fixed contract, so a new integrator with different needs means editing the core.

## Principle 1: our books = GTBank (the main goal)

All customer money physically sits in Inhills' **participant settlement account** at GTBank. Our wallets only divide that money up. So:

- **The bank is the source of truth.** Each ledger movement that touches the outside world points to exactly one bank fact: a payment points to a GTBank `transactionId` with SUCCESS, a cash-out to a GTBank disbursement `reference` with ACSC. Transfers and fee splits move money between our own wallets only and must net to zero.
- **Bank mirror.** `ijp_bank_transactions` holds every transaction GTBank reports, from the webhook and from a scheduled pull of `/transactions?merchantId&startDate&endDate` with date-time windows. `ijp_bank_disbursements` does the same for disbursements, from `/disbursement/status` and the webhook. A **matcher** links each bank row to our record. Its outcomes:
  - matched
  - missing in ours: ingested automatically
  - missing at bank: flagged and frozen
  - amount, merchant or beneficiary mismatch: alert, nothing posted
  - reversed at bank: we post the reversal
- **Pending vs available, as Payoneer does it.** A webhook credit first lands as **pending**. It becomes **available**, and so can be cashed out, only once the bank pull confirms the same `transactionId`, amount and merchant. A forged or mistaken webhook can never be withdrawn.
- **Cash-outs are final only after two signals**: the webhook *and* a `/disbursement/status` ACSC with a matching amount and account.
- **Daily close** per merchant and day: bank count and sum = ours. A day is "reconciled" only when everything matches, and the open items appear on a platform screen. Global identity: Σ wallet balances (pending + available, internal wallets included) + cash-outs in flight = Σ bank credits − Σ bank ACSC disbursements.
- GTBank has **no balance or statement API**. Ask them for one. Until then, platform staff upload the settlement-account statement (CSV) and a monthly check compares it.

## Principle 2: authorization like MoMo / Payoneer, not an OTP every time

The current pattern (an emailed OTP on every action) is slow and doesn't actually measure risk. Instead:

- **Transaction PIN.** Each staff user who can move money sets a 5-digit PIN. It is hashed, locks after 3 wrong tries, a reset needs MFA, and every attempt is audited. A normal transfer or cash-out needs the PIN only, as MoMo does.
- **Settlement accounts.** The business adds its own (with its PIN). It can be used as soon as GTBank's `/disbursement/lookup` returns the same holder name (word order and case don't matter). There is no platform approval and no waiting time; the first cash-out to a new account asks for the extra code instead.
- **Risk-based step-up** (TOTP through `StaffMfaService::verify()`, or an emailed code if the user has no MFA) is added only when:
  - the device is new
  - the amount is above the business's step-up threshold
  - it is the first cash-out to this account
  - velocity is unusual (count or sum per hour)
  - the PIN was recently reset
- **Limits** set by Inhills per business: per-transaction, daily and monthly cash-out limits. **Maker-checker** above an approval threshold: a second business user approves with their own PIN.
- **Trusted devices**: a signed device cookie, registered after a step-up and listed so it can be revoked.
- Every PIN set, wrong PIN, lock, trusted browser, account change and approval is audited.

## GTBank v2.5 doc vs production code (each one fixed in `GtbankClient` / services)

| # | Doc v2.5 says | Production code does | Rebuild |
|---|---|---|---|
| 1 | Disbursement `reference`: alphanumeric, max 35 | `CSH-YYYYmmddHHMMSS-NNNN` (hyphens) | `CSH` + digits, validated before sending |
| 2 | `amount` is a Long in the smallest unit | `(int) round(float)` after a 2-dp debit | whole RWF end to end |
| 3 | Duplicate = HTTP **409** (code 13/07). The 409 message differs per endpoint ("Request Validation failed" on merchant payments) | matches the text "already exist" | branch on HTTP status and code, never on text |
| 4 | Error codes 00–15, 96 | knows only 00 and 01 | full map: 08/09 → refresh token and retry once; 10 insufficient funds, 14 bad beneficiary, 05 → definitely failed; 12 timeout, 96, 5xx, network → **unknown** |
| 5 | Status response is snake_case (`request_id, document, cbs_transaction_reference, receiver_id, receiver_names, sender_id, bank_code`). Not found = `{"error":"Transaction not found…"}` | reads only `status` and `document`; a not-found becomes an exception, then a blind resubmit | typed `NotFound`; recheck with backoff during a grace period; after that platform staff decide; never resubmit blind |
| 6 | Disbursement webhook: `identifier, status, amount, currency, beneficiaryAccount, receiverNames, creditTime, completedAt` | amount and account never compared | compared with the cash-out; a mismatch raises an alert and is not applied |
| 7 | Transaction statuses SUCCESS / PENDING / FAILED / **REVERSED** / CANCELLED | everything except SUCCESS is dropped | REVERSED → debit back (or hold if already cashed out); PENDING → mirror only, credit later on SUCCESS |
| 8 | Webhook: answer **200 within 5 s, idempotent** | duplicate or inactive wallet → 422; ledger work done inside the request | store the raw event in an inbox, answer quickly, process it on the queue; a duplicate gets 200 |
| 9 | Merchant update and close are **asynchronous** (`status: PENDING`) | update treated as applied at once; `/merchants/close` never called (a deleted wallet stays open at the bank) | `update_pending` until a lookup confirms; closing a wallet calls `/merchants/close` |
| 10 | Register field is `tin` in the table, `taxIdentificationNumber` in the sample. Lookup returns `businessOwnerName` (doc) or `businessOwner` (logs) | sends `taxIdentificationNumber`, reads one shape | tolerant parser with every variant mapped in one place; confirm with GTBank and the production call logs |
| 11 | Lookup must return the requested merchant | never checks (local log: asked 55500002, got 55500006) | refuse a lookup whose `merchantId` ≠ the one requested |
| 12 | Merchant transactions supports `startDate`/`endDate` date-times per merchant | pulls the whole participant and filters in PHP | per-merchant windowed pulls for the mirror |
| 13 | Errors are `{"error": "…"}` (doc); logs also show `{"message": "…"}` | reads both | keep both |
| 14 | `remarks` ≤ 50, `beneficiaryAccount` ≤ 20, phone `+250XXXXXXXXX`, merchantId starts with 55 and has 8–12 digits | not enforced | validated before any call |
| 15 | Webhook signature "if supported"; there is no secret header in the doc | our own `X-GTBank-Webhook-Secret` | keep the header (it is already agreed) plus an optional IP allow-list; ask GTBank about signing |

### What GTBank really answers (production log, 03/11/2026), not what the document says

The document is not reliable on responses and codes, so the client acts on what GTBank actually answers. The real answers are fixtures in `Modules/InjongePay/tests/fixtures/gtbank-production.php`, with tests in `GtbankRealAnswersTest`.

- **PaymentBridge is a Java/Spring service in front of DigiPay** (`com.example.mpayhub`, `api.gtbank.rw/digipay/...`). When DigiPay answers badly, the bridge returns **HTTP 500** and puts DigiPay's real status and body inside its `error` text. `Gtbank/JavaError.php` reads every shape seen:
  - Spring RestTemplate: `400 on POST request for "…": "{…}"` and `401 … [no body]`
  - Jackson: `Error while extracting response for type …`
  - I/O errors: `Read timed out`, `Connection refused`
  - Spring Boot's default error JSON

  The outcome follows what DigiPay said. Example: a status check that comes back as 500 wrapping DigiPay's 400 "Transaction not found" is read as **not found**.
- **A timeout is not a failure.** `/disbursement/transfer` timed out at 15 s, and 30 s later its status was **ACSC**. Transfers now wait 60 s, and a write that gets no clear answer is always "unknown" until a status check settles it.
- **DigiPay refusing GTBank's own login** (`401 … [no body]` on `/pay`) means the transfer was never created; the status check then says not found. The client reports it as unknown, explains it in plain words ("tell GTBank"), and leaves the status check to decide.
- **Wallet numbers must be sent as `07XXXXXXXX`.** `+250788811607` makes the lookup fail with a Jackson error, while `0788811607` works. Every beneficiary is normalised before it is sent. A lookup GTBank can't read means "account not verified".
- **No single answer ever decides about money.** These cases were examples, not the full list. `GtbankClient::classify` returns "done", "refused" or "not found" only for a clear, readable answer. Everything else is **unknown**:
  - an empty or truncated body, an HTML proxy page, a redirect
  - 408/425/429/5xx, a wrapped DigiPay error on a write
  - an `{"error"}` in a 200 on a write
  - TLS, DNS and reset errors
  - codes 12 and 96

  Codes arrive as numbers or strings (`0`, `"00"`). `Gtbank/BankAnswers.php` then decides a disbursement only from an explicit status for **our** reference, **our** amount and **our** account. DigiPay's `"status":"FAILED"` describes the *query*, so it never counts as a transfer failure. A "not found" is never proof on its own, because a write that timed out can appear minutes later. `GtbankConfusingAnswersTest` covers 44 answers.
- **GTBank sends no webhook secret header.** That is why production had switched the check off. A webhook is now accepted when it carries the right secret, or carries none and comes from GTBank's address (Injonge Pay settings → GTBank's addresses, e.g. `10.22.198.46`). The money stays pending until GTBank's own transaction list confirms it.

## Who does what

| Actor | How | Can |
|---|---|---|
| Superadmin | core `is_superadmin` | everything, and "Log in as" any user to help |
| **Platform staff** (Inhills) | users of the operator (primary) business holding the **platform permissions** (`{action} injongepay_platform.{x}`, `injongepay_connect_platform.{x}`) through core roles | create, register and close merchant codes; KYC, fee and limits; reconciliation; money to allocate; settle cash-outs GTBank gave no final answer for; GTBank call log; register partner systems (integrators); the platform's own **Fee earnings** and their cash-out |
| Business admin and staff | core business roles, `{action} injongepay.{x}` / `injongepay_connect.{x}` | everything about their own money: payments, wallet pages, ledger, transfers between their own accounts, settlement accounts, cash-outs, approvals, reports, business contact details, POS connections and linked invoices, their own users and roles |

Inhills' own business does not show the business menus (it has no merchant codes); it sees only its platform menu plus **My PIN & devices**.

Core changes built for this:
- **Platform-only permissions** (`app/Support/Access/PlatformAccess.php`): permission modules a module marks `platform_modules` are held only by superadmins and operator staff; a customer business's Admin never gets them, and its role editor never offers them.
- **"Businesses manage their own staff and roles"** (Platform settings → Security). On by default.
- **Idle sign-out** (Platform settings → Security, minutes; 0 = off): server-side check in `LogoutIdleStaff`, a countdown banner and pings from `public/js/idle-logout.js`, audited.
- **Business type** on every Business (`config('core.business_types')`, 53 kinds).
- **Dashboard by module**: one section per module the business uses (figures, buttons, content), Team & settings at the end, activity log off unless turned on.
- **`whereUuid()` and uuid route binding** that answer "not found" instead of a Postgres 500 for a value that isn't a uuid.

## Module 1: `Modules/InjongePay` (money core)

module.json: `menu_group "Injonge Pay"`, sellable, tables prefixed `ijp_`. The skeleton follows `Modules/Savings`: a provider extending `App\Support\Modules\CoreModuleServiceProvider`, a `DataController` with the hooks `user_permissions`, `modifyAdminMenu`, `dashboard_stats`, `dashboard_quick_actions` and `dashboard_widget`, and `lang/en/{injongepay,audit}.php`. **When a migration is added, raise the version in module.json**, or `core:module update` won't run it on installed sites.

**Data.** Every table has `uuid` (kept from production), `business_id` (`app/Models/Concerns/BelongsToBusiness.php`) and `legacy_id` (for the import).
- `ijp_business_profiles`: TIN, contacts, fee override (the KYC GTBank needs). Name and type come from the core Business.
- `ijp_wallets`. Two separate fields replace the old `is_active` (fix 10):
  - `bank_status`: `NOT_REGISTERED`, `REGISTERING`, `PENDING`, `ACTIVE`, `INACTIVE`, `CLOSED`, `FAILED`
  - `is_enabled`: the platform's own switch
- `ijp_ledger_entries`: append-only, with `idempotency_key` unique.
- `ijp_payments`.
- `ijp_unallocated_payments`: bank money we couldn't credit yet (fix 5).
- `ijp_transfers`, `ijp_cashouts`, `ijp_cashout_events`. The events table holds every state change with its source (webhook, poller, operator).
- `ijp_settlement_accounts`, `ijp_approvals`, `ijp_bank_calls` (every GTBank call in or out, redacted, linked to its subject), `ijp_counters`.

Money is **bigint cents** with no floats; a `Support/Money.php` modelled on `Modules/Savings/app/Support/Money.php`. Cash-out amounts are whole RWF (fix 4).

**Services.** Each one is an injected class with logic only. Controllers stay thin and nothing in them is static (fix 13).

1. **Ledger.** The only writer of balances.
   - The wallet is locked inside the transaction; no overdraft.
   - The idempotency key turns a replay into a no-op.
   - Entries are never edited.
   - Fee credits to the internal wallets are posted per payment as **pending fee entries** and swept in batches by the scheduler, so payments never queue behind one hot row (fix 8). Platform totals stay exact.
2. **References.** `Support/Counter.php`: locked counters in `ijp_counters` for PAY, TRF and CSH (`CSH20261103000001`, letters and digits only, as GTBank requires) and for merchant codes (`555xxxxx`). A number is taken before the money transaction so payments never wait on the counter row. Nothing is random, so nothing can collide (fix 6).
3. **GtbankClient**, transport only.
   - Token refreshed once on a 401.
   - Retries only on idempotent GETs.
   - Every call goes to `ijp_bank_calls`.
   - Its result is typed: done, not found, refused or **unknown**. Callers branch on that, never on message text (fix 1). See "What GTBank really answers" above.
   - **Test mode**: with `GTBANK_BASE_URL` empty nothing is sent; merchant codes are active at once, accounts are accepted with the name typed, payments are available at once and cash-outs complete by themselves. Every Injonge Pay page shows a "Test mode" banner. Tests use `Http::fake` with GTBank's real answers.
4. **MerchantService**, an explicit state machine.
   - Registration is queued when a wallet is created.
   - A network error leaves the state as it was and records the error, instead of marking FAILED (fix 9).
   - A scheduled sync updates only `bank_status`, never `is_enabled`.
   - Every change goes through a POST and is audited (fix 12).
5. **Webhook inbox and PaymentIntake.**
   - The webhook stores the raw event in `ijp_webhook_events` (unique on `transactionId` + status) before acting on it, then processes it in the same request (no bank call is involved, so it is fast). If processing fails, the event stays and `injongepay:process-inbox` retries it every minute.
   - The duplicate check and insert happen in one transaction on a unique `bank_reference`. A replay returns the existing payment (fix 7).
   - A credit lands as **pending** until the bank mirror confirms it (Principle 1).
   - If the wallet can't take the payment, it is **parked** in `ijp_unallocated_payments` and the platform is notified (fix 5).
6. **BankMirror and Matcher** (Principle 1).
   - Scheduled windowed pulls, plus a button, feed `ijp_bank_transactions`.
   - Rows missing on our side are ingested; mismatches open reconciliation items; REVERSED rows post a reversal.
7. **Authorization** (Principle 2).
   - Pieces: PIN, risk-based step-up, limits, maker-checker, trusted browsers; settlement accounts checked against GTBank's holder name.
   - The approval payload is frozen and re-validated at execute time, and the row is locked when it executes.
8. **CashoutService**, rebuilt as an outbox flow driven by the queue and scheduler, never by the web request (fixes 1–3):
   ```
   REQUESTED → (step-up) APPROVED → [tx: lock wallet, debit, cash-out QUEUED]
   job Submit → SUBMITTED | REJECTED → reverse | UNKNOWN
   UNKNOWN/SUBMITTED → poller /disbursement/status + webhook → COMPLETED | FAILED → reverse
   ```
   - Every transition runs under a row lock and checks the expected from-state, and writes a `ijp_cashout_events` row.
   - Webhook amount and account are compared with our record; a mismatch raises an alert and changes nothing.
   - A status of "not found" after the grace period goes to **platform staff only**, who verify and reverse it (fix 2).
   - Only the payments the amount covers, oldest first, are marked cashed out.
   - Business users see status only.
9. **TransferService.** Only between the business's own merchant accounts; wallets are locked in id order.
10. **Reconciliation.** Daily close per merchant and day, plus the global identity and Σ entries = balance per wallet. Open items go to a platform queue with a resolution note, and every step is audited.
11. **Fee earnings.** The platform revenue and bank-cut wallets belong to the operator business. Inhills cashes them out to its own settlement accounts through the same CashoutService, Approvals and PIN rules a business uses. Business screens never list these wallets.

**Business screens:** overview, a page per merchant account (today, this month, cash-outs on the way, latest payments and cash-outs), payments, ledger, transfers (with a preview of the receiving account before the PIN), settlement accounts, cash-outs with their timeline, approvals, reports, business details (contacts and address; TIN, fee and limits are Inhills'), My PIN & devices.

Webhooks (`/api/payments/webhook`, `/api/gtbank/disbursement-status`) keep their URLs, request payloads, the secret header and the success response body. Two changes follow GTBank's own doc (§10.1.5): a duplicate gets **200 instead of 422**, and the answer is fast. The "secret check off" switch is removed.

**Audit.**
- Config models extend `App\Models\BaseModel`, so changes are audited automatically. Secret columns go in `$hidden`.
- Every money, bank and approval event calls `audit('injongepay_<event>', …, $subject, $details, $businessId)` and has a label in `lang/en/audit.php`.
- Balance updates are wrapped in `ActivityLogService::muted()`. The ledger and `ijp_cashout_events` are the immutable money trail and are linked from each audit row.

## Module 2: `Modules/InjongePayConnect` (partner sub-module, `requires: ["InjongePay"]`, sellable on its own)

- **Integrators** are partner systems (e.g. "Injonge EBM") registered by Inhills. Each speaks one **contract profile** (`config('injongepayconnect.profiles')`), a class that owns its answer shapes. Today there is one, `pos-ebm-v1`.
- **The business connects its own systems**: Integrations → Connect a system → choose an integrator → the client ID and secret are shown once. It renews the secret, switches the connection off (tokens stop at once) and sets allowed addresses. Linked invoices are on their own tab.
- **Inhills** keeps the integrator list (switching one off stops it everywhere), sees which businesses use each (names only, never credentials) and reads the API call log.
- **`pos-ebm-v1`** reproduces `/api/partner/v1/*` answer for answer (token, institution, merchant-accounts, payments, totals, confirm, unconfirm, invoices), checked by `PartnerApiV1Test`; the legacy `/api/ebm/payments/lookup` is kept unchanged.
  - Fixes that keep the contract: a cashed-out payment stays AVAILABLE/CONFIRMED in the filters; confirm/unconfirm shows in `updated_since`; a reversed payment can't be confirmed; `date_to` as a date covers the whole day; the filter is a join instead of `whereIn`; rate limit per connection; expired tokens pruned daily.
- A new integrator with different needs gets **a new profile class**. The money core is never touched.
- Partner document: `Modules/InjongePayConnect/API.md`.

## Reuse from core

| Need | Core piece |
|---|---|
| Tenancy | `BelongsToBusiness`, `app/Support/Tenancy/CurrentBusiness.php` (`set()` in jobs and commands) |
| Permissions, menu | DataController hooks → `app/Services/Permission/AccessCatalogue.php`, `app/Support/Menu/AdminMenu.php` |
| Audit | `audit()` (`app/Support/helpers.php`), `LogsActivity`, `ActivityLogService::muted()` |
| Notifications | `app/Services/Notification/NotificationService.php`, `app/Notifications/BrandedNotification.php` |
| Settings and secrets | `app/Support/Settings/BusinessSettings.php`, `System` + `Crypt::encryptString` (bank credentials and webhook secret) |
| Step-up | `password.confirm.staff`, `StaffMfaService::verify()` |
| Gating | `module:InjongePay` |
| Queue and scheduler | `CoreModuleServiceProvider::configureSchedules`, database queue |
| API skeleton | `Modules/Distribution/app/Providers/RouteServiceProvider.php`, `AuthenticateSite` (hash lookup), named rate limiters |
| Tests | `tests/TestCase.php` helpers, a `SavingsTestCase`-style base, `Http::fake` |

## Build phases (one commit per phase)

0. ✅ Core: platform-only abilities, the "businesses manage staff" setting, idle logout. Each has tests (`tests/Feature/PlatformAccessTest.php`).
1. ✅ InjongePay skeleton: migrations, Money, Ledger (pending/available buckets), references, business profiles, permissions and menu, audit labels, platform fees and webhook secret.
2. ✅ GtbankClient (typed outcomes, v2.5 error-code map, token refresh, reads retried, posts never resent), bank-call log with screens, MerchantService state machine, scheduled sync, platform wallet screens. Without GTBANK_BASE_URL the module runs in test mode (wallets active at once, nothing sent).
3. ✅ Webhook inbox (stored before processing, retried every minute), PaymentIntake (net to pending; available once GTBank's list confirms it), money held when a code can't take it (platform credits it or records how it was settled), fee accruals swept to the platform wallets every 10 minutes. `/api/payments/webhook` answers as before except: a redelivery gets 200 with the existing payment, held money gets 200 "Payment received and held for review."
4. ✅ **BankMirror, Matcher, daily close, reconciliation queue.** This is the parity engine, and it comes before any money leaves. Built so far:
   - GTBank's list read every 10 minutes: confirms our payments, takes in missed ones, ignores rows about other codes, never confirms a different amount
   - REVERSED handled: the money and fees come back off, and an item opens if the money was already cashed out (nothing goes negative)
   - each day closed at 01:30 per merchant code, comparing count and total
   - the books check: entries = balances, and the platform identity
   - payments GTBank hasn't confirmed after 24 h are flagged
   - a queue of differences that stay open until explained
5. ✅ Authorization, settlement accounts and transfers:
   - 5-digit PIN, locked after 3 wrong tries
   - step-up (authenticator, or an emailed code) only for: a new browser, a large amount, the first payout to an account, many operations in an hour, or a recently set PIN
   - trusted browsers
   - per-business limits (per operation, per day, per month)
   - a second person above a threshold, never the requester
   - settlement accounts added by the business, usable once GTBank confirms the holder name
   - transfers between the business's own accounts, locked in id order
6. ✅ Cash-outs:
   - The request reserves the money; a queued job sends it to GTBank once and never resends it.
   - Status checks back off from 1 to 60 minutes.
   - **Paid or returned only on two agreeing signals**: two status reads, or a read plus GTBank's webhook, about our reference, amount and account.
   - Refused by the bridge and then not found twice over 10 minutes means the money goes back automatically.
   - Anything else unclear for 2 h goes to platform staff ("Being checked with GTBank"), who settle it only with GTBank's confirmation.
   - Every step is a timeline event.
   - Only the payments the amount covers are marked cashed out.
   - Whole RWF only.
   - `/api/gtbank/disbursement-status` keeps its URL and answers.
7. ✅ Reports for a business (period totals, PDF/Excel of payments, ledger per wallet with balances after each entry) and the platform money overview (every business, fee wallets, books check, Excel).
8. ✅ InjongePayConnect: integrators with a contract each; the business connects its own systems (secret shown once, renew or switch off ends tokens, allowed addresses, linked invoices); Inhills registers integrators and reads the call log. `/api/partner/v1/*` and `/api/ebm/payments/lookup` answer exactly as production (contract tests in `PartnerApiV1Test`); partner document in `Modules/InjongePayConnect/API.md`.
After the phases, from trying it: Fee earnings, dashboard by module, business wallet pages and business details, transfer preview, test-mode banner, and moving settlement accounts and POS connections to the business.

9. The **data-migration phase**, planned separately: production Postgres → `ijp_*`, keeping UUIDs, references, merchant codes, partner secret hashes and the webhook secret, with a dry run and count/amount checks.

## Verification

- `php artisan test --testsuite=Modules` each phase. Coverage:
  - fee split
  - no overdraft
  - idempotent replays
  - reference uniqueness under concurrency
  - payment parked when its wallet is inactive
  - every cash-out path: ok, rejected, timeout → unknown → status, webhook mismatch, duplicate webhook, not-found
  - parity:
    - a webhook credit stays pending until the mirror confirms it
    - a forged webhook can't be cashed out
    - a missed payment is ingested by the mirror
    - a REVERSED transaction posts a reversal
    - a cash-out is final only after webhook + status
    - the daily close flags a 1-RWF difference
  - GTBank error-code map (00–15, 96) and every response-shape variant, using doc samples plus captured production payloads
  - authorization:
    - PIN only for a trusted beneficiary under the threshold
    - step-up on a new device, a large amount or the first payout
    - an account GTBank didn't confirm can't be used
    - limits enforced
    - maker-checker blocks self-approval
    - PIN lockout
  - only platform staff can settle an unclear cash-out; a business never can
  - business admin refused on wallet, role and staff edits
  - idle logout after 2 minutes
  - tenant isolation
  - an audit row for every action
- **Contract tests**: golden JSON captured from the production app for every partner, webhook and EBM endpoint, asserted byte for byte against the `pos-ebm-v1` profile.
- Manual: both panels clicked through in test mode on the local install.

## What's remaining (04/11/2026)

**Before go-live (blocking)**
1. **Data migration** from production (phase 9). Needs a `pg_dump` or read-only access to the production database.
2. **First run against the real GTBank.** These answers have never been seen from GTBank, only from its document:
   - `/transactions` with `startDate`/`endDate` per merchant: the 10-minute mirror and the daily close depend on it
   - `/merchants/register`
   - the disbursement-status webhook (production logs only show payment webhooks)

   Run on the server with the real keys: register one merchant code, read one day's list, make one small cash-out. Then add the answers to `tests/fixtures/gtbank-production.php`.
3. **Server setup**: cron `schedule:run`, a `queue:work` under Supervisor, `APP_ENV=production`, an https `APP_URL`, the `GTBANK_*` keys, the GTBank CA file. GTBank must call the two webhook URLs on the new domain.

**Promised in this guide but not built**
4. **Security changes are only audited**: a new PIN, a new settlement account and a changed limit notify no one. They should notify the business admin.
5. **PIN reset with MFA**: today changing a PIN needs the password only (plus the step-up afterwards for 24 h).
6. **Monthly statement check**: GTBank has no balance or statement API; uploading its settlement-account statement to compare totals is not built. Ask GTBank for a statement endpoint first.
7. **Velocity rule** counts operations per hour only, not amounts.
8. **Approval requests waiting for a second person** expire only when someone tries to approve them after 24 h; they aren't marked expired by themselves.

**Smaller**
9. **Partner rate limit per connection** has no screen any more; every connection uses the default (120/min).
10. **Translations**: Injonge Pay's screens are English only. The core supports French and Kinyarwanda.
11. **A click-through of every screen** in the browser, both as a business and as Inhills.
