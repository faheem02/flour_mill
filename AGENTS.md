# Flour Mill Management System

## Overview
PHP/MySQL procedural app (no framework). Flat directory structure — not an MVC pattern. SB Admin 2 template.

## Setup
- DB: MySQL `flour_mill`, credentials hardcoded in `includes/db.php` (`root` / no password).
- Schema: `database/full_schema.sql` — run to create DB + seed data.
- Place under a web server root (XAMPP htdocs, etc.). No build step.
- Default login: `admin` / `admin123` (plain text in DB).

## Architecture
- **Entrypoint**: `index.php` redirects to `dashboard.php` (authenticated) or `auth/login.php`.
- **Page pattern**: `session_start()` → `require_once '../../includes/config.php'` → auth guard → set `$active_page` / `$page_title` → `require_once '../../includes/db.php'` → `include '../../includes/header.php'` → HTML → `include '../../includes/footer.php'`.
- **URL base**: `$base_url` computed dynamically in `includes/config.php` from `DOCUMENT_ROOT`.
- **Auth**: Session-based, plain-text password comparison.

## Modules
| Directory | Purpose |
|-----------|---------|
| `modules/purchases/` | Wheat purchase invoices |
| `modules/suppliers/` | Supplier CRUD, ledger, payment |
| `modules/production/` | Gandam crush records, extraction report |
| `modules/products/` | Finished goods (Atta, Maida, Suji, Bran) |
| `modules/stock/` | Stock ledger, adjustments |
| `modules/sales/` | Sale invoices, returns |
| `modules/customers/` | Customer CRUD, ledger, receipts |
| `modules/expenses/` | Expense categories, add/list |
| `modules/accounts/` | Cash book, bank book, general ledger |

## Key helpers (`includes/functions.php`)
- `sanitize()`, `money()`, `qty()` — formatting
- `generateVoucherNo()`, `generateInvoiceNo()`, `generatePurchaseNo()`, `generateProductionNo()` — auto-numbering
- `setFlash()` / `flashMessage()` — session-based flash messages
- `autoJournalEntry()` — double-entry accounting posting
- `navActive()` — sidebar highlight

## Conventions
- **DataTables**: Any `<table class="datatable">` auto-initializes in `footer.php` (sort desc by first column, 25 per page).
- **Flash messages**: `<div class="alert alert-success alert-auto">` auto-hides after 5s.
- **Transactions**: All multi-step DB operations use `begin_transaction()` / `commit()` / `rollback()`.
- **Sidebar**: Custom collapse logic in footer, color theme via CSS variables (`--navy`, `--gold`).
- **No tests, no CI, no build tools, no linting**.
