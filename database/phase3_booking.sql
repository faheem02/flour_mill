-- Phase 3: Replace Purchases with Booking system

-- Bookings table
CREATE TABLE IF NOT EXISTS bookings (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    booking_no      VARCHAR(50) NOT NULL UNIQUE,
    supplier_id     INT NOT NULL,
    date            DATE NOT NULL,
    booked_qty      DECIMAL(12,3) NOT NULL DEFAULT 0,
    received_qty    DECIMAL(12,3) DEFAULT 0,
    rate            DECIMAL(10,2) DEFAULT 0,
    advance_amount  DECIMAL(12,2) DEFAULT 0,
    expected_date   DATE DEFAULT NULL,
    status          ENUM('pending','partial','completed','cancelled') DEFAULT 'pending',
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB;

-- Add booking_id to wheat_arrivals
ALTER TABLE wheat_arrivals ADD COLUMN booking_id INT DEFAULT NULL AFTER id;
ALTER TABLE wheat_arrivals ADD FOREIGN KEY (booking_id) REFERENCES bookings(id);
