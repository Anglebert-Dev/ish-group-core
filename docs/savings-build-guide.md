# Savings (Ish Group Finance) on ISH Core: Build Guide

Created: 29/10/2026. Maps the "Ish Group Finance — Functional Requirements Specification v1.0" onto the platform.

## 1. Goal

A personal-finance module that explains **where money is, what happened to it, what is committed, what is owed, what is expected, what can safely be spent, and what is likely next**, with actual, planned, expected, projected and reconciled figures kept apart.

- Module name: **Savings** (`Modules/Savings`), sidebar group "Savings".
- **Independent**: requires no other module. Sold through Billing packages like any module.

## 2. How it fits the platform

| Spec need | Platform answer |
|---|---|
| Users, ownership, permissions (FR-19) | The **business** is the owner of the finances (a person or household signs up as a "business"). Roles/permissions decide who sees what (`savings.*`). Other businesses never see the data. |
| Authentication, 2FA, security | Core login, MFA, session protection. |
| Audit history (FR-18) | Core audit log: records audited automatically; ledger events with `audit()` and reasons. |
| Notifications (FR-15) | Core notifications (in-app + email) from a daily command; preferences in settings. |
| Settings (FR-01) | `BusinessSettings` (`savings.*`): base currency, budget period rule, available-to-spend policy, alerts. |
| Backups (FR-17, NFR-10) | Platform backups + the module's own JSON export/restore. |
| Module on/off, packages | Billing packages; Super Admin → Modules. |

## 3. Money and the ledger (the heart)

- **Money = whole minor units** (`bigint`): RWF 25,600 → `25600`; USD 12.50 → `1250`. Decimals per currency from settings (RWF 0, USD 2…). No floats anywhere; rates use bcmath strings.
- **Ledger transaction** = one financial event (income, expense, transfer, refund, repayment, advance, fee, interest, adjustment…), with effective date, description, counterparty, reference, tags, idempotency key, status.
- **Entries** = how each account changed (signed, in the account's currency). Balance of an account = opening balance + sum of its entries. Nothing else stores a balance.
- **Lines** = what the money was *for*: expense (category), income (category), receivable up/down, payable up/down, adjustment. A transaction must **balance**: account movement = money in − money out across lines. A transfer has two entries and no lines (it cannot be income or expense).
- **Never edited after posting**: a correction **reverses** (mirror transaction, linked, reason required) and optionally posts a **replacement**. Reports net them out.
- **Idempotency**: every form carries a one-time key; resubmitting returns the same transaction. Likely duplicates (same account, amount, ±2 days, same reference) must be confirmed.
- **Foreign currency**: original amount, currency and rate are kept on the transaction; entries are in the account's currency.

## 4. Feature map (spec → module)

| Area | What we build | Spec |
|---|---|---|
| Accounts | Any number; types (bank, cash, mobile money, savings, investment, credit card, loan, other); liquid vs not; asset vs liability; opening balance + date; minimum balance; archive | FR-02, Sc.1 |
| Categories | Income/expense, nested, archive; tags on transactions | FR-07, Sc.4 |
| Transactions | Income, expense, transfer (incl. cross-currency + fee), refund (linked to original), split payment (part expense, part owed back), repayments, advances, borrowing, fees, interest, adjustments; search/filter | FR-03/04/16 |
| Budget periods | Monthly from any start day (28th→27th), weekly, biweekly, calendar; periods stored with explicit dates so history never moves | FR-05, Sc.3 |
| Budgets | Limit per category per period; spent, remaining, over, projected; copy from previous period; transfers never count | FR-05, Sc.4 |
| Expected events | Expected income (instalments like 450k + 550k), bills, transfers, contributions, repayments; confidence (confirmed/expected/uncertain); mark as received/paid (posts once) or match to an existing transaction; skip | FR-06/08, Sc.2/11 |
| Recurring rules | Daily/weekly/monthly/yearly × interval, fixed or variable, start/end, skip; generates expected events; editing changes only future unposted ones | FR-08, Sc.11, AT-18 |
| Obligations | Owed to me / I owe / **to restore** (money taken from savings); original amount, due date, counterparty, evidence notes; partial repayments; adjustments; disputed, written off; restoration basis built from selected transactions (each only once) with an explanation | FR-09, Sc.6–8 |
| Commitments | Money promised from an account (bill, group contribution); lowers available-to-spend, not the balance; settle with a payment | FR-11, Sc.10 |
| Goals | Target amount/date, earmarked allocations (plans) vs funded contributions (posted transfers); reassign between goals with history; missed contributions | FR-10, Sc.5 |
| Available to spend | Per liquid account: balance − open commitments − (optional) goal earmarks − (optional) minimum balance | FR-11, BR-06 |
| Reconciliation | Statement balance + date vs ledger; difference stays visible; resolve with explanation or an explained adjustment; history kept | FR-12, Sc.12 |
| Forecast | Day-by-day per account from today: actual balance + expected events − commitments; confirmed vs uncertain; first shortfall date (below zero or minimum) with the events causing it; what-if (move a date / change an amount) without saving | FR-13, Sc.13 |
| Reports | Income and spending by category, budgets, transfers, balances (as of a date), obligations, goals, recurring, cash flow by month; PDF/Excel | FR-14 |
| Alerts | Upcoming bills, overdue obligations, late income, budget 80%/100%, missed contributions, projected shortfall; each switchable | FR-15 |
| Import / export / restore | CSV statement import with preview, validation and duplicate flags; full JSON backup; restore as merge (skip existing) or replace | FR-17, Sc.15 |

## 5. Business rules → where enforced

| Rule | Enforcement |
|---|---|
| BR-01 actual balances | Only posted entries count; expected/forecast never write entries |
| BR-02 transfers | Two equal and opposite entries, no lines → never in income/spending |
| BR-03 repayments | Line `receivable_down` → reduces outstanding, not income |
| BR-04 refunds | Refund links to the original; reduces that category's spending |
| BR-05 allocations | Goal allocations and budgets are plans; no entries |
| BR-06 available | Calculated view, never posted |
| BR-07 forecasts | Calculated view; each event labelled confirmed/expected/uncertain |
| BR-08 reconciliation | Difference kept until explained/resolved |
| BR-09 corrections | Reverse + replace, reason required, audited |
| BR-10 idempotency | Unique key per business on transactions; one posting per expected event |
| BR-11 currency | Original amount/currency/rate kept |
| BR-12 history | Lines keep the category used at the time; rules regenerate only future events |

## 6. Build phases (as the spec orders them)

| Phase | Content | Exit test |
| ✅ all | Built 30/10/2026: engine + screens; 38 tests (AT-01…AT-20, scenarios, screens) | |
|---|---|---|
| 1 | Accounts, categories, ledger engine, transfers, corrections, idempotency, duplicates | AT-01–04, 07, 08, 14, 17, 19 |
| 2 | Budget periods, budgets, expected income instalments | AT-05, 10 |
| 3 | Recurring rules, obligations (receivable, payable, restoration), commitments, goals, available-to-spend | AT-06, 09, 11, 18 |
| 4 | Forecast + shortfalls, reconciliation | AT-13, 15 |
| 5 | Reports, alerts, import/export/restore | AT-12, 16, 20 |

## 7. Open points (for you)

- ✅ Decided 30/10/2026: finances are **private per user** (owner_id on every table, scoped like business; settings, alerts, backups per user).
- Exchange rates are entered by hand (no rate feed).
- Bank connections are out of scope; statements come in as CSV.
