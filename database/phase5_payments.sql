-- Phase 5: Farmer Payment Tracking
-- Run: mysql -u root flour_mill < database/phase5_payments.sql

ALTER TABLE farmers ADD COLUMN balance DECIMAL(12,2) DEFAULT 0.00 AFTER city;

CREATE TABLE IF NOT EXISTS farmer_payments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id   INT NOT NULL,
    date        DATE NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    type        ENUM('advance','payment') DEFAULT 'payment',
    booking_id  INT DEFAULT NULL,
    notes       TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES farmers(id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id)
) ENGINE=InnoDB;
