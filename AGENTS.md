# Flour Mill Management System

PHP/MySQL procedural app (no framework). SB Admin 2 template. Flat directory structure — not MVC.

## Setup
- **DB**: MySQL `flour_mill`, credentials hardcoded in `includes/db.php` (`root` / no password).
- **Schema**: `database/full_schema.sql` — run to create DB + seed data. Also has incremental phase SQL files (`phase1_masters.sql`–`phase6_booking_bags.sql`) for staged setup. Migrations: `migrate_warehouse_type.sql` (adds `mill` warehouse type), `migrate_bag_stock.sql` (creates bag_stock tables), `migrate_sale_freight.sql` (adds freight/delivery columns to sales), `migrate_bag_action.sql` (adds return/purchase enum to booking_bags), `migrate_bag_rate.sql` (adds rate column to bag_stock_ledger), `migrate_general_party.sql` (creates general_parties + party_transactions tables), `migrate_weight_logic.sql` (fixes arrival stock to use actual_weight). Helpers: `run_phase3.php`, `run_phase6.php`, `run_phase7.php`. Reset: `database/reset_data.sql` — truncates transactional data, keeps users/products/accounts/master config.
- **Deploy**: Place under web server root (XAMPP htdocs). No build step. No composer, no npm, no bundler.
- **Default login**: `admin` / `admin123` (plain text in DB, seeded in full_schema.sql:509).

## Architecture
- **Entrypoint**: `index.php` → `dashboard.php` (authenticated) or `auth/login.php`.
- **Page pattern** (module files under `modules/*/`): `session_start()` → `require_once '../../includes/config.php'` → inline auth guard → set `$active_page` / `$page_title` → `require_once '../../includes/db.php'` → `include '../../includes/header.php'` → HTML → `include '../../includes/footer.php'`.
  - **`$active_page` MUST be set BEFORE including `header.php`** — the header uses it to set sidebar highlighting variables. If you forget, the sidebar won't highlight correctly.
- **`header.php` is itself an auth guard** — it checks `$_SESSION['user_id']` and redirects unauthenticated users. The inline guard before it is redundant but kept in every page.
- **`header.php` loads `functions.php`** — helpers (`sanitize()`, `money()`, `qty()`, `setFlash()`, `navActive()`) are available on every authenticated page without explicit inclusion. Some module files also explicitly `require_once '../../includes/functions.php'` before header (redundant but harmless).
- **`$asset_path`** (defined in `config.php` as `$base_url . 'assets/sb-admin2/'`): full URL prefix — prepend to reference SB Admin 2 assets (e.g. `$asset_path . 'js/sb-admin-2.min.js'`). Use `$base_url` for app pages.
- **Auth**: Session-based, plain-text password comparison (prepared statements used though). `auth/login.php` is standalone (no header/footer).

## Modules (15 directories)
| Directory | Sidebar section | Purpose |
|-----------|----------------|---------|
| `masters/` | Warehouse | Vehicles, Drivers, Bags, Brokers, Warehouses CRUD |
| `arrivals/` | Wheat Arrival | Wheat arrival recording, register, raw material stock |
| `bookings/` | Booking | Wheat booking from farmers (booking_no BK-XXXX) |
| `farmers/` | Farmers | Farmer list, ledger, payments |
| `purchases/` | *(not in sidebar)* | Wheat purchase invoices — currently only `add.php.old` / `list.php.old` stubs |
| `production/` | Production | Gandam crush records, extraction report (production_no PROD-XXXX) |
| `products/` | Product Stock | Finished goods (Atta, Maida, Suji, Bran) — inline add form |
| `stock/` | Warehouse / Production / Product Stock | Stock ledger, adjustments, warehouse stock, issuance/transfer |
| `bags/` | Bag Stock | Bag IN/OUT, adjustment, ledger |
| `sales/` | Sales | Sale invoices, returns |
| `customers/` | Customers | Customer CRUD, ledger, receipts |
| `expenses/` | Expenses | Expense add/list |
| `accounts/` | Accounts (2 of 8 pages linked) | Cash book, bank book |
| `parties/` | General Party | Party list, received/paid entries, ledger |
| `reports/` | Reports | Daily summary |

