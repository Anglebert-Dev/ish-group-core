# Garment Factory on ISH Core: Build Guide

Updated: 28/10/2026. Replaces the earlier "Sellable Module Pack" plan, which was written before the platform existed.

## 1. Goal

- Garment factory management, built as **separate modules that are sold separately**.
- **Generic modules first** (useful to many business types), then a thin **Garment pack** on top.
- Common app features come from ISH Core and are never rebuilt in a module.

## 2. What ISH Core already gives

| Need (old plan §2–§3) | Status in ISH Core |
|---|---|
| Login, users, roles, password reset, MFA | ✅ Core |
| Multi-business separation | ✅ `business_id` + `BelongsToBusiness`; each business sees only its data |
| Notifications | ✅ In-app + email (sent in the business's name). ❌ SMS |
| Module registry, versions, install/update | ✅ `php artisan core:module …`, Super Admin → Modules |
| Entitlements per business (which modules are paid) | ✅ Billing module: packages include modules |
| Plan limits | ✅ Packages set limits (`users` today; modules register their own, e.g. `inventory.stores`) |
| Module-level permissions | ✅ Each module declares permissions; roles only see what they're given |
| Trial and expiry | ✅ Trial → active → past due (grace) → **locked** until paid. Data is never deleted |
| Audit trail | ✅ Every change and action, per business; superadmin sees all |
| Customer list | ✅ Core **Customers** (Management → Customers) |
| Document numbering | ✅ `DocumentNumber::next('sales_order', 'SO')` → `SO-2026-00001`, per business, locked, never reused |
| Module dependencies (Sales needs Inventory) | ✅ `"requires"` in `module.json` |
| Shared movement log | ➡️ Part of the Inventory module (every stock-moving module requires Inventory) |
| Per-business configuration (units, stages, prefixes) | ✅ `BusinessSettings` key/value per business, audited |
| Usage tracking (per-use pricing) | ❌ Only if something is priced per use |

## 3. Platform services for modules (built)

1. **Module dependencies:** `"requires": ["Inventory"]` in `module.json`.
   - Install/enable is refused while a requirement is missing; disable/uninstall is refused while another module needs it.
   - A business can only use a module when it can use everything it requires.
   - Adding a module to a package (or to a business's switches) adds its requirements automatically.
2. **Document numbers:** `app(DocumentNumber::class)->next('sales_order', 'SO')` inside the saving transaction.
   - Per business and type; yearly sequence by default (`SO-2026-00042`); the counter row is locked, so no duplicates.
   - The business can change it: `numbering.<type>.prefix`, `.yearly`, `.digits`.
3. **Per-business settings:** `app(BusinessSettings::class)->get('inventory.units', ['pcs'])` / `->set(...)`.
   - Defaults live in code; only changes are stored; every change is audited.
   - Keys are namespaced by module (`inventory.*`, `production.stages`, `numbering.*`).
4. **Movement log:** built inside **Inventory** (`inventory_movements` + `StockService`). Other modules move stock only through it:
   `app(StockService::class)->record(MovementType::Sale, [['variant' => $v, 'store' => $s, 'quantity' => -3]], reference: $invoice, referenceLabel: $invoice->number)`.
   It locks the rows, checks the net per variant/store (no overselling across lines), refuses below zero (`InsufficientStock`), and is all-or-nothing.
5. **SMS:** only when a provider is chosen.

## 4. Module catalogue

Each module is its own folder under `Modules/`. "Requires" means it cannot be installed or sold without the listed modules.

| Module | Requires | Key rules (from the old plan §4 and §6) |
|---|---|---|
| **Inventory** (finished goods + materials) | — | Items with variants, units, multiple stores; reorder level; all changes via movement log; materials never mixed with finished goods; correction cannot go below zero; per-item history |
| **SalesOrders** | Inventory | Number, customer, due date, lines, status; lines editable only while Pending; cancel states what happens to reserved stock |
| **Quotations** (quotes + samples) | SalesOrders | Follow-up date, deposits; "Convert to order" copies customer, items, prices; returned sample can go back to stock |
| **Production** (work orders) | Inventory | Stages configured per business; counts move forward only; a later stage can never exceed an earlier one; finishing adds only new pieces to stock; lowering finishing reverses stock, or is refused if already sold; daily target per line |
| **Invoicing** (sales + customer payments) | Inventory | Invoice = stock issue + money; stock checked across all lines (two lines of one item can't oversell); payments as a list, capped at the total; cancel restores stock, or creates a credit note if delivered |
| **Delivery** | SalesOrders | Delivery note, partial deliveries, receiver name/signature |
| **Purchasing** (suppliers) | Inventory | Suppliers, purchase orders, goods received; reorder level suggests a purchase; supplier balance |
| **GarmentPack** | Production, Inventory, SalesOrders | Style library with size×colour matrix; bill of materials per style; costing per piece and margin; cutting plan; bundles; quality control (rejects never enter stock); piece-rate labour; subcontracting/CMT; tailoring measurements |

Customers are **core**, not a module: every package gets them.

**One switch:** every module above needs Inventory (directly or through another). Turning Inventory off (Super Admin → Modules) turns them all off, data kept; leaving it out of a package hides them all for that business.

EBM / RRA tax invoicing is **not** planned.

## 5. Packages (set up in Super Admin → Billing → Packages)

| Package | Modules |
|---|---|
| **Base** | Inventory, Invoicing (+ core Customers, reports, dashboard) |
| **Orders add-on** | SalesOrders, Quotations, Delivery |
| **Purchasing add-on** | Purchasing |
| **Production add-on** | Production |
| **Garment pack** | GarmentPack |

Prices: to be decided (cost and market data).

## 6. Kigali Sun issues

| Issue | Where it's handled |
|---|---|
| Default password on a public page | ✅ Solved by core (no default passwords; breached passwords refused) |
| "Reset system" before login | ✅ Solved (no such feature) |
| Same email saved twice | ✅ Solved (users unique; customers unique per business) |
| Last admin can be made inactive | ✅ Solved (blocked) |
| Client deleted with linked orders | ✅ Core blocks delete when a module reports links (`customer_links` hook); use Inactive |
| Order lines cannot be edited after create | SalesOrders rule + test |
| Finishing can exceed sewing / lowering finishing keeps stock | Production rules + tests |
| Finished goods show only the current number | Inventory: movement log history |
| Payment above total accepted | Invoicing rule + test |
| Cancel does not return stock | Invoicing rule + test |
| Two lines of one item oversell | Invoicing stock check + test |
| Sample "Update advance payment" broken | Quotations: deposits list + test |
| Approved sample shown as "Completed" | Quotations: correct status labels + test |
| Production report shows current, not historical | Reports read the movement log |

## 7. Build order

| Step | What |
|---|---|
| 1 | ✅ Platform services (§3): dependencies, numbering, per-business settings |
| 2 | ✅ Customers in core |
| 3 | ✅ Inventory (with the movement log) |
| 4 | ✅ SalesOrders |
| 5 | ✅ Production |
| 6 | ✅ Invoicing + customer payments |
| — | ✅ Automated end-to-end factory test (`InvoicingTest::test_factory_order_end_to_end`). **Still run one real factory order with a client before going further** |
| 7 | ✅ Quotations, ✅ Delivery |
| 8 | ✅ Purchasing |
| 9 | ✅ GarmentPack: styles, BOM with waste, costing and margin, BOM issue, size×colour grid entry, QC rejects (Production), piece-rate pay and payroll, CMT, bundles with tickets, measurements |
| 10 | ✅ Reports module (sales by product/customer, production output and rejects, stock value, movements, purchases) + dashboard cards per module |

## 8. Rules every module follows

- Every table has `business_id`; models use `BelongsToBusiness` and extend `BaseModel`.
- Every action is audited: records automatically, other actions with `audit()`.
- Linked data is never hard-deleted; use Inactive / Cancelled.
- Quantity changes go through the movement log only.
- Each module ships: permissions (`user_permissions`), its sidebar section (`modifyAdminMenu`), report tab, dashboard cards (`dashboard_stats`, `dashboard_widget`), limits it needs (`Quota::register`), and tests for every rule in §4.
- A disabled or locked module hides its screens; its data stays.

## 9. Open questions (yours)

- Package prices.
- SMS provider (if SMS is needed).
- First real factory to run the end-to-end test with.
