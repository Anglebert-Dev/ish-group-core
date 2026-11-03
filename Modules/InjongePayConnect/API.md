# Injonge Pay Partner API (contract `pos-ebm-v1`)

> Served by the InjongePayConnect module. Same routes, fields, codes and messages as before. Changes that keep the contract: a cashed-out payment stays `AVAILABLE`/`CONFIRMED` in the filters, `updated_since` includes confirmation changes, `date_to` as a date covers the whole day, a reversed payment can't be confirmed, and the rate limit applies per connection instead of per IP.

Reference for the API the POS/EBM integration consumes: merchant accounts, payments, and linking a payment to a signed invoice. For the internal design rationale (why reserve/release were removed, why the confirmation table is separate from the core payment model), see `README.md` in this same folder.

**Base URL**: `https://<host>/api/partner/v1`
**Format**: JSON in, JSON out. UTF-8.
**Auth**: OAuth2 client-credentials shape (`POST /oauth/token`), then `Authorization: Bearer <token>` on everything else.

> **Changelog**: an earlier draft of this API had `reserve`/`release` endpoints with a hold-before-confirm step. Those are gone. The flow is invoice-first: you sign the EBM invoice immediately, then call `confirm` to link it to a specific payment afterwards (or `unconfirm` to undo that link). `confirm` now also supports partial payments — several confirmations can share one `invoice_no`, one per payment, so `GET /invoices/{invoice_no}/payments` returns the full history of a partly-paid invoice.

---

## 1. Authentication

### `POST /oauth/token`

No auth required (this is how you get it). `Content-Type: application/x-www-form-urlencoded`.

| Field | Required | Description |
|---|---|---|
| `grant_type` | yes | Must be `client_credentials` |
| `client_id` | yes | Issued per institution from Injonge Pay's admin UI |
| `client_secret` | yes | Issued alongside `client_id`, shown once at generation/rotation time |

**Response `200`**
```json
{
  "token_type": "Bearer",
  "access_token": "a1b2c3...64 chars",
  "expires_in": 3600
}
```

**Response `401`** (wrong client_id/secret, or credential disabled)
```json
{ "error": { "code": "invalid_client", "message": "Invalid client credentials." } }
```

Token lifetime is **3600 seconds** (1 hour), server-configured. There is no refresh-token grant — request a new token with the same client_id/client_secret once the old one expires; nothing is gained by tracking expiry yourself beyond retrying on a 401.

Every other endpoint below requires `Authorization: Bearer <access_token>`. A missing, garbage, or expired token returns:
```json
{ "error": { "code": "unauthorized", "message": "Unauthorized action" } }
```
with HTTP `401`.

**Credential rotation**: if your `client_id`/`client_secret` is rotated or disabled on the Injonge Pay side, every token issued under it stops working *immediately* (not just new issuance) — a rotation invalidates live sessions, so expect a 401 on your next call and re-authenticate.

---

## 2. Institution

### `GET /institution`

Returns the institution that owns the token — use this as your connection test.

**Response `200`**
```json
{
  "id": "99efd5b2-b1e1-426c-9503-c149b2a8b00b",
  "name": "Rhenium Group",
  "tin": "123456789",
  "status": "ACTIVE"
}
```
`id` is always a UUID, never a database row number. `status` is `ACTIVE` or `INACTIVE`, derived from whether the institution itself is active in Injonge Pay — unrelated to any one merchant account's status.

---

## 3. Merchant accounts

### `GET /merchant-accounts`

Lists every merchant account (wallet) belonging to your institution.

**Response `200`**
```json
{
  "data": [
    {
      "id": "3f2a1c90-...",
      "merchant_code": "55500001",
      "name": "Main Till",
      "provider": "GTBANK",
      "status": "ACTIVE"
    }
  ]
}
```
`status` is one of `PENDING`, `ACTIVE`, `FAILED`, `CLOSED` — whether that specific merchant code is actually registered and live with GTBank. `provider` is always `"GTBANK"` today; Injonge Pay only integrates one bank at present.

### `GET /merchant-accounts/{id}/totals?date=YYYY-MM-DD`

End-of-day reconciliation totals for one merchant account, one calendar day. `{id}` is the merchant account's `id` (uuid) from the list above.

| Query param | Required | Description |
|---|---|---|
| `date` | yes | `YYYY-MM-DD` |

**Response `200`**
```json
{
  "date": "2026-10-09",
  "count": 14,
  "gross_total": 182000.00,
  "fee_total": 3640.00,
  "net_total": 178360.00
}
```
`count` is the number of payments *received* that day regardless of confirmed/unconfirmed/reversed state — this is a reconciliation total against what GTBank actually credited, not a sales total.

---

## 4. Payments

### `GET /merchant-accounts/{id}/payments`

