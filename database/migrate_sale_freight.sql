-- Add delivery type, freight amount, warehouse_id and notes to sales
ALTER TABLE sales
ADD COLUMN warehouse_id INT DEFAULT NULL AFTER customer_id,
ADD COLUMN delivery_type ENUM('delivery','pickup') DEFAULT 'pickup' AFTER status,
ADD COLUMN freight_amount DECIMAL(12,2) DEFAULT 0.00 AFTER paid_amount,
ADD COLUMN notes TEXT AFTER driver_mobile;
