# Flour Mill Management System

PHP/MySQL procedural app (no framework). SB Admin 2 template. Flat directory structure — not MVC.

## Setup
- **DB**: MySQL `flour_mill`, credentials hardcoded in `includes/db.php` (`root` / no password).
- **Schema**: `database/full_schema.sql` — run to create DB + seed data. Also has incremental phase SQL files (`phase1_masters.sql`–`phase5_payments.sql`) for staged setup. `database/run_phase3.php` is a one-off helper.
- **Deploy**: Place under web server root (XAMPP htdocs). No build step. No composer, no npm, no bundler.
- **Default login**: `admin` / `admin123` (plain text in DB, seeded in full_schema.sql:426).

## Architecture
- **Entrypoint**: `index.php` → `dashboard.php` (authenticated) or `auth/login.php`.
- **Page pattern** (module files under `modules/*/`): `session_start()` → `require_once '../../includes/config.php'` → inline auth guard → set `$active_page` / `$page_title` → `require_once '../../includes/db.php'` → `include '../../includes/header.php'` → HTML → `include '../../includes/footer.php'`.
- **`header.php` is itself an auth guard** — it checks `$_SESSION['user_id']` and redirects unauthenticated users. The inline guard before it is redundant but kept in every page.
- **`header.php` loads `functions.php`** — helpers (`sanitize()`, `money()`, `qty()`, `setFlash()`, `navActive()`) are available on every authenticated page without explicit inclusion.
- **`$asset_path`** (defined in `config.php` as `assets/sb-admin2/`): prepend this to reference SB Admin 2 assets (e.g. `$asset_path . 'js/sb-admin-2.min.js'`). Use `$base_url` for app pages.
- **Auth**: Session-based, plain-text password comparison (prepared statements used though). `auth/login.php` is standalone (no header/footer).

## Modules (14 directories)
| Directory | Sidebar label | Purpose |
|-----------|---------------|---------|
| `masters/` | Masters | Vehicles, Drivers, Bag Types, Brokers, Warehouses CRUD |
| `arrivals/` | Arrivals | Wheat arrival recording, register, raw material stock |
| `bookings/` | Booking | Wheat booking from farmers (booking_no BK-XXXX) |
| `farmers/` | Farmers | Farmer list, ledger, payments |
| `purchases/` | *(not in sidebar)* | Wheat purchase invoices — currently only `add.php.old` / `list.php.old` stubs |
| `suppliers/` | Suppliers | Supplier CRUD, ledger, payments |
| `production/` | Production | Gandam crush records, extraction report (production_no PROD-XXXX) |
| `products/` | Stock | Finished goods (Atta, Maida, Suji, Bran) |
| `stock/` | Stock | Stock ledger, adjustments, warehouse stock |
| `sales/` | Sales | Sale invoices, returns |
| `customers/` | Customers | Customer CRUD, ledger, receipts |
| `expenses/` | Expenses | Expense categories, add/list |
| `accounts/` | Accounts | Cash book, bank book, general ledger, plus extra pages (balance_sheet, profit_loss, trial_balance, journal_entry) not linked in sidebar |
| `reports/` | Reports | Daily summary |

## Key helpers (`includes/functions.php`)
- `sanitize()`, `money()`, `qty()` — formatting
- `generateVoucherNo()`, `generateInvoiceNo()`, `generatePurchaseNo()`, `generateProductionNo()`, `generateBookingNo()` — auto-numbering
- `setFlash()` / `flashMessage()` — session-based flash messages
- `autoJournalEntry()` — double-entry accounting posting (debits/credits arrays)
- `navActive()` — sidebar highlight

## Conventions
- **DataTables**: `<table class="datatable">` auto-initialized in `footer.php` (sort desc by first col, 25 per page).
- **Flash messages**: `<div class="alert alert-success alert-auto">` auto-hides after 5s.
- **Transactions**: All multi-step DB operations use `begin_transaction()` / `commit()` / `rollback()`.
- **Sidebar**: Custom collapse logic in footer; CSS variables `--navy`, `--gold` for theme. Activation variables (`$masters_active`, `$bookings_active`, etc.) are defined in `header.php` — update them when adding new sidebar items.
- **Single-file CRUD**: Master pages (vehicles, drivers, bags, etc.) combine add + edit in one file, distinguished by `$id` in the POST handler.
- **SQL style**: Mixes prepared statements (`bind_param`) and direct variable interpolation (via `sanitize()`). The `sanitize()` helper is always available (auto-loaded from `functions.php` via `header.php`).
- **No tests, no CI, no build tools, no linting**.
