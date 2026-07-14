-- Bag Stock Management Migration
-- 1. Add rate_per_bag to bag_types
ALTER TABLE bag_types
ADD COLUMN rate_per_bag DECIMAL(10,2) DEFAULT 0.00 AFTER empty_bag_cost;

-- 2. Add bag_warehouse_id to booking_bags
ALTER TABLE booking_bags
ADD COLUMN bag_warehouse_id INT DEFAULT NULL AFTER bag_rate;

-- 3. Bag stock per warehouse (no bag_type — single type)
CREATE TABLE IF NOT EXISTS bag_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    qty INT DEFAULT 0,
    UNIQUE KEY uk_wh (warehouse_id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB;

-- 4. Bag stock ledger
CREATE TABLE IF NOT EXISTS bag_stock_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    warehouse_id INT NOT NULL,
    qty_in INT DEFAULT 0,
    qty_out INT DEFAULT 0,
    balance_qty INT DEFAULT 0,
    type VARCHAR(30) NOT NULL,
    reference_id INT DEFAULT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB;