**Cross-module sidebar links** — some pages are linked under a different section than their directory:
- `stock/warehouse_stock.php` → linked under **Warehouse** sidebar section
- `stock/issuance.php` → linked under **Production** sidebar section
- `stock/ledger.php`, `stock/adjustment.php` → linked under **Product Stock** sidebar section

**Orphaned pages** (exist on disk, not in sidebar): `masters/vehicles.php`, `drivers.php`, `bags.php`, `brokers.php`, `warehouse_view.php`; `accounts/balance_sheet.php`, `journal_entry.php`, `profit_loss.php`, `trial_balance.php`. These are functional but undiscoverable from the UI.

**AJAX/data fetchers** (no header/footer): `masters/*_delete.php` (5 files), `products/add_ajax.php`, `arrivals/delete.php`, `arrivals/get_drivers.php`, `arrivals/get_booking_json.php`, `arrivals/arrival_json.php`, `bookings/get_farmers.php`, `bookings/ajax_add_farmer.php`, `bookings/status_update.php`, `stock/get_warehouse_stock.php`, `bags/get_bag_stock.php`, `sales/get_warehouse_products.php`, `sales/sale_json.php`, `sales/delete.php`.

**Sub-pages** (not in sidebar but linked from other pages): `arrivals/edit.php`, `arrivals/print.php`, `arrivals/print_list.php`, `bookings/view.php`, `bookings/payment.php`, `bookings/farmers.php`, `bookings/print_list.php`, `sales/edit.php`, `sales/print.php`, `sales/print_list.php`, `production/print_list.php`, `customers/print_list.php`, `farmers/print_list.php`, `bags/print_ledger.php`, `stock/print_warehouse_stock.php`, `stock/print_ledger.php`, `accounts/print_cash_book.php`, `accounts/print_bank_book.php`.

## Key helpers (`includes/functions.php`)
- `sanitize()`, `money()`, `qty()` — formatting
- `generateVoucherNo()`, `generateInvoiceNo()`, `generatePurchaseNo()`, `generateProductionNo()`, `generateBookingNo()` — auto-numbering
- `setFlash()` / `flashMessage()` — session-based flash messages
- `autoJournalEntry($date, $description, $debits, $credits, $created_by)` — double-entry accounting posting. `$debits`/`$credits` are associative arrays `[account_id => amount]`. Returns journal_id or false on failure.
- `navActive()` — sidebar highlight

## Conventions
- **DataTables**: `<table class="datatable">` auto-initialized in `footer.php` (sort desc by first col, 25 per page). Print auto-expands to show all rows.
- **Flash messages**: `<div class="alert alert-success alert-auto">` auto-hides after 5s.
- **Transactions**: All multi-step DB operations use `begin_transaction()` / `commit()` / `rollback()`.
- **Sidebar**: Custom collapse logic in footer; CSS variables `--navy`, `--gold` for theme. The sidebar is defined inline in `header.php` (no separate sidebar file). Activation variables (`$masters_active`, `$bookings_active`, etc.) are defined in `header.php` — update them when adding new sidebar items.
- **Single-file CRUD**: Master pages (vehicles, drivers, bags, etc.) combine add + edit in one file, distinguished by `$id` in the POST handler.
- **SQL style**: Mixes prepared statements (`bind_param`) and direct variable interpolation (via `sanitize()`). The `sanitize()` helper is always available (auto-loaded from `functions.php` via `header.php`).
- **No tests, no CI, no build tools, no linting**.

## Stock Flow & Warehouse Types
Warehouses have a `type` ENUM: `wheat` (storage), `mill` (production), `finished` (dispatch), `general`.

**Production flow:**
1. Wheat arrives at a `wheat` warehouse via Arrivals module
2. Wheat is transferred to a `mill` warehouse via Issuance/Transfer page
3. Production consumes wheat FROM the mill warehouse → output products added TO the same mill warehouse
4. Finished products are transferred from mill to `finished` warehouse via Issuance (or sold directly from mill)
5. Sales deduct from either `mill` or `finished` warehouse

**Key invariant:** `products.stock_qty` is the global aggregate. `warehouse_stock` tracks per-warehouse per-product stock. Both must be updated in every stock movement.
