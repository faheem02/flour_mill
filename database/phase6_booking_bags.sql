-- ============================================================
-- Phase 6: Booking Bags, Moisture, Katt
-- Adds bag tracking, moisture %, and katt to booking system
-- ============================================================

-- New table: booking_bags
CREATE TABLE IF NOT EXISTS booking_bags (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    booking_id      INT NOT NULL,
    bag_type_id     INT NOT NULL,
    quantity        INT NOT NULL DEFAULT 0,
    bag_capacity_kg DECIMAL(10,2) DEFAULT 50.000,
    ownership       ENUM('company','farmer') NOT NULL DEFAULT 'company',
    bag_rate        DECIMAL(10,2) DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (bag_type_id) REFERENCES bag_types(id)
) ENGINE=InnoDB;

-- New columns in bookings table
ALTER TABLE bookings
    ADD COLUMN moisture_percent DECIMAL(5,2) DEFAULT 0 AFTER advance_amount,
    ADD COLUMN katt_per_bag DECIMAL(5,3) DEFAULT 0 AFTER moisture_percent;
