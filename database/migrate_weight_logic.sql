-- Migration: wheat arrival stock uses actual_weight; booked_qty is wheat only
-- Fixes historical data so warehouse stock reflects physical (actual) weight received
-- and bookings list shows apples-to-apples pending (wheat booked vs wheat received).
-- Safe to run on a freshly-seeded DB too.

-- 1) booked_qty = bag_qty x bag_capacity (wheat only — remove katt from booked_qty)
UPDATE bookings b
JOIN booking_bags bb ON bb.booking_id = b.id
SET b.booked_qty = bb.quantity * bb.bag_capacity_kg;

-- 2) received_qty = SUM(gross_weight) of arrivals for that booking (wheat only)
UPDATE bookings b
SET b.received_qty = COALESCE(
    (SELECT SUM(gross_weight) FROM wheat_arrivals WHERE booking_id = b.id),
    0
);

-- 3) Recompute booking statuses based on new booked/received values
UPDATE bookings SET status = 'completed' WHERE received_qty > 0 AND received_qty >= booked_qty AND status NOT IN ('cancelled');
UPDATE bookings SET status = 'partial'  WHERE received_qty > 0 AND received_qty <  booked_qty AND status NOT IN ('cancelled');
UPDATE bookings SET status = 'pending'  WHERE received_qty <= 0 AND status NOT IN ('cancelled');

-- 4) Repoint arrival ledger entries to use actual_weight (or net fallback) as physical stock qty
UPDATE stock_ledger sl
JOIN wheat_arrivals a ON a.id = sl.reference_id AND sl.type = 'arrival'
JOIN products p ON sl.product_id = p.id
SET sl.qty_in = COALESCE(NULLIF(a.actual_weight, 0), a.net_weight)
WHERE p.name = 'Wheat (Gandam)';

-- 5) Rebuild warehouse_stock for the wheat product straight from the ledger
DELETE ws FROM warehouse_stock ws
JOIN products p ON ws.product_id = p.id
WHERE p.name = 'Wheat (Gandam)';

INSERT INTO warehouse_stock (warehouse_id, product_id, stock_qty)
SELECT sl.warehouse_id, sl.product_id, SUM(sl.qty_in - sl.qty_out) AS bal
FROM stock_ledger sl
JOIN products p ON sl.product_id = p.id
WHERE p.name = 'Wheat (Gandam)'
GROUP BY sl.warehouse_id, sl.product_id
ON DUPLICATE KEY UPDATE stock_qty = VALUES(stock_qty);

-- 6) Rebuild global products.stock_qty (aggregate over warehouse_stock) for wheat
UPDATE products p
SET p.stock_qty = (SELECT COALESCE(SUM(stock_qty), 0) FROM warehouse_stock WHERE product_id = p.id)
WHERE p.name = 'Wheat (Gandam)';