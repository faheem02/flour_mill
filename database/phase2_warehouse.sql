-- Phase 2: Warehouse tracking + Wheat as product + Arrivals
-- Run this to set up warehouse-wise stock tracking and wheat arrivals

-- Add Wheat (Gandam) as a raw material product if not exists
INSERT IGNORE INTO products (name, category, unit, sale_price, stock_qty)
SELECT 'Wheat (Gandam)', 'Raw Material', 'KG', 0, 0
WHERE NOT EXISTS (SELECT 1 FROM products WHERE name = 'Wheat (Gandam)');

-- Warehouse stock table (product-wise stock per warehouse)
CREATE TABLE IF NOT EXISTS warehouse_stock (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id    INT NOT NULL,
    product_id      INT NOT NULL,
    stock_qty       DECIMAL(12,3) DEFAULT 0.000,
    UNIQUE KEY (warehouse_id, product_id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- Add warehouse_id to stock_ledger
SET @exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = 'flour_mill' AND TABLE_NAME = 'stock_ledger' AND COLUMN_NAME = 'warehouse_id');
SET @sql = IF(@exists = 0, 
    'ALTER TABLE stock_ledger ADD COLUMN warehouse_id INT DEFAULT NULL AFTER reference_id',
    'SELECT "warehouse_id already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key if not exists
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = 'flour_mill' AND TABLE_NAME = 'stock_ledger' AND CONSTRAINT_NAME LIKE '%warehouse%');
SET @sql2 = IF(@fk_exists = 0,
    'ALTER TABLE stock_ledger ADD FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)',
    'SELECT "FK already exists"');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Wheat arrivals table
CREATE TABLE IF NOT EXISTS wheat_arrivals (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    date            DATE NOT NULL,
    supplier_id     INT DEFAULT NULL,
    warehouse_id    INT DEFAULT NULL,
    vehicle_id      INT DEFAULT NULL,
    driver_id       INT DEFAULT NULL,
    bag_type_id     INT DEFAULT NULL,
    num_bags        INT DEFAULT 0,
    gross_weight    DECIMAL(12,3) DEFAULT 0,
    bag_weight      DECIMAL(12,3) DEFAULT 0,
    net_weight      DECIMAL(12,3) DEFAULT 0,
    moisture_pct    DECIMAL(5,2) DEFAULT 0,
    quality_grade   VARCHAR(50),
    broker_id       INT DEFAULT NULL,
    notes           TEXT,
    status          ENUM('pending','completed','cancelled') DEFAULT 'completed',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (driver_id) REFERENCES drivers(id),
    FOREIGN KEY (bag_type_id) REFERENCES bag_types(id),
    FOREIGN KEY (broker_id) REFERENCES brokers(id)
) ENGINE=InnoDB;
