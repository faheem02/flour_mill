-- ============================================================
-- FLOUR MILL DATABASE RESET
-- Keeps: users, products, expense_categories, chart_of_accounts, bank_accounts, bag_types
-- Clears: everything else (farmers, bookings, arrivals, sales, stock, journals, etc.)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Master data (user-created)
TRUNCATE TABLE farmers;
TRUNCATE TABLE suppliers;
TRUNCATE TABLE vehicles;
TRUNCATE TABLE drivers;
TRUNCATE TABLE brokers;
TRUNCATE TABLE warehouses;

-- Booking flow
TRUNCATE TABLE booking_bags;
TRUNCATE TABLE bookings;
TRUNCATE TABLE farmer_payments;
TRUNCATE TABLE supplier_ledger;
TRUNCATE TABLE purchases;
TRUNCATE TABLE purchase_returns;

-- Arrivals & Production
TRUNCATE TABLE wheat_arrivals;
TRUNCATE TABLE production_items;
TRUNCATE TABLE productions;

-- Customers & Sales
TRUNCATE TABLE customer_ledger;
TRUNCATE TABLE customers;
TRUNCATE TABLE sale_items;
TRUNCATE TABLE sales;
TRUNCATE TABLE sale_returns;

-- Stock
TRUNCATE TABLE warehouse_stock;
TRUNCATE TABLE warehouse_transfers;
TRUNCATE TABLE stock_ledger;
TRUNCATE TABLE stock_adjustments;

-- Bag Stock
TRUNCATE TABLE bag_stock;
TRUNCATE TABLE bag_stock_ledger;

-- Expenses
TRUNCATE TABLE expenses;

-- Accounts
TRUNCATE TABLE journal_entries;
TRUNCATE TABLE journal_entry_items;

SET FOREIGN_KEY_CHECKS = 1;

-- Reset product stock to 0
UPDATE products SET stock_qty = 0.000;

-- Reset chart of accounts balances to 0
UPDATE chart_of_accounts SET balance = 0.00, opening_balance = 0.00;

-- Reset bank accounts balances to 0
UPDATE bank_accounts SET balance = 0.00, opening_balance = 0.00;
