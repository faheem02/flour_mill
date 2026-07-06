-- Flour Mill Management System - Complete Database Schema
-- Double-Entry Accounting with Production Tracking

CREATE DATABASE IF NOT EXISTS flour_mill CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE flour_mill;

-- ============================================================
-- 1. USERS
-- ============================================================
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    name        VARCHAR(100),
    role        ENUM('admin','user') DEFAULT 'user',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. SUPPLIERS (Wheat sellers)
-- ============================================================
CREATE TABLE suppliers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    phone           VARCHAR(20),
    address         TEXT,
    opening_balance DECIMAL(12,2) DEFAULT 0.00,
    balance         DECIMAL(12,2) DEFAULT 0.00,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 3. SUPPLIER LEDGER
-- ============================================================
CREATE TABLE supplier_ledger (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id     INT NOT NULL,
    date            DATE NOT NULL,
    type            ENUM('purchase','payment','return','opening') NOT NULL,
    reference_id    INT DEFAULT NULL,
    debit           DECIMAL(12,2) DEFAULT 0.00,
    credit          DECIMAL(12,2) DEFAULT 0.00,
    balance         DECIMAL(12,2) DEFAULT 0.00,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 4. PURCHASES (Wheat purchase invoices)
-- ============================================================
CREATE TABLE purchases (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id    INT NOT NULL,
    date           DATE NOT NULL,
    invoice_no     VARCHAR(50),
    total_qty      DECIMAL(12,3) DEFAULT 0.000,
    rate_per_kg    DECIMAL(10,2) DEFAULT 0.00,
    total_amount   DECIMAL(12,2) DEFAULT 0.00,
    paid_amount    DECIMAL(12,2) DEFAULT 0.00,
    status         ENUM('pending','completed','returned') DEFAULT 'completed',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB;

-- ============================================================
-- 5. PURCHASE RETURNS
-- ============================================================
CREATE TABLE purchase_returns (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id    INT NOT NULL,
    supplier_id    INT NOT NULL,
    date           DATE NOT NULL,
    qty            DECIMAL(12,3) DEFAULT 0.000,
    amount         DECIMAL(12,2) DEFAULT 0.00,
    reason         TEXT,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB;

-- ============================================================
-- 6. PRODUCTS (Finished goods: Atta, Maida, Suji, Bran, etc.)
-- ============================================================
CREATE TABLE products (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    category       VARCHAR(50),
    unit           VARCHAR(20) DEFAULT 'KG',
    sale_price     DECIMAL(10,2) DEFAULT 0.00,
    stock_qty      DECIMAL(12,3) DEFAULT 0.000,
    status         ENUM('active','inactive') DEFAULT 'active',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 7. PRODUCTIONS (Gandam crush record)
-- ============================================================
CREATE TABLE productions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    date            DATE NOT NULL,
    wheat_qty       DECIMAL(12,3) NOT NULL,
    wheat_purchase_id INT DEFAULT NULL,
    total_output    DECIMAL(12,3) DEFAULT 0.000,
    extraction_rate DECIMAL(5,2) DEFAULT 0.00,
    wastage_qty     DECIMAL(12,3) DEFAULT 0.000,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wheat_purchase_id) REFERENCES purchases(id)
) ENGINE=InnoDB;

-- ============================================================
-- 8. PRODUCTION ITEMS (Output of each product)
-- ============================================================
CREATE TABLE production_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    production_id   INT NOT NULL,
    product_id      INT NOT NULL,
    qty             DECIMAL(12,3) NOT NULL,
    rate_per_kg     DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (production_id) REFERENCES productions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ============================================================
-- 9. CUSTOMERS (Wholesale buyers: retailers, bakeries, etc.)
-- ============================================================
CREATE TABLE customers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    phone           VARCHAR(20),
    address         TEXT,
    business_name   VARCHAR(150),
    opening_balance DECIMAL(12,2) DEFAULT 0.00,
    balance         DECIMAL(12,2) DEFAULT 0.00,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 10. CUSTOMER LEDGER
-- ============================================================
CREATE TABLE customer_ledger (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT NOT NULL,
    date            DATE NOT NULL,
    type            ENUM('sale','receipt','return','opening') NOT NULL,
    reference_id    INT DEFAULT NULL,
    debit           DECIMAL(12,2) DEFAULT 0.00,
    credit          DECIMAL(12,2) DEFAULT 0.00,
    balance         DECIMAL(12,2) DEFAULT 0.00,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 11. SALES (Flour sale invoices)
-- ============================================================
CREATE TABLE sales (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT NOT NULL,
    date            DATE NOT NULL,
    invoice_no      VARCHAR(50),
    total_qty       DECIMAL(12,3) DEFAULT 0.000,
    total_amount    DECIMAL(12,2) DEFAULT 0.00,
    paid_amount     DECIMAL(12,2) DEFAULT 0.00,
    status          ENUM('pending','completed','returned') DEFAULT 'completed',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB;

-- ============================================================
-- 12. SALE ITEMS
-- ============================================================
CREATE TABLE sale_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    sale_id         INT NOT NULL,
    product_id      INT NOT NULL,
    qty             DECIMAL(12,3) NOT NULL,
    rate            DECIMAL(10,2) NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ============================================================
-- 13. SALE RETURNS
-- ============================================================
CREATE TABLE sale_returns (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    sale_id         INT NOT NULL,
    customer_id     INT NOT NULL,
    date            DATE NOT NULL,
    product_id      INT NOT NULL,
    qty             DECIMAL(12,3) NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    reason          TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ============================================================
-- 14. STOCK LEDGER (Product-wise stock movement)
-- ============================================================
CREATE TABLE stock_ledger (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    product_id      INT NOT NULL,
    date            DATE NOT NULL,
    type            ENUM('production','sale','sale_return','adjustment','opening') NOT NULL,
    reference_id    INT DEFAULT NULL,
    qty_in          DECIMAL(12,3) DEFAULT 0.000,
    qty_out         DECIMAL(12,3) DEFAULT 0.000,
    balance_qty     DECIMAL(12,3) DEFAULT 0.000,
    rate            DECIMAL(10,2) DEFAULT 0.00,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ============================================================
-- 15. STOCK ADJUSTMENTS
-- ============================================================
CREATE TABLE stock_adjustments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    date            DATE NOT NULL,
    product_id      INT NOT NULL,
    type            ENUM('shortage','excess','damage') NOT NULL,
    qty             DECIMAL(12,3) NOT NULL,
    reason          TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ============================================================
-- 16. EXPENSE CATEGORIES
-- ============================================================
CREATE TABLE expense_categories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    description     TEXT,
    status          ENUM('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB;

-- ============================================================
-- 17. EXPENSES
-- ============================================================
CREATE TABLE expenses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    category_id     INT NOT NULL,
    date            DATE NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    paid_to         VARCHAR(150),
    payment_type    ENUM('cash','bank') DEFAULT 'cash',
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id)
) ENGINE=InnoDB;

-- ============================================================
-- 18. CHART OF ACCOUNTS (Double-Entry Accounting)
-- ============================================================
CREATE TABLE chart_of_accounts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(20) NOT NULL UNIQUE,
    name            VARCHAR(150) NOT NULL,
    type            ENUM('asset','liability','equity','income','expense') NOT NULL,
    parent_id       INT DEFAULT NULL,
    opening_balance DECIMAL(12,2) DEFAULT 0.00,
    balance         DECIMAL(12,2) DEFAULT 0.00,
    status          ENUM('active','inactive') DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES chart_of_accounts(id)
) ENGINE=InnoDB;

-- ============================================================
-- 19. JOURNAL ENTRIES (Vouchers)
-- ============================================================
CREATE TABLE journal_entries (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    date            DATE NOT NULL,
    voucher_no      VARCHAR(50) NOT NULL UNIQUE,
    description     TEXT,
    total_debit     DECIMAL(12,2) DEFAULT 0.00,
    total_credit    DECIMAL(12,2) DEFAULT 0.00,
    created_by      INT DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ============================================================
-- 20. JOURNAL ENTRY ITEMS
-- ============================================================
CREATE TABLE journal_entry_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    journal_id      INT NOT NULL,
    account_id      INT NOT NULL,
    debit           DECIMAL(12,2) DEFAULT 0.00,
    credit          DECIMAL(12,2) DEFAULT 0.00,
    notes           TEXT,
    FOREIGN KEY (journal_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(id)
) ENGINE=InnoDB;

-- ============================================================
-- 21. BANK ACCOUNTS
-- ============================================================
CREATE TABLE bank_accounts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    account_name    VARCHAR(150) NOT NULL,
    bank_name       VARCHAR(150),
    account_no      VARCHAR(50),
    opening_balance DECIMAL(12,2) DEFAULT 0.00,
    balance         DECIMAL(12,2) DEFAULT 0.00,
    status          ENUM('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB;

-- ============================================================
-- DEFAULT DATA
-- ============================================================

-- Default Admin User (password: admin123)
INSERT INTO users (username, password, name, role) VALUES
('admin', 'admin123', 'Administrator', 'admin');

-- Default Products (Flour Mill)
INSERT INTO products (name, category, unit, sale_price) VALUES
('Atta (Whole Wheat)', 'Flour', 'KG', 45.00),
('Maida (White Flour)', 'Flour', 'KG', 50.00),
('Suji (Semolina)', 'Flour', 'KG', 55.00),
('Bran (Choker)', 'By-Product', 'KG', 20.00),
('Fine Atta', 'Flour', 'KG', 48.00),
('Coarse Maida', 'Flour', 'KG', 47.00);

-- Default Expense Categories
INSERT INTO expense_categories (name, description) VALUES
('Electricity', 'Electricity bills'),
('Labor', 'Labor wages'),
('Transport', 'Transportation & freight'),
('Maintenance', 'Machine maintenance & repair'),
('Rent', 'Building rent'),
('Packaging', 'Packaging materials (bags, etc.)'),
('Miscellaneous', 'Other expenses');

-- Default Chart of Accounts (Double-Entry)
INSERT INTO chart_of_accounts (code, name, type, parent_id) VALUES
('1', 'Assets', 'asset', NULL),
('1-1000', 'Cash Account', 'asset', 1),
('1-2000', 'Bank Accounts', 'asset', 1),
('1-3000', 'Accounts Receivable', 'asset', 1),
('1-4000', 'Stock Inventory', 'asset', 1),
('2', 'Liabilities', 'liability', NULL),
('2-1000', 'Accounts Payable', 'liability', 6),
('2-2000', 'Supplier Payable', 'liability', 6),
('3', 'Equity', 'equity', NULL),
('3-1000', 'Owner Capital', 'equity', 9),
('3-2000', 'Retained Earnings', 'equity', 9),
('4', 'Income', 'income', NULL),
('4-1000', 'Sales Revenue', 'income', 12),
('4-2000', 'Other Income', 'income', 12),
('5', 'Expenses', 'expense', NULL),
('5-1000', 'Cost of Goods Sold', 'expense', 15),
('5-2000', 'Purchase of Wheat', 'expense', 15),
('5-3000', 'Electricity Expense', 'expense', 15),
('5-4000', 'Labor Expense', 'expense', 15),
('5-5000', 'Transport Expense', 'expense', 15),
('5-6000', 'Maintenance Expense', 'expense', 15),
('5-7000', 'Rent Expense', 'expense', 15),
('5-8000', 'Packaging Expense', 'expense', 15),
('5-9000', 'Miscellaneous Expense', 'expense', 15);

-- Default Bank Account
INSERT INTO bank_accounts (account_name, bank_name, account_no) VALUES
('Main Cash', 'Cash', 'CASH-001'),
('HBL Current Account', 'Habib Bank Ltd', 'HBL-1234-5678');
