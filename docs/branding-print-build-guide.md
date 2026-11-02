# Branding & printing: Build Guide

Created: 02/11/2026. Status: **Phases 1–2 done (02/11/2026)**. Update §7 after each phase, so work can pick up where it stopped.

## 1. Goal

Every business prints documents and lists that look like **theirs**, from one place:

- **Branding**, a core feature that's always available, like Users: who the business is, how it looks, its stamp and signature, and how to pay it.
- **One print engine**, shared by the core and every module. It produces a **PDF**, a **browser print view**, and **Excel** with the same branding.
- **Design with a live preview:** a layout style, colours, font, logo position and size, header and footer text, watermark, stamp and signature, paper size. Changes show in a sample document as you edit.
- **Every module's documents** use it: existing PDFs move onto it, and documents that couldn't be printed before get layouts.

## 2. Decisions (02/11/2026)

| Question | Decision |
|---|---|
| How documents are designed | **Settings + live preview** (not drag-and-drop). Reliable in PDF (dompdf) on every server. |
| Branding data | **All four groups:** identity and contact, visuals, stamp and signature, payment details |
| Output | **PDF download, browser print preview, branded Excel** |
| Scope | **Engine + all documents** (list below) |
| Where it lives | **Core** (`app/`), not a module: always on. Permission `settings.branding` (list/update), menu "Branding". |

## 3. Branding data (per business)

Stored with `BusinessSettings` under `branding.*`. Colour, logo and favicon stay where they are, on `businesses`.

| Group | Fields |
|---|---|
| Identity & contact | legal name, trading name (the name already used), tax ID (TIN), registration no., address, phone, email, website |
| Visuals | primary colour (existing), secondary colour, font, logo (existing), logo for dark backgrounds, favicon (existing) |
| Stamp & signature | stamp image, signature image, signatory name, signatory title |
| Payment details | a list of bank accounts and mobile money numbers (label, account name, number, bank or provider, branch, SWIFT) |

The Invoicing module's own issuer settings (`invoicing.tax_id`, `invoicing.address`, `invoicing.phone`) are **used as fallbacks**: branding is read first, then those. Nothing is copied, so the two can't drift apart. Phase 3 moves editing to Branding. `invoicing.footer` becomes the invoice document's footer in the print design.

**Phase 1 notes:**
- **The page:** Settings became **Branding**. Same route (`settings.index`/`settings.update`), same permission (`list`/`update settings.setting`), so existing roles keep access. It has tabs for Business details, Look, Stamp & signature and Payment details (`?tab=`), and each tab posts `section` and saves only its own fields. The old one-form post (no `section`) still works.
- **Code:** `App\Services\Branding\BrandingService` reads and saves; `App\Support\Branding\Brand` is the value object for documents (`documentName()`, `contactLines()`, data URIs for the logo, stamp and signature).
- **Stamp and signature** are stored on the **private** `local` disk, previewed through `settings.image/{kind}` (signed in, same permission, `no-store`), and embedded in PDFs as data URIs. Never a public URL.
- **Auditing:** every change goes through `BusinessSettings::set()`/`forget()`, which audit each key.

## 4. Print engine (core)

- **`Document`** (value object): type, title, number, dates, from/to parties, meta rows, a table (columns with alignment, rows), totals, notes and terms, payment details on/off, signature on/off, status watermark (PAID, DRAFT, CANCELLED…).
- **Design** (per business, `print.*` settings), shared by all documents:
  - layout: Classic, Modern or Compact
  - colours, defaulting to the brand
  - font: Sans, Serif, Helvetica or Times (fonts dompdf ships)
  - logo position (left, centre, right) and size
  - header text, footer text, terms
  - which identity lines to show
  - watermark on/off
  - stamp and signature on/off and position
  - paper (A4, Letter, A5)

  Per document type: its terms and footer, payment details on/off, signature on/off.
