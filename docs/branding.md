# Branding & Appearance: Plan

Created: 02/11/2026. Status: **B0, B1 and B2 done (02/11/2026).** Update §8 after each phase, so work can pick up where it stopped.

This takes the full "Branding & Appearance Module" requirements and sets out, section by section, **what we already have**, **what we build**, and **what we leave out and why**. Printing and document branding are already built (`docs/branding-print-build-guide.md`); this plan builds on them.

## 1. Where we are today

| Area | What exists |
|---|---|
| Identity | Branding page (Management → Branding): name, legal name, TIN, registration no., address, phone, email, website |
| Logos | Logo, dark-background logo, favicon (`businesses.logo_path`, `favicon_path`, `branding.logo_dark`) |
| Colours | **One** brand colour (`businesses.brand_color`) → CSS `--brand`, `--brand-dark`, `--brand-light`. Secondary colour is used only in print. |
| Design tokens | Tailwind (CDN) config maps `brand`, `ink`, `body`, `muted`, `surface`, `line`, `success`, `warning`, `danger` to fixed values. These names are used ~1,900 times across 230 views, but **~370 `bg-white` and ~390 hard-coded `gray/slate` classes** remain. |
| Font | Inter everywhere (Google Fonts), hard-coded in about 10 layouts. The branding font applies to print only. B0 makes one font choice apply everywhere. |
| Theme | Light only. No dark mode (7 views have stray `dark:` classes). No user preferences. |
| Layout | Fixed left sidebar (`components/dashboard/sidebar`), header, `responsive.css`. Menu built by `AdminMenu` from `config/menu.php` + module hooks, filtered by permission. |
| Dashboard | `DashboardService`: stats, quick actions, recent activity, module widgets (hooks). Fixed order. |
| Login | `layouts/auth`: brand colour gradient. It's the **platform** brand, because the login page doesn't know which business is signing in. |
| Emails | One template (`emails/message`): brand colour header, logo, button. |
| Documents | Done: engine, layouts, designer with live preview, per-type terms and footer, watermark, stamp, signature. |
| Error pages | Only `errors/403`. |
| Platform control | Superadmin's primary business is the platform brand. Billing packages have a `features` array (not yet used for branding). |
| History | Every settings change is audited key by key (`BusinessSettings::set`). No versions, drafts or rollback. |

## 2. Architecture (decide once, everything else follows)

1. **One theme document per business.** All appearance choices (colours, theme mode, layout, typography, login, dashboard, menu, email, advanced) live in one JSON document, validated by one `ThemeSchema` class. It's not dozens of loose settings. The schema says, for every key, its type, allowed values, default and which package feature unlocks it.
2. **Versions: draft → published → archived.** New table `branding_versions` (business_id, number, status, payload JSON, note, created_by, published_by, published_at).
   - Editing changes **only the draft**. **Publish** makes it live and archives the old one.
   - **Discard** drops the draft. **Restore** copies an archived version into a new draft. **Reset** makes a draft from platform defaults.
   - The page shows "last published on … by …". History lists every version. Each action is audited.
   - Identity data (name, TIN, logos, stamp, payment details) stays as it is today: saved immediately, outside versions. It's business data, not appearance.
3. **Design tokens are the only way views get colours, fonts and shapes.**
   - `ThemeCompiler` turns the published document into CSS variables (`--brand`, `--accent`, `--surface`, `--card`, `--ink`, `--sidebar-bg`, `--radius-card`, `--font-body`, `--density`…) for light **and** dark.
   - The Tailwind config maps every colour and shape name to a variable.
   - Components use the names, never hex codes, so a new module inherits branding with no work.
   - Compiled CSS is cached per business and version (`branding:{business}:{version}`) and cleared on publish.
