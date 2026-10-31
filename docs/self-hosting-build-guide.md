# Self-hosting (shipping modules to a customer's server): Build Guide

Created: 31/10/2026. Status: **All phases done (31/10/2026)**. Tested end to end on two real servers, on PostgreSQL and on MySQL 9.7. Update the progress table in §9 after each phase, so work can pick up where it stopped.

## 1. Goal

Some customers want a module (e.g. Savings) running **on their own server**, with no subscription. We need to:

1. **Choose what they get** in our app (only the modules we select, plus what those modules need) and build a ready-to-extract zip for them.
2. Let them **install it without a developer**: after extracting, a web install page asks for the database (MySQL or PostgreSQL), app details, email, and the first admin, then sets everything up. The same can be done **from the terminal** by their developer or by us.
3. **Keep them updated**: when we change a module, their server gets the new version from ours.

Customers can host the code however they want: shared hosting (cPanel, no terminal), a VPS, or Docker.

## 2. Decisions made (31/10/2026)

| Question | Decision |
|---|---|
| Does the core need changes to run without Billing? | **No.** Without Billing, `bindIf(Entitlements, UnlimitedEntitlements)` (`app/Providers/AppServiceProvider.php`) unlocks every module. Nothing outside `Modules/Billing` refers to Billing. |
| Who picks the modules? | **We do**, in our app. The customer can't add modules. |
| How do updates travel? | **Their server asks ours** (pull). It works behind firewalls and on shared hosting. We never connect to their server. |
| Install updates automatically? | **Set per customer** in our app: `auto` (installs on the next check) or `manual` (their admin clicks **Update**). New customers start on `manual`. |
| What can be updated? | **Modules only** for now. A core change goes out as a new full zip (core self-update could be a later phase). |
| Where do our screens live? | A new module, **`Modules/Distribution`** (Super Admin only). It is **never shipped**, and neither is Billing. |
| Licence | Not in scope for now. |

## 3. How it fits together

```
OUR SERVER (Distribution module)                CUSTOMER SERVER (core + chosen modules)
────────────────────────────────                ───────────────────────────────────────
Sites: customer, modules, update mode
Build zip ──────── zip + install code ────────▶ extract → /install (or core:setup)
                                                 → .env, migrate, admin, modules
Publish module version (signed)
/api/distribution/v1/check-in ◀──── hourly ──── core:updates (or "Check now")
        offers (signed) ──────────────────────▶ auto: install │ manual: show "Update"
/api/distribution/v1/releases/{id}/download ◀── download, verify, swap folder, migrate
/api/distribution/v1/report ◀─────────────────── result (success / rolled back)
```

What lives where:

| Piece | Where | Shipped? |
|---|---|---|
| Installer (web + `core:setup`), update client, Updates page | Core (`app/`) | Yes |
| Sites, module assignment, builds, releases, update API | `Modules/Distribution` | **Never** |

## 4. Facts found in the code that shape the design

- **Modules load through Composer.** The merge plugin writes fixed entries in `vendor/composer/autoload_psr4.php`, so a module added later on their server wouldn't load. Fix: a fallback autoloader in `app/Support/helpers.php` that maps `Modules\{Name}\` to `Modules/{Name}/app/`.
- **Every page touches the database.** `View::composer('*')` in `AppServiceProvider` calls `SettingService::current()`. Before install, those composers are skipped.
- **There is no Vite build.** `resources/js` doesn't exist, and the real assets are already in `public/`. The build copies `public/` as is.
- **`.env.example` assumes things a fresh server doesn't have yet.** It has `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, and database sessions and cache. Before install, these are forced to file sessions and cache, with secure cookies only on HTTPS.
- **Our server and the tests must never end up in the installer.** "Installed" means `storage/installed` exists **or** `APP_INSTALLED=true`.

## 5. Customer side (core, shipped)

### 5.1 Installer