- **Renderers:** PDF (dompdf), HTML print view (opens the browser print dialog), Excel (PhpSpreadsheet: logo, details, colours).
- **Printables registry:** modules register their document types through a `DataController` hook (`printables`): type, label, and how to load and authorise a record and turn it into a `Document`. Core routes `app/print/{type}/{id}` (`?format=pdf|html`) serve every module. A shared `<x-print.buttons>` component gives the PDF and Print buttons.
- **Lists:** `ListExportLibrary` keeps its API, so the 28 existing exports don't change. It builds a list `Document` through the engine, and lists get a Print button too.
- Printing is audited, as list exports and invoice PDFs are today.

**Phase 2 notes:**
- **Code:**
  - `App\Support\Printing\{Document, PrintDesign, Printable, PrintRegistry, SampleDocument}`
  - `App\Services\Printing\PrintService` (`pdfBytes`/`pdf`/`html`/`excel`)
  - template `resources/views/print/document.blade.php`: one file, three layouts, PDF and browser
  - `config/printing.php` for core types
- **Adding a document type** (what modules do):
  1. Subclass `Printable` (`label`, `permission`, `find($routeKey)` under the business scope, `document($record)`).
  2. Return `['type' => Class::class]` from `DataController::printables()`.
  3. Put `<x-print.buttons type="…" :id="$record->getRouteKey()" />` on the record's page.

  The routes are `print.show` (print view, opens the print dialog) and `print.pdf`. Both check the permission and audit the print.
- **Page numbers** are stamped by dompdf's canvas (`page_text`), because CSS `counter(pages)` doesn't work in dompdf.
- **Lists:** `ListExportLibrary` keeps its API, so the 28 exports didn't change. `?print=1` on any list's PDF link gives the print view, and the export buttons now include **Print**. Numeric and amount columns are right-aligned automatically, and lists with more than 5 columns print landscape. The old `list-export-pdf` template is gone.
- **Designer:** Branding → **Documents** tab. The live preview iframe is `settings.print-preview`, which takes the unsaved choices as a query string, and `?format=pdf` shows the real PDF. Saved as `print.*` (layout, paper, accent, font, logo position and size, tagline, footer, 5 switches, and per-type terms and footer in `print.types`).
- **Checked visually:** all three layouts rendered to PDF and converted to images (poppler in a throwaway Docker image). The "1 / 0" page counter was fixed after that check.

## 5. Documents

| Module | Documents |
|---|---|
| Invoicing | invoice and credit note (existing PDF, moved), payment receipt |
| Delivery | delivery note (existing PDF, moved) |
| Billing | platform invoice (existing PDF, moved; branded by the platform business), payment receipt |
| Quotations | quotation, deposit receipt |
| SalesOrders | sales order confirmation |
| Purchasing | purchase order, goods received note, supplier payment voucher |
| Production | work order (job sheet) |
| GarmentPack | style costing sheet, CMT job order |
| Every list | PDF, Excel and Print through the engine |

Savings (personal finance) is left out on purpose.

## 6. Rules

- PDF layouts use what dompdf supports: tables, floats, no flexbox or grid. Every layout is checked in both PDF and the browser.
- Images (logo, stamp, signature) are embedded as data URIs, never fetched remotely.
- Branding and design changes are audited.
- Everything has a screen (`docs`: web-first). No terminal-only steps.

## 7. Phases and progress

| # | Phase | Status |
|---|---|---|
| 1 | Branding data + Branding page (identity, visuals, stamp & signature, payment details); copy Invoicing issuer settings | ✅ 02/11/2026 |
| 2 | Print engine: `Document`, layouts, PDF/HTML/Excel, design settings + live preview, printables registry + routes + buttons; lists through the engine | ✅ 02/11/2026 |
| 3 | Move the existing PDFs (Invoicing, Billing, Delivery) onto the engine | ☐ |
| 4 | New documents in every module (§5) | ☐ |
| 5 | Check every document in PDF and print view (rendered to images and looked at), tests, README | ☐ |