Lists payments received on one merchant account.

| Query param | Required | Description |
|---|---|---|
| `status` | no | `AVAILABLE`, `CONFIRMED`, or `REVERSED`. Omit for all. |
| `amount` | no | Exact amount match |
| `date_from` | no | ISO date/datetime — payments received on/after |
| `date_to` | no | ISO date/datetime — payments received on/before. A date alone (`2026-10-09`) includes that whole day. |
| `updated_since` | no | ISO datetime — only rows changed since this instant, including payments confirmed or unconfirmed by another terminal (use this for incremental Refresh, not `date_from`, so a status flip on an old payment still shows up) |
| `search` | no | Partial match against payer phone, payer name, or the bank's own transaction reference. No time limit — not restricted by `date_from`/`date_to` unless you also pass them. |
| `page` | no | Default 1 |
| `per_page` | no | Default 50, max 200 |

**Response `200`**
```json
{
  "data": [
    {
      "id": "7c1e2f44-...",
      "amount": 5000.00,
      "fee_amount": 100.00,
      "currency": "RWF",
      "network": "MTN",
      "payer_name": "Jean Claude",
      "payer_phone": "250788111222",
      "provider_reference": "TX_1001",
      "received_at": "2026-10-09T09:55:19+02:00",
      "status": "AVAILABLE",
      "invoice_no": null,
      "is_partial": null,
      "note": null,
      "invoice_total": null,
      "balance_after": null,
      "confirmed_by_name": null,
      "confirmed_at": null
    }
  ],
  "page": 1,
  "per_page": 50,
  "total": 1
}
```
`network` is the telco/channel name as GTBank itself reports it (e.g. `"MTN"`) — passed through as-is. `received_at` is ISO 8601 with timezone offset, always.

### `GET /payments/{id}`

Single payment, same shape as one item above, plus whatever confirmation fields apply.

### `POST /payments/{id}/confirm`

Links a payment to a signed invoice. Call this once the EBM invoice exists and you've matched it to a specific incoming payment (via the POS popup or the Payment Confirmation page).

| Field | Required | Description |
|---|---|---|
| `invoice_no` | yes | Your invoice number |
| `ebm_receipt_no` | no | RRA receipt number, once available |
| `sdc_id` | no | |
| `partial` | no | `true` if this payment doesn't cover the full invoice |
| `note` | no | Free text, e.g. `"Partial: 200 of 300 RWF"` |
| `invoice_total` | no | The invoice's full amount, for reference |
| `balance_after` | no | What's still owed after this payment |
| `cashier.id` / `cashier.name` | no | Whoever confirmed it, for the audit trail |
| `confirmed_at` | no | Defaults to server time if omitted |

Header `Idempotency-Key` is read (not required, but strongly recommended) — sending the exact same request twice *for the same payment* with the same key is a safe no-op, not a double-link.

**Several payments on one invoice (partial payments)**: `invoice_no` is **not** unique — call `confirm` again with a different `{id}` (a different payment) and the same `invoice_no` to record a second installment. Each call is its own confirmation row against its own payment; nothing rejects a repeat `invoice_no` as long as the `{id}` in the URL is a payment that hasn't itself been confirmed yet. Use `GET /invoices/{invoice_no}/payments` (below) to see the full history.

**Response `200`**
```json
{
  "id": "7c1e2f44-...",
  "amount": 5000.00,
  "fee_amount": 100.00,
  "currency": "RWF",
  "network": "MTN",
  "payer_name": "Jean Claude",
  "payer_phone": "250788111222",
  "provider_reference": "TX_1001",
  "received_at": "2026-10-09T09:55:19+02:00",
  "status": "CONFIRMED",
  "invoice_no": "INV-0042",
  "is_partial": true,
  "note": "Partial: 200 of 300 RWF",
  "invoice_total": 300.00,
  "balance_after": 100.00,
  "confirmed_by_name": "Jean Cashier",
  "confirmed_at": "2026-10-09T10:02:04+02:00"
}
```

**Response `409`** — *this payment* was already confirmed by a different request (not a retry of the same one — this is about the payment in the URL being reused, not about `invoice_no` being reused, which is allowed):
```json
{
  "error": {
    "code": "ALREADY_CONFIRMED",
    "message": "This payment has already been confirmed.",
    "invoice_no": "INV-0039"
  }
}
```
`invoice_no` here tells you which invoice already has *this specific payment*, exactly as asked for — check this before assuming your own request failed outright; if it's your own invoice number, something upstream retried without the idempotency key and the earlier attempt already succeeded.

### `GET /invoices/{invoice_no}/payments`

Every payment confirmed against one invoice number, oldest first — the full history of a partly-paid invoice. `{invoice_no}` is your own invoice number, not a uuid. Scoped to your institution (not one merchant account), since an invoice number alone doesn't tell Injonge Pay which account it was on.