- **Before Laravel starts** (`app/Support/Installer/PreInstall.php`, called from `bootstrap/app.php`), while the site isn't installed:
  - create `.env` from `.env.example`
  - **generate the APP_KEY on their server** (we never ship or know it)
  - force file session and cache, the sync queue, and a sqlite `:memory:` placeholder database
- **Middleware `RedirectToInstaller`:** before install, every URL goes to `/install`; after install, `/install` returns 404.
- **Wizard steps:**
  1. install code (sent with the zip; only its hash is in the zip)
  2. requirements
  3. database (test connection)
  4. app name, URL and timezone
  5. email (test send)
  6. first business and admin
  7. install
  8. finish page: cron line, queue setup, login link
- **Terminal:** `php artisan core:setup`, interactive or fully by flags:
  - `--db-*`, `--app-*`, `--mail-*`
  - `--business --admin-name --admin-email --admin-password`
  - `--modules`
  - `--skip-env` (Docker, where env comes from outside)
  - `--no-interaction`
- **`InstallationService`** is shared by the web and terminal installs. It resumes after a failure and takes a file lock. Steps:
  1. write `.env` (values quoted, newlines rejected, file mode 0640)
  2. migrate and set up permissions
  3. create the first business and superadmin
  4. install the shipped modules (requirements first)
  5. store the site token encrypted in the database
  6. `storage:link`
  7. write `storage/installed`
  8. first check-in with our server
  9. `audit('platform_installed')`
- **Shared hosting:**
  - `QUEUE_VIA_SCHEDULER=true` makes `schedule:run` also process the queue
  - a root `.htaccess` and `index.php` send requests to `public/` and block `.env`, `storage`, `vendor` and `Modules`

### 5.2 Updates

- `php artisan core:updates [--install]` runs hourly from the scheduler. The Super Admin → **Updates** page has the same "Check now" and "Update" buttons, plus the history.
- **Each download is checked before use:**
  - sha256 of the file
  - ed25519 signature (our public key ships in `config/distribution.php`)
  - the module name and version inside the zip must match the offer
  - Billing and Distribution are refused
- **Safe extraction:** rejects `..`, absolute paths, symlinks, more than one top folder, and oversized archives. Files are extracted to `storage/app/updates/staging` first.
- **Install steps:**
  1. optional database backup (`BackupService`)
  2. move the old folder to a backup
  3. put the new folder in place
  4. clear `bootstrap/cache/modules.php` and opcache
  5. run `core:module install|update X` in a fresh process
- **If it fails:** roll back the migrations, restore the old folder, and report `rolled_back`. The last 3 backups are kept.
- **Module migrations must be additive.** MySQL can't undo schema changes inside a transaction.

## 6. Our side (`Modules/Distribution`, never shipped)

### Tables

These are platform-level, with no `business_id`.

| Table | Holds |
|---|---|
| `distribution_sites` | uuid, name, customer, contact, app_url, `update_mode` (auto/manual), status, last reported core/PHP/database, `modules_writable`, `last_check_in_at` |
| `distribution_site_tokens` | **sha256 hash only**, last4, revoked_at (a new token per build) |
| `distribution_site_modules` | site, module, installed_version, status (pending_install, installed, update_available, failed), last_error |
| `distribution_releases` | module, version, sha256, signature, file, requires, changelog, published_at, yanked_at |
| `distribution_builds` | site, modules, core git sha, file, sha256, status, expires_at (14 days) |
| `distribution_check_ins` | each check-in (kept 90 days) |
| `distribution_update_reports` | each install or update result from their side |

### Rules

- **Assigning modules:** assigning a module also assigns what it requires. A module another assigned module needs can't be removed. Billing and Distribution can never be assigned.
- **Signing keys:** `distribution:keygen` makes an ed25519 key pair. The **secret stays only in our `.env`** (`DISTRIBUTION_SIGNING_SECRET`). The public key list goes in `config/distribution.php`, which ships.
- **Publishing:** `distribution:publish {module}`, or the button. It takes the version from `module.json`, refuses a version that already exists, zips the module without tests, then hashes and signs it.