4. **Fallbacks:** missing or invalid value → platform default → built-in default (today's look). A broken theme can never stop a page from rendering.
5. **Preview without publishing.** `?preview=draft` is honoured only for someone with `update settings.setting` in their own business, and only inside the studio iframe. It renders the real page with the draft tokens.
6. **Who controls what:**
   - **Platform** (superadmin): defaults (the platform business's published theme), which features each package unlocks (`features: ['branding.dark_mode', 'branding.login', 'branding.custom_css', …]`), and upload limits. Without Billing (self-hosted), everything is unlocked, as with modules today.
   - **Business:** permission `update settings.setting` (existing) to edit, plus a new `publish branding` so an owner can let staff draft while only some people publish.
   - **User:** personal light, dark or system mode, **only if** the business allows it. Stored on the user (`users.preferences` JSON), never on the business.
7. **Boundaries:**
   - Appearance never changes permissions. Hiding a menu item is cosmetic; routes stay protected.
   - It never changes business logic or legally required document content (TIN, totals, numbers, credit-note wording).
   - It's never global by accident: every read is by business.

## 3. Requirements: have / build / leave out

Each row shows its **priority** (P0–P3) and the **phase** (§8) that builds it.

### 3.1 Business identity

| Item | Status | Plan |
|---|---|---|
| Business name, logo, dark-background logo, favicon | ✅ have | |
| Light logo vs dark logo | ✅ have (logo + "logo for dark backgrounds") | Rename the labels to be clear |
| Short name | build, P0, B1 | Used in the collapsed sidebar, mobile header, PWA name |
| Login logo, mobile logo | build, P0, B1 | Optional; fall back to the main logo |
| Slogan, short description | build, P0, B1 | Slogan on login and dashboard welcome; description on login and the Frontend site |
| Brand/cover image | build, P1, B5 | Login background and dashboard banner |

### 3.2 Colours

| Item | Status | Plan |
|---|---|---|
| Primary | ✅ have | |
| Secondary | partial (print only) | B2: used in the app too |
| Accent, sidebar bg/text, header bg/text, page bg, card bg, border, link, primary and secondary buttons, success/warning/danger/info | build, P0, B2 | Each is a token. Blank means "work it out from primary", so most businesses set 1–2 colours. |
| HEX picker, manual entry | build, P0, B2 | Native colour input + text field |
| RGB/HSL | build, P1, B2 | Entry accepts `rgb()`/`hsl()`, stored as HEX |
| Palettes, restore defaults | build, P0, B2 | |
| Readable text suggestion, contrast check | build, P0, B2 | WCAG ratio on every text/background pair. Below 4.5:1 shows a warning and a suggested fix, and **blocks publish** for button and body text. |
| Live preview | build, P0, B2 | See 3.15 |

### 3.3 Themes

| Item | Status | Plan |
|---|---|---|
| Light, dark, system | build, P0, B2 | Dark is a second token set. It needs the B1 clean-up (no hard-coded whites and greys). |
| Default platform theme | build, P0, B1 | Platform business's published version |
| Custom, saved, switchable themes | build, P1, B6 | These are **presets** (3.14): saving a theme = saving a preset |
| Business default, user override on/off | build, P0, B2 | Setting `theme.user_override` |
| Preview before publishing | build, P0, B1 | Draft and publish |

### 3.4 Layout & interface

Only what our layout can do everywhere:

| Item | Plan |
|---|---|
| Sidebar: collapsible / expanded by default; width compact, standard, wide; look solid, minimal, bordered | **B3, P0** |
| Header: standard, compact | **B3, P0** |
| Density (navigation, tables, forms, spacing): compact, comfortable | **B3, P0**: one density token used by `x-common.table`, inputs and spacing |
| Content width: standard, wide, full | **B3, P0** |
| Card style flat, bordered, elevated; corners sharp, slightly rounded, rounded; button shape square, rounded, pill | **B3, P0**: radius and shadow tokens |
| Breadcrumbs, page title/subtitle, footer, back-to-top | **B3, P1**: switches on `x-common.page-header` and the layout |
| Loading indicator, transitions on/off | **B3, P1**: transitions also respect `prefers-reduced-motion` |
| Default landing page | **B6, P1** |
| Sidebar on the right | **Leave out**: the layout, drawers and every module page assume left. It belongs with RTL language support later. |
| Transparent header, "fluid" page layout | **Leave out**: same as full width, or unreadable over content |
| Floating action button position | **Leave out**: there's no floating action button |

### 3.5 Typography

**System font: B0, P0, done first.** One choice on Branding → Look changes the font of **the whole system**:
- the staff app and the login, forgot and reset password and 2FA pages
- emails, error pages, the business website (Frontend) and pricing pages
- printed documents

Today Inter is hard-coded in about 10 places (`layouts/app`, `layouts/auth`, `emails/message`, `errors/403`, `install/layout`, `auth/confirm-password-resume`, Frontend `layouts/public`, Billing `pricing`, GarmentPack `tickets`, Distribution `guide`). Each one switches to a `--font-body` variable plus a font link from `brand-head`.

- **List:** about 12 approved fonts (Inter by default, Roboto, Open Sans, Lato, Poppins, Montserrat, Nunito, DM Sans, Work Sans, Source Serif, Merriweather, Playfair Display), plus "System font" (no download).
- **Self-hosted:** the font files are bundled in `public/fonts` (woff2), so nothing loads from Google and it works offline.
- **PDF:** today the Look-tab font (Sans, Serif, Helvetica, Times) is for print only. It merges into this one choice. For PDFs, the same font's TTF is registered with dompdf, so documents match the screen. Without a TTF, the PDF falls back to the closest built-in font (sans → DejaVu Sans, serif → DejaVu Serif).
- **Emails:** the font is listed first, then safe fallbacks, because many mail apps ignore web fonts.
- The page loads only the chosen font, at the weights the app uses (400, 500, 600, 700).
- **Tests:** saving a font changes the CSS variable on the app, login, email and PDF; an unknown font is rejected; it's audited.

**B0 notes:**
- **Code:**
  - `App\Support\Branding\Fonts`: the list, CSS stacks, `@font-face` for screens and PDFs, preview faces
  - `BrandingService::font()`
  - `<x-common.brand-font />`, which sets `--font-body` and is used by `brand-head` and the standalone pages
- **Files:** `public/fonts` (woff2, about 1 MB) and `resources/fonts` (TTF, about 4 MB), licences in `resources/fonts/README.md`. Google Fonts links are removed from every page, and a test keeps it that way.
- **PDF:** each font gets its own family name in dompdf (`BrandFont-{key}`), because dompdf caches fonts by name: with one shared name, switching fonts kept the old one. Font metrics are cached in `storage/fonts`.
- **Document font:** the designer's font is now "Same as the system font" unless picked. Older print-only choices (sans, serif, helvetica, times) are read as "same as the system font" for documents, and as Inter or Source Serif for the system font.
- **Not changed:** the installer always uses Inter, because there is no business before install. The printable-page toolbar keeps the system UI font.
- **Checked:** screenshots of the dashboard, the Look tab (phone and desktop) and login with Poppins (headless Chrome in Docker, `ghcr.io/puppeteer/puppeteer`). PDFs were checked for embedded Merriweather, Lato and Playfair. The check also fixed the colour code boxes running off the screen on phones.

| Item | Plan |
|---|---|
| System font (whole app, login, emails, website, PDF) | ✅ **B0** (above) |
| Separate heading font | **B3, P0**: defaults to the system font |
| Base size (small, normal, large), heading scale, weight, line height | **B3, P0**: tokens |
| Letter spacing, text alignment default | **Leave out**: breaks tables and forms; little value |
| Font preview, restore default | **B3, P0** |
| Custom uploaded fonts (woff2) | **B10, P3**: licence checkbox, size limit, served from the business's storage. For PDF, also registered with dompdf (TTF needed). |

### 3.6 Login & authentication

The login page is shared by every business, so it has to **know which business** is signing in:
- **Business login link** `/login/{slug}` (the slug already exists for the Frontend site at `/s/{slug}`). It is shown on the Branding page and linked from the business's website.
- After a successful login, a cookie remembers the business, so `/login` on that device shows its brand next time. The cookie only changes the look; it plays no part in authentication.
- With no slug and no cookie, the page uses the platform brand.

| Item | Plan |
|---|---|
| Login logo, background colour or image, heading, welcome message, button colour (from tokens), footer text, contact details | **B5, P1** |
| Same branding on forgot, reset, confirm password and 2FA pages | **B5, P1**: one `layouts/auth`, so all of them follow |
| Register page | **B5, P1**: registration creates a **new** business, so it keeps the platform brand |
| Email verification page | **Leave out**: no such page exists (staff are created by admins). Revisit if added. |

### 3.7 Dashboard

| Item | Plan |
|---|---|
| Welcome message, banner (logo or cover image), announcement or notice | **B6, P1** |
| Widget visibility, order, columns (1–3), card look (from tokens) | **B6, P1**. Built on `DashboardService::widgets()` and the module hooks: each widget gets a stable key. |
| Widgets per role | **B6, P1**: a widget still needs its permission, so this can only hide, never show more |
| Widget resizing | **Leave out**: needs a drag grid; columns and order cover the need |

### 3.8 Navigation

| Item | Plan |
|---|---|
| Rename items and group headings, reorder, hide, icon from an approved set, default-open groups, badges on/off, landing page | **B6, P1**. Saved as overrides by route name and applied **after** `AdminMenu` has filtered by permission. A hidden item's route stays reachable for people who have the permission. |
| Navigation density | from B3 |
| Move items between groups | **B6, P1**, but only between existing groups |

### 3.9 Components

Nothing to configure per component: buttons, inputs, selects, checkboxes, switches, tabs, badges, alerts, toasts, modals, pagination, tables, uploads and progress bars **inherit tokens**. **B1** moves every `x-common.*` component onto tokens. **B2** checks each in light and dark on a "components" preview page.

Charts: there's no chart library today. B2 adds a `--chart-1…6` palette, derived from the brand, for when one arrives (Reports could use it).

### 3.10 Emails & notifications

| Item | Status | Plan |
|---|---|---|
| Logo, brand colour header and button | ✅ have | |
| Header colour, footer text, signature, contact details | build, P2, B7 | |
| Sender display name = business name | build, P2, B7 | The **address** stays the platform's verified sender, with **Reply-To** set to the business email. Own sender address or domain: P3 later (needs domain verification with the mail provider). |
| Password reset, invitations, transactional and document emails | build, P2, B7 | Check that every mail (including Laravel's built-in reset notification) goes through `emails/message`, and move any that don't |
| Preview each email before publishing | build, P2, B7 | Preview list in the studio |
| In-app notification colours | via tokens (B1) | |
| SMS sender name | **Leave out**: no SMS provider in the app |

### 3.11 Documents & print

Done: logo, name, contacts, address, registration, header and footer, accent, watermark, layouts, signature, stamp, PDF, per-type terms and footer.

Still to build (**B8, P2**):
- **Custom watermark text** (e.g. "COPY"), never replacing status watermarks like PAID or CANCELLED
- **Document background** (light tint or image, kept faint)
- **Layout per document type** (e.g. Modern for quotations, Compact for receipts)
- **Report header and footer** for lists and reports

Rule kept: legally required content (TIN, totals, numbers, credit-note title) can't be hidden or changed by design settings.

### 3.12 Mobile

Mostly a consequence of B1–B3 (tokens and `responsive.css`) plus the mobile logo and short name. **Every phase is checked at phone width** (360px), tablet and desktop, in light and dark. Touch sizes stay at least 44px whatever the density.

### 3.13 Custom CSS: B10, P3

Off by default. Needs package feature `branding.custom_css` and permission `publish branding`.
- Parsed and **rebuilt** (not just filtered). Rejected: `@import`, `@font-face` except uploaded fonts, `url()` except the business's own assets, `expression`, `behavior`, `javascript:`, `</style`, `position: fixed` over the whole page.
- Every selector is put inside `.app-content`, so the sidebar, header, login and anything security-related can't be restyled.
- 20 KB limit. Part of the draft and publish flow, audited, and with a "disable custom CSS" switch the platform can force off.
- No HTML or JavaScript anywhere, ever.

### 3.14 Presets: B6, P1

- **Built-in presets:** Corporate, Minimal, Modern, Dark, Warm, Garment/Fashion, Retail, Services. These are stored as theme documents in code.
- **Business presets:** save the current draft, apply, duplicate, delete.
- **Applying a preset** fills the **appearance** part of the draft only. It never touches identity, logos or document text.

### 3.15 Preview & publishing: B1 (engine), B4 (studio)

- **Studio:** editor on the left, preview iframe on the right.
  - Device switch: phone 390, tablet 820, desktop 1280.
  - Page switch: dashboard, a list page, a form, login, components, email, document.
  - Light and dark toggle.
- Draft, publish, discard, restore and reset (§2.2). Last published date and person, history list with a "restore" button.

### 3.16 Platform & tenant controls

| Item | Plan |
|---|---|
| Isolation, permissions, history, rollback, fallbacks | B1 (§2) |
| Platform defaults | B1: platform business's theme; superadmin edits it on the same studio |
| Feature availability per package | B1: `features` keys on Billing packages, checked by `ThemeSchema`; locked sections show "Not in your plan" |
| Upload limits (size, types) | B1: `config/branding.php` |
| Cache refresh on publish | B1 |
| New business | B1: starts with no version, so it uses platform defaults. Nothing is copied, so later platform changes reach it. |
| Business deleted | B1: its branding files are deleted with the business (local and public disks), as with its other files |

### 3.17 Extras

| Item | Plan |
|---|---|
| Browser tab title, favicon | ✅ have (`brand-head`) |
| Error pages 404, 419, 429, 500, 503 (maintenance) with the brand | **B8, P2**. 500 and 503 must render **without the database** (static fallback when branding can't load). |
| Empty-state messages | **B8, P2**: `x-common.empty-state` uses brand colours. Custom text per page: leave out. |
| Support contact, help-centre link, app footer, copyright | **B8, P2** |
| Announcement banner (business to its staff) | **B6, P1**: one banner, start and end dates, dismissible |
| Date and number format | **B8, P2**: date format choice plus thousands and decimal separators (currency already per business) |
| Terminology (rename words like "Work order") | **B10, P3**: overrides on chosen lang keys only, never on legal document wording |
| PWA name and icon (manifest per business) | **B10, P3** |
| Loading screen, notification sound, custom domains, QR logo | **Leave out**: no loading screen, sounds or QR codes today. Custom domains are an infrastructure project (DNS, SSL, routing); they get their own plan if wanted. |

**Also done (02/11/2026, asked during B1):**
- **Billable modules:** Super Admin → Modules has a **Billing** column. "In packages" modules are sold through packages; "Free for all" ones are open to every business and left out of the package form. Stored as `<alias>_billable` in the system table, read by `ModuleManager::isBillingExempt()`. `module.json` `billing_exempt` (Billing, Distribution) means always free and can't be changed. Audited, with the terminal twin `core:module billable|free <Name>`.
- **Pricing link:** "See plans & pricing" on the login page shows only while Billing is on. Its routes stay registered when it's off, so `Route::has()` wasn't enough.
- **Languages** (`App\Support\Locale\Locales`, `SetLocale` middleware):
  - a language is offered once `resources/lang/{code}` exists; names are in `config/app.php` `locales`
  - order: the user's choice (`users.locale`) → the business default (Branding → Business details → Default language) → `APP_LOCALE`; guests pick for their session
  - switcher: the user menu always lists the languages; the login pages show a selector only when there are two or more
  - `<html lang>` follows the language; changes are audited
  - adding French = copy `resources/lang/en` to `resources/lang/fr` (and each module's `lang/en` to `lang/fr`) and translate
- **Run `php artisan migrate`** after pulling (`users.locale`).

**B2 notes:**
- **Dark set:** `ThemeTokens::DARK_DEFAULTS`. On dark, `brand-light` becomes a dim brand tint (the active menu item) and `brand-dark` a brighter shade that passes 4.5:1 on cards, and links are lifted until readable. A test checks this for light, dark, yellow and near-black brands.
- **Modes:** the theme document has `mode` (light / dark / system) and `user_mode` (may people choose; unset = yes). `ThemeTokens::css()` writes light only, dark only, or light plus a `prefers-color-scheme: dark` block, and sets `color-scheme` so native date pickers and scrollbars follow.
- **People's own choice:** `users.appearance` (null = business default), from the user menu → Appearance (Business default / Light / Dark / Follow device). Shown and accepted only while the business allows it, and it never touches the business's setting. Audited.
- **Colours:**
  - every token has a dark counterpart (`colors_dark`, in a fold-out "Dark mode colours" section)
  - fields accept `#abc`, `#aabbcc`, `rgb()` and `hsl()`, stored as hex (`ColorLibrary::parse`)
  - six ready-made palettes fill the form without saving
- **Readability:** `ThemeContrast` checks text on what it sits on (WCAG ratio), in light and also dark when dark can be seen. Body, headings, sidebar and header text below 4.5:1, or secondary text below 3:1, **block publishing**. Links, status colours and white text on the brand are warnings. Each problem shows a readable suggestion (`ColorLibrary::readable`).
- **Chart palette:** `chart-1` to `chart-6` tokens, also used by the dashboard's quick-action tiles.
- **Status colours:** the remaining `red`, `green`, `amber` and similar classes (about 100) became `danger`, `success`, `warning` and `info` tints. Dark text on tints became `text-ink`. The guard test now also rejects coloured palette classes.
- **Secondary colour:** still documents only. The app uses accent and the token set instead.
- **Checked:** screenshots in dark mode of the dashboard, a list (phone), an order, the new-order form (date fields, selects), an invoice and the Theme tab.
- **Found:** the products table on order and invoice pages scrolls sideways at 1280px in both modes (an existing layout limit). To fix in B3 (content width).
- **Run `php artisan migrate`** after pulling (`users.appearance`).

## 4. What the B1 clean-up involves

**B1 notes:**
- **Tokens:**
  - `App\Support\Branding\ThemeTokens` defines the defaults (today's look) and resolves them against the brand and overrides.
  - CSS variables hold RGB channels (`--c-card: 255 255 255`), so opacity classes like `bg-brand/20` work. `--brand`, `--brand-dark` and `--brand-light` stay as hex for hand-written CSS.
  - New colour names: `page`, `card`, `accent`, `link`, `info`, `sidebar` / `sidebar-ink`, `header` / `header-ink`.
  - One shared `<x-common.tailwind />` replaces five copies of the Tailwind config (app, auth, 403, pricing, website).
- **The clean-up was smaller than counted above:**
  - `bg-white` (359) → `bg-card`, plus a handful of greys
  - translucent white over colour (`bg-white/20`) is allowed
  - the website's dark hero and footer, the installer, print and email stay fixed on purpose
  - `ThemeTokensTest` fails on any new raw colour class
- **Versions:**
  - stored in `branding_versions` (business, number, status, payload)
  - `App\Services\Branding\ThemeService` runs draft, publish, discard, restore, reset, history and preview
  - layers, in order: built-in → platform published → business published → draft (preview only)
  - published payloads are cached per business and cleared on publish
  - `ThemeSchema` keeps only known keys and valid values. The brand colour stays on Look, because it's identity and also used by emails and print.
- **Who can do what:** `update settings.setting` edits drafts. **Publishing needs `approve settings.setting`** (an existing permission, so roles can split editing from publishing).
- **Preview:** a session flag for that user only. It's checked against their business and edit permission, and ended by Publish, Discard or "Stop preview". A dark banner shows while previewing.
- **Screens:** Branding → **Theme** (colour fields grouped as Brand, Backgrounds, Text, Sidebar & header, Status; blank = default, shown as the placeholder) and **History** (versions, Restore, Reset to platform look). A publish bar appears on every Branding tab while a draft exists.
- **Sidebar and header** follow `sidebar-ink` and `header-ink` (icons, headings and borders as tints), and the sidebar uses the dark-background logo when its colour is dark (`ColorLibrary::isDark`).
- **Identity extras:**
  - short name, slogan and description on Business details
  - login page logo and phone logo on Look
  - the phone header shows the phone logo, else the short name; the login page uses the login logo
  - slogan and description are stored now and will be used by B5 (login) and B6 (dashboard welcome)
- **Package features:** Billing's package "features" are only marketing text. Since everything P0–P2 is for all plans, per-package switches (`Entitlements::allowsCapability`, package checkboxes) are built with B10, the first feature that needs one.
- **Checked:** a scripted browser walk-through on a throwaway QA database with the demo factory: save draft, preview (banner), publish, History, dark sidebar, phone header. It caught two bugs: dark sidebar text was unreadable, and the banner stayed after publishing.
- **Run `php artisan migrate`** after pulling (new table `branding_versions`).

Dark mode, page and card colours and density all depend on views using names, not fixed colours:
- `bg-white` (~370) → `bg-card`
- `text-gray-*`, `bg-gray-*`, `border-gray-*`, `slate-*` (~390) → `ink`, `body`, `muted`, `surface`, `line`
- inline hex in `brand-head` and the date fields → variables

It's mechanical but touches all 230 views, so it's done module by module, with a test that **fails if a view uses a raw colour class** (with a small allow-list). This guard is what keeps future modules branded automatically.

## 5. Rules

- One theme document per business, validated by `ThemeSchema`; views only use tokens.
- Nothing appearance-related changes permissions, data or legal document content.
- Everything has a screen (web-first); every action is audited, business-scoped.
- Every phase is checked visually at phone, tablet and desktop, in light and dark: screenshots looked at, not assumed.
- Self-hosted installs: no new external services required. Google Fonts are optional, with a system-font fallback.

## 6. Tests

For each phase:
- `ThemeSchema` rejects bad values and fills defaults.
- Draft never shows to staff.
- Publish, restore and reset produce the right live version, and each is audited.
- One business's theme never appears for another.
- A package without a feature can't save that section.
- Preview needs permission.
- Contrast blocks publishing unreadable button or body text.
- The raw-colour guard.
- Menu overrides can't reveal an item the user lacks permission for.
- Custom CSS sanitiser cases (B10).
- Error pages render with the database down.

## 7. Open questions

1. **Business login link:** is `/login/{slug}` acceptable, or should each business get a subdomain (needs wildcard DNS and SSL)?
2. **Package features:** which branding features are paid? Proposal: dark mode, login branding and presets for all; custom CSS, custom fonts and terminology on higher plans.
3. **Should staff be allowed to choose dark mode** by default when the business hasn't said?

## 8. Phases and progress

| # | Phase | Priority | Status |
|---|---|---|---|
| B0 | System font: one font choice for the whole system (app, login, emails, error pages, website, PDF), bundled font files, dompdf registration | P0 | ✅ 02/11/2026 |
| B1 | Foundation: theme document + `ThemeSchema`, versions (draft/publish/restore/reset), tokens + compiler + cache, platform defaults, fallbacks; identity extras (short name, slogan, description, login and mobile logos); **move all views and components onto tokens** + raw-colour guard. Package features moved to B10. | P0 | ✅ 02/11/2026 |
| B2 | Colours & themes: full palette, picker/HEX/RGB/HSL, palettes, contrast check + suggestions, dark and system mode, user override, chart palette | P0 | ✅ 02/11/2026 |
| B3 | Layout & typography: sidebar, header, density, content width, cards, corners, buttons, switches (breadcrumbs, titles, footer, back-to-top, transitions); fonts and type scale | P0 | ☐ |
| B4 | Studio: live preview with devices, pages, light/dark; history screen | P0 | ☐ |
| B5 | Login & authentication branding, business login link | P1 | ☐ |
| B6 | Dashboard (welcome, banner, widgets, per role), navigation overrides, landing page, announcement banner, presets | P1 | ☐ |
| B7 | Emails: header, footer, signature, contacts, sender name/Reply-To, every mail on the template, previews | P2 | ☐ |
| B8 | Documents extras, branded error pages, footer/copyright/support links, date and number formats | P2 | ☐ |
| B9 | QA: every module page at three widths, light and dark, with presets; README | P2 | ☐ |
| B10 | Advanced: custom CSS (sanitised, scoped), uploaded fonts, terminology, PWA manifest | P3 | ☐ |