**Response `200`**
```json
{
  "data": [
    {
      "payment_id": "7c1e2f44-...",
      "amount": 200.00,
      "invoice_no": "INV-0042",
      "is_partial": true,
      "note": "Partial: 200 of 300 RWF",
      "invoice_total": 300.00,
      "balance_after": 100.00,
      "status": "CONFIRMED",
      "confirmed_by_name": "Jean Cashier",
      "confirmed_at": "2026-10-09T10:02:04+02:00"
    },
    {
      "payment_id": "9a4b7e21-...",
      "amount": 100.00,
      "invoice_no": "INV-0042",
      "is_partial": false,
      "note": null,
      "invoice_total": 300.00,
      "balance_after": 0.00,
      "status": "CONFIRMED",
      "confirmed_by_name": "Jean Cashier",
      "confirmed_at": "2026-10-09T14:20:11+02:00"
    }
  ]
}
```
Includes `UNCONFIRMED` rows too (not just `CONFIRMED`) — check `status` on each entry if you only want the currently-active ones.

### `POST /payments/{id}/unconfirm`

Undoes a confirm. Who's allowed to call this is enforced entirely on your side (your "manager" permission) — Injonge Pay only checks that the institution-level API credential is valid, it has no concept of individual POS user roles.

| Field | Required | Description |
|---|---|---|
| `reason` | yes | Free text, kept for audit |
| `manager.id` / `manager.name` | no | Who undid it |

**Response `200`** — same payment shape as above, `status` back to `AVAILABLE`, `invoice_no`/`confirmed_*` cleared.

**Response `409`** — nothing to unconfirm (payment was never confirmed, or was already unconfirmed):
```json
{ "error": { "code": "conflict", "message": "This payment has not been confirmed." } }
```

**Deleting a payment on your side**: if a payment record is deleted in the POS, call this endpoint automatically (not just on a manual manager action) so the two systems don't disagree about whether it's still confirmed — use a fixed `reason` like `"Deleted in POS"` and omit `manager` if there's no specific user attached to an automatic sync.

---

## 5. Status vocabulary

| Status | Meaning |
|---|---|
| `AVAILABLE` | Received, not yet linked to any invoice |
| `CONFIRMED` | Linked to an invoice via `confirm` |
| `REVERSED` | GTBank reversed the payment (the money is no longer there). A reversed payment cannot be confirmed (`409 conflict`). |

There is no `"Confirmed elsewhere"` state — a merchant code belongs to exactly one institution and one API credential scope in this system, so nothing but your own integration can ever confirm a payment on your account.

Bank-side reversals are read from GTBank's own transaction list every 10 minutes and show as `REVERSED`.

---

## 6. Error codes

| HTTP | `code` | When |
|---|---|---|
| 401 | `invalid_client` | `/oauth/token` with wrong credentials |
| 401 | `unauthorized` | Missing/invalid/expired bearer token |
| 404 | `not_found` | Merchant account or payment uuid doesn't exist, or belongs to another institution (returned as 404, not 403, on purpose — existence isn't confirmed either way) |
| 409 | `ALREADY_CONFIRMED` | `confirm` on a payment someone else already confirmed (carries `invoice_no`) |
| 409 | `conflict` | `unconfirm` on a payment that isn't currently confirmed |
| 422 | `validation_error` | Missing/malformed required field |

---

## 7. Rate limits

The default API rate limit applies: **60 requests/minute** per token (falls back to per-IP if unauthenticated, e.g. on `/oauth/token` itself). A `429` means you've exceeded it — back off and retry, don't tighten your polling loop instead.

---

## 8. Testing

There is no separate sandbox environment or test credential set today — this API runs against the same database as production. For now:
- Generate a real credential via Injonge Pay's admin UI (Settings → your institution → API access) and use it against a real (or test) institution.
- To simulate an incoming payment without a real GTBank transaction, ask Injonge Pay to use the existing "Record Payment" admin action on a wallet — it creates a payment the same way a real webhook would, so it'll show up via `GET .../payments` as `AVAILABLE` immediately.

A proper isolated sandbox with disposable test credentials is not built yet — raise it if you need one before integration testing starts in earnest.

---

## 9. Fields intentionally not available yet

- **Customer's payment narration/message** (the free text a customer types when paying, which would allow auto-matching a payment to an invoice number) — GTBank's own webhook payload to Injonge Pay doesn't currently include this field at all. This would need to be requested from GTBank directly; Injonge Pay has nothing to pass through even if it wanted to.
- **Fee breakdown beyond a single total** — `fee_amount` is a single platform-fee figure; there's no further breakdown (e.g. bank fee vs. platform fee) since Injonge Pay only tracks its own fee, not GTBank's internal cost structure.