### Building a zip

`distribution:build {site}`, or the button. It runs as a queued job:

1. `git archive HEAD`. Only committed files are included, so no `.env`, `vendor` or `docs`.
2. Remove:
   - modules not chosen, and Billing and Distribution always
   - tests, `phpunit.xml`, the demo seeder, `bootstrap/cache/*.php`
3. `composer install --no-dev --optimize-autoloader --no-scripts` with `platform.php=8.3`, then `composer check-platform-reqs`.
4. Copy `public/`.
5. Write:
   - `.env.example` (production, no secrets)
   - `storage/app/distribution/site.json` (site uuid, server URL, token, modules, install-code hash)
   - `config/core.php` (version)
6. Zip with one top folder, then sha256. Smoke test with `php artisan --version`.
7. Show the **install code once**, with an expiring download link.

Every action is logged with `audit()`.

## 7. Update API (our server)

Base path: `/api/distribution/v1`. Bearer token, checked by its hash. HTTPS only, rate-limited, no session.

| Endpoint | Request | Response |
|---|---|---|
| `POST /check-in` | site uuid, nonce, core/PHP version, database driver, `modules_writable`, installed modules and versions | `update_mode`, offers (assigned modules only; the newest non-yanked release newer than installed; `install` for newly assigned modules, requirements first). The body is signed (`X-Ish-Signature`) and includes the echoed nonce and the server time. |
| `GET /releases/{id}/download` | — | the zip; 404 unless the module is assigned to the site |
| `POST /report` | results: module, action, from/to version, status, error, duration | updates `site_modules` and logs the report |

The client rejects a check-in response if:

- the signature is wrong
- the nonce or uuid doesn't match
- the server time is more than 10 minutes off

## 8. Security checklist

- **Installer:**
  - marker file plus a lock file
  - install code required
  - refuses a database that already has users
  - 404 once installed
  - CSRF and rate limits
- **APP_KEY:** generated on their server.
- **Tokens:**
  - our side stores only hashes
  - their side stores the token encrypted and deletes it from `site.json`
- **Signing secret:** only in our `.env`.
- **Updates:**
  - signed responses and packages, plus sha256
  - protection against path traversal (zip slip), symlinks and zip bombs
  - Billing and Distribution refused on both sides
- **Shared hosting:** the root `.htaccess` blocks `.env`, `storage`, `vendor` and `Modules`.

## 9. Phases and progress

Commit after each phase. Run `php artisan test` before every commit.

| # | Phase | Content | Status |
|---|---|---|---|
| 0 | Groundwork | module fallback autoloader; `Installer` helper + `APP_INSTALLED`; `core:mark-installed`; `PlatformSetupService` pulled out of `InstallCoreCommand`; `core:install` writes the marker; `phpunit.xml` sets `APP_INSTALLED=true` | ✅ 31/10/2026: tests point the marker at a temp file (`Installer::useMarkerPath`, `tests/TestCase.php`); `storage/installed` is git-ignored |
| 1 | Installer | `PreInstall`, `RedirectToInstaller`, install routes and views, `RequirementsChecker`, `EnvWriter`, `DatabaseProbe`, `MailProbe`, `InstallationService`, `core:setup`, queue-via-scheduler, root `.htaccess` | ✅ 31/10/2026: 15 tests; tried for real on a fresh copy with Postgres (web wizard → sign in → dashboard; `core:setup --no-interaction` with Savings, including resuming after a failure) |
| 2 | Distribution basics | module scaffold, tables, sites, module assignment with requirements, tokens, `distribution:keygen`, releases + publish | ✅ 31/10/2026: 14 tests + core boundary test |
| 3 | Zip builds | `BuildService` + job, builds page, install code | ✅ 31/10/2026: 6 tests; real run: `distribution:build` (8 s, 15 MB zip with Savings) → extracted → web install with the code on Postgres → sign in, Savings pages work, Billing absent |
| 4 | Update API (server) | check-in, download, report, offer logic, site status page | ✅ 31/10/2026: 9 tests |
| 5 | Update client | `UpdateClient`, `SafeZipExtractor`, `ModulePackageInstaller`, `UpdateService`, `core:updates` + schedule, Updates page, auto/manual | ✅ 31/10/2026: 13 tests (real installs and rollbacks of a fixture module) |
| 6 | Finishing | pruning, README "Self-hosting" section, end-to-end test | ✅ 31/10/2026: `distribution:prune` (daily 03:30), first check-in right after install, a failed version isn't retried automatically, end-to-end runs below (PostgreSQL and MySQL) |

