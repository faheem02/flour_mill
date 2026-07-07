# Flour Mill Management System

PHP/MySQL procedural app (no framework). SB Admin 2 template. Flat directory structure — not MVC.

## Setup
- **DB**: MySQL `flour_mill`, credentials hardcoded in `includes/db.php` (`root` / no password).
- **Schema**: `database/full_schema.sql` — run to create DB + seed data. Also has incremental phase SQL files (`phase1_masters.sql`–`phase5_payments.sql`) for staged setup.
- **Deploy**: Place under web server root (XAMPP htdocs). No build step.
- **Default login**: `admin` / `admin123` (plain text in DB).

## Architecture
- **Entrypoint**: `index.php` → `dashboard.php` (authenticated) or `auth/login.php`.
- **Page pattern**: `session_start()` → `require_once '../../includes/config.php'` → auth guard → set `$active_page` / `$page_title` → `require_once '../../includes/db.php'` → `include '../../includes/header.php'` → HTML → `include '../../includes/footer.php'`.
- **URL base** (`$base_url`): computed dynamically in `includes/config.php` from `DOCUMENT_ROOT`.
- **Auth**: Session-based, plain-text password comparison (prepared statements used though).

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
- **Sidebar**: Custom collapse logic in footer; CSS variables `--navy`, `--gold` for theme.
- **No tests, no CI, no build tools, no linting**.
