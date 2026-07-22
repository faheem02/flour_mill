-- Add rate column to bag_stock_ledger
ALTER TABLE bag_stock_ledger ADD COLUMN rate DECIMAL(10,2) DEFAULT 0 AFTER balance_qty;
