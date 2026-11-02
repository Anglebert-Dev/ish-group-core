# Branding & printing: Build Guide

Created: 02/11/2026. Status: **planned**. Update §7 after each phase, so work can pick up where it stopped.

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

The Invoicing module's own issuer settings (`invoicing.tax_id`, `invoicing.address`, `invoicing.phone`, `invoicing.footer`) are copied into branding once, and branding is read first from then on.

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
| 1 | Branding data + Branding page (identity, visuals, stamp & signature, payment details); copy Invoicing issuer settings | ☐ |
| 2 | Print engine: `Document`, layouts, PDF/HTML/Excel, design settings + live preview, printables registry + routes + buttons; lists through the engine | ☐ |
| 3 | Move the existing PDFs (Invoicing, Billing, Delivery) onto the engine | ☐ |
| 4 | New documents in every module (§5) | ☐ |
| 5 | Check every document in PDF and print view (rendered to images and looked at), tests, README | ☐ |