**Before deploying Phase 1 to our own server, run `php artisan core:mark-installed` there.** Otherwise our server will redirect to the installer.

### Phase 5 notes (customer side)

- **Files:** `app/Services/Updates/*`, `app/Console/Commands/CheckUpdatesCommand.php` (`core:updates [--install]`), `app/Http/Controllers/Web/SuperAdmin/UpdateController.php`, `resources/views/app/super-admin/updates.blade.php`, `resources/lang/en/updates.php`.
- **Linked or not:** a server counts as linked when the `system` table holds `distribution_token` and `distribution_server_url`, which the installer copies from `site.json`. Unlinked servers (ours, developers' machines) have no Updates tab, and `core:updates` does nothing on them.
- **Schedule:** `core:updates` runs hourly at a minute derived from `crc32(APP_KEY) % 60`, so sites don't all call at once. Automatic sites install straight away; manual sites store the offers for Super Admin → Updates, where the admin clicks Install (with a password confirmation).
- **Stored in `system`:**
  - `distribution_update_mode`, set by our server on each check-in
  - `distribution_offers` (JSON)
  - `distribution_last_check_at`, `distribution_last_error`
  - `distribution_update_history` (last 30)
- **An answer from our server is trusted only if:**
  - it's signed (`X-Ish-Signature` over `ish-checkin-v1\n` + body)
  - its nonce and site uuid match
  - its server time is within 10 minutes of ours
- **Offers are dropped if:**
  - the download URL isn't on our server
  - the module is Billing or Distribution
  - the module name or version doesn't have the expected shape
- **Package checks, in order:** sha256, then the signature, then (after safe extraction) that `module.json` has the offered name and version, then that the modules it requires are installed.
- **Install:**
  1. database backup, if `UPDATES_BACKUP_FIRST` is set (on by default; never blocks the update)
  2. the old folder moves to `storage/app/updates/backups/{Module}-{version}-{time}`, and the last 3 are kept
  3. the new folder goes in place
  4. `bootstrap/cache/modules.php` and opcache are cleared
  5. `php artisan core:module install|update X` runs in a **fresh process**. In-process is used when `UPDATES_IN_PROCESS=true` or `proc_open` is unavailable; it calls `ModuleManager::rescan()`, because nwidart caches the module list for the whole process.
- **Rollback:**
  1. roll back the update's recorded migrations
  2. run the failed migration's own `down()`, since it was never recorded (errors ignored)
  3. restore the old folder
  4. report `rolled_back`

  If a module fails, any later offer that requires it is skipped. Module migrations should still be written to be additive.
- **Reporting:** results go to `/report`. If that call fails, our server catches up at the next check-in, from the versions the site reports. Each result is also audited as `module_update_{status}` on the site's primary business.

### Phase 4 notes (our server)

- **Routes** are in `Modules/Distribution/routes/api.php`, under `/api/distribution/v1` with middleware `api` + `AuthenticateSite` + `throttle:distribution-api` (30 per minute per token). No session or CSRF.
- **Authentication:** `AuthenticateSite` looks up the bearer token by its hash and refuses revoked tokens (401), suspended sites, and a `site_uuid` that doesn't match (403). It also updates the token's `last_used_at`.
- **Offers** (`UpdateOfferService`): only assigned modules, ordered with requirements first. The latest available release is offered if it's newer than the installed version, or as an `install` when the site doesn't report the module. Each check-in also refreshes each site module's status and stores a `distribution_check_ins` row.
- **Without a signing key**, check-in answers 503 instead of an unsigned response.
- **Reports** update the site module's version and status (`failed` keeps `last_error` until the module succeeds) and write a `distribution_update_reports` row. The site page shows the update history and recent check-ins.

### Phase 3 notes

- **Build a zip:** Super Admin → Self-hosting → a site → **Build zip**, which runs as a queued job (needs `queue:work`). Or run `php artisan distribution:build "<site name or uuid>"`, which runs in place. The **install code is shown once**: in the flash message on the site page, or printed by the command. Only its hash is stored, in the build row and in `site.json`. If the code is lost, build again.
- **What's in it:** `git archive HEAD`, so **only committed code ships**. Commit first. Then:
  - unassigned modules, plus Billing and Distribution, are removed (the build fails if either is still there)
  - removed every time: `tests`, `docs`, `README.md`, `phpunit.xml`, `DemoFactorySeeder`, frontend build files
  - `vendor/` is copied from ours, then `composer install --no-dev --optimize-autoloader --no-scripts` runs in the staging folder. Offline and fast; the autoloader lists only the modules in the zip.
  - written per site: `storage/app/distribution/site.json`, `config/distribution.php` (our public keys as a literal list), `config/core.php` (`git describe --tags --always`), and `.env.example` with production placeholders (sqlite `:memory:`, file session and cache, no APP_KEY)
  - smoke test: `php artisan --version` inside the staging folder
- **The zip:** one top folder named after the site's slug, with Unix permissions (`artisan` is 0755). Stored at `storage/app/private/distribution/builds/`. It can be downloaded for 14 days (download requires a password confirmation). **Expired files aren't deleted yet**; pruning is Phase 6.
- **Install code:** 10 characters with no 0/O or 1/I/L, shown as `XXXXX-XXXXX`. The installer ignores case, dashes and spaces (`SiteManifest::normalizeCode`).
- **Tokens:** each build issues its own token (`build_id` on the token, `token_id` on the build). A failed build revokes its token.

### Phase 2 notes

- **Turn it on (our server only), all from the web:**
  1. Super Admin → Modules → install Distribution.
  2. Self-hosting → Releases → **Create signing key** (password confirmation). This writes `DISTRIBUTION_SIGNING_SECRET` and `DISTRIBUTION_PUBLIC_KEYS` to `.env`, rebuilds the config cache if there is one, and **shows the secret once for backup**. Without the secret, sites won't accept new releases.
  3. If `.env` isn't writable, the page shows the two lines to add by hand.
  4. Terminal equivalents: `core:module install Distribution`, `distribution:keygen`. Both paths use `SigningKeys::create()`.
  5. **Replacing a key is terminal only** (`distribution:keygen --force`), on purpose: sites built with the old key would stop accepting updates.
- **Config:** `config('distribution.*')` merges core `config/distribution.php` (shipped: `never_ship`, `public_keys`) with the module's `config/config.php` (`signing_secret`, `disk`, `directory`).
- **The signed text** is defined in core: `App\Support\Distribution\PackageSignature::message()` (`ish-module-v1\n{module}\n{version}\n{sha256}`), plus `verify()`. Phase 5 must use it as is.
- **All seven tables already exist** (migration `2026_10_31_100000_create_distribution_tables.php`), so phases 3–4 need no schema changes.
- **Releases:**
  - stored at `storage/app/private/distribution/releases/{Module}/{Module}-{version}.zip`, with one top folder `{Module}/`
  - left out: `tests`, `node_modules`, `.git`, symlinks
  - a version must be greater than **every** earlier release of that module, withdrawn ones included, so a version number is never reused
  - "latest" is decided by version number, not by date: `Release::latestFor()`
- **Tokens:** `SiteService::issueToken()` returns `ish_` + 48 hex characters and stores only its sha256 and the last 4 characters. Phase 3 calls it once per build.
- **Boundary guard:** `tests/Feature/ModuleBoundaryTest.php` fails if anything in core refers to Billing or Distribution. Exceptions: `bootstrap/cache` and `DemoFactorySeeder`; the build must strip the seeder.

### Phase 1 notes (what changed from the plan, and where things are)

- **Files:** `app/Support/Installer/{Installer,PreInstall,SiteManifest}.php`, `app/Services/Install/*` (`InstallRules` holds the validation shared by web and CLI), `app/Http/Controllers/Install/InstallController.php`, `routes/install.php`, `resources/views/install/*`, `resources/lang/en/install.php`, `app/Console/Commands/SetupCommand.php`, `config/core.php` (version, `dev` in the repo).
- **Servers set up before the installer existed** (ours, and every developer's machine) don't need `core:mark-installed`. On the first web request, `RedirectToInstaller` sees users in the database and writes `storage/installed` itself. `core:mark-installed` is still there for doing it by hand.
- **HTTPS:** `SecurityHeaders` now forces HTTPS only when `APP_ENV=production` **and** `APP_URL` starts with `https://`. A self-hosted copy on plain http (and any fresh copy reaching `/install`) is no longer redirected to an https address that may not exist.
- **Before install, only the web path forces file sessions and cache.** `PreInstall` skips the CLI. `InstallationService::applyRuntime()` switches the process to the `array` cache, because a terminal install starts before `.env` exists and the default cache store points at a SQLite file. Found in the real terminal run.
- **No symlinks:** `config/filesystems.php` reads `PUBLIC_DISK_IN_PUBLIC`. The installer sets it when `storage:link` fails.
- **Shared hosting:** `config('queue.via_scheduler')` (`QUEUE_VIA_SCHEDULER`) adds `queue:work --stop-when-empty` to the every-minute schedule. The web installer has this ticked by default; `core:setup` needs `--queue-via-scheduler`.
- **Resuming:** progress is saved in `storage/app/installing.json`. While it exists, a database that already has users is accepted (`InstallationService::resuming()`), because those users come from the stopped run.
- **The install code** is checked against `install_code_hash` in `site.json` with `password_verify` (upper-cased, trimmed). Phase 3 must hash it with `password_hash(strtoupper($code), PASSWORD_DEFAULT)`.
- **site.json fields read so far:** `site_uuid`, `server_url`, `token`, `modules`, `install_code_hash`. The install stores the first three as `distribution_site_uuid`, `distribution_server_url` and `distribution_token` (encrypted) in the `system` table, then deletes `token` from the file.
- **Not done in Phase 1:** the first check-in at the end of install. It needs the update client and comes with Phase 5.

## 10. Tests to write

**Core**

- Installer redirect before install and 404 after.
- `EnvWriter`: quoting, newline injection, keeps the key.
- `InstallationService` (sqlite):
  - migrates and creates the superadmin
  - installs modules in requirement order
  - writes the marker
  - refuses a database that already has users
  - resumes after a failure
- `core:setup --no-interaction`.
- Install code required.
- `SignatureVerifier`: tampered package, wrong sha, mismatched name or version.
- `SafeZipExtractor`: `../`, absolute paths, symlinks, more than one top folder.
- `ModulePackageInstaller` with a fixture module: install, update, rollback when a migration throws.
- `UpdateClient` with `Http::fake`: forged signature and wrong nonce rejected; auto vs manual.
- Guard: no core file references `Modules\Distribution` or `Modules\Billing`.

**Distribution**

- Superadmin only.
- Assigning a module adds its requirements; Billing and Distribution can't be assigned.
- Only token hashes are stored.
- API:
  - revoked or suspended token returns 401
  - only assigned and newer releases are offered
  - newly assigned modules are offered as `install`
  - downloading an unassigned release returns 404
  - reports update site modules
- A published release verifies against the public key.
- The build leaves out Billing, Distribution, tests and `.env`.

## 11. End-to-end check (when everything is built)

1. `distribution:keygen`.
2. Create a site with Savings, then `distribution:build`.
3. Unzip it into an empty folder and run `php -S` from `public/`.
4. Go through `/install` against MySQL. Log in and confirm Savings works and Billing is absent.
5. Do the same with `core:setup --no-interaction` against Postgres.
6. Bump Savings to 1.1.1 and run `distribution:publish Savings`.
   - In manual mode, **Update** appears and installs it.
   - In auto mode, `core:updates` installs it.
   - Our site page shows the new version and the report.
7. Publish a release with a broken migration. Confirm it rolls back to the old folder and version.

### End-to-end run, 31/10/2026 (Postgres, two real servers on localhost)

| Step | Result |
|---|---|
| Ours: Distribution on, keygen, site "Acme Finance" with Savings, publish, `distribution:build` | zip and install code |
| Customer: extract, web installer with the code, Postgres | installed; **first check-in reached us at once** (site shows pgsql, its URL, Savings 1.1.0 installed) |
| Publish Savings 1.1.1 (adds a table); site on **manual** | hourly check: "One update available", nothing installed; Updates page shows the changelog; admin clicks Install (with a password confirmation): 1.1.1 installed, table created, old version backed up; our side: `1.1.0 → 1.1.1 success (460 ms)` |
| Switch the site to **automatic**, publish 1.1.2 | installed by `core:updates` with no one clicking |
| Publish a **broken** 1.1.3 (second migration throws) | `rolled_back`: still 1.1.2, folder restored, **no table left from either new migration**, app still works, our side shows the error |
| Next hourly check | 1.1.3 is **not** retried automatically (`updates.not_retried`) |
| Give Delivery (brings SalesOrders and Inventory); publish them and a fixed Savings 1.1.4 | all four installed in order Inventory → SalesOrders → Delivery → Savings; modules that weren't in the zip load (fallback autoloader); their pages work |
| Schedules | customer: `core:updates` hourly, queue every minute (cron-only mode), `savings:alerts`; ours: `distribution:prune` daily |
| Revoke the site's token | customer's next check: "The update server answered 401. Invalid token." |

### End-to-end run, 31/10/2026 (MySQL 9.7.1, our side done in the browser)

| Step | Result |
|---|---|
| Ours, **all in the browser**: Create signing key (password confirmation, secret shown once), Publish Savings, Add site, Build zip (queue job, 7 s) | install code on the site page, zip ready to download |
| Customer: web installer on MySQL | a wrong DB password shows MySQL's error; installed (38 tables, 1 admin); first check-in reached us (`db=mysql`) |
| Manual 1.1.1 → automatic 1.1.2 | same as on Postgres |
| Broken 1.1.3: migration 1 creates a table; migration 2 **adds a column to `sav_accounts`**, creates a table, then throws | `rolled_back`: still 1.1.2; migration 1's table rolled back; **the column and the half-made table removed** by the failed migration's `down()`; no 1.1.3 migrations left recorded; app works |
| Next check | not retried automatically |
| Delivery (+ SalesOrders, Inventory) and fixed 1.1.4 | all installed in order; pages work; our history lists all 7 results |
| Revoke token | 401 on the next check |
| Second copy of the same zip: `core:setup --no-interaction` on MySQL | installed even though its token was revoked (the check-in is non-fatal); running it again: "already installed" |

## 12. Open points

- **HTTPS:** nothing enforces HTTPS for the update server address yet. Our `APP_URL` must be `https://` in production, because builds put it in every `site.json`.
- **Core self-update** (app/, vendor) is a later phase. Until then, a core change means a new zip for each site.
- Should a customer's admin be allowed to switch modules off on their server? They can today, through Super Admin → Modules, because they are superadmin there.
- Docker: `storage/` and `Modules/` must be persistent volumes for updates to survive a container restart. Otherwise use `manual` mode and ship new images.
