-- Phase 4: Farmers table (for booking system)

CREATE TABLE IF NOT EXISTS farmers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    phone       VARCHAR(20),
    village     VARCHAR(100),
    city        VARCHAR(100),
    status      ENUM('active','inactive') DEFAULT 'active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Migrate bookings: supplier_id -> farmer_id
ALTER TABLE bookings DROP FOREIGN KEY bookings_ibfk_1;
ALTER TABLE bookings CHANGE supplier_id farmer_id INT NOT NULL;
ALTER TABLE bookings ADD FOREIGN KEY (farmer_id) REFERENCES farmers(id);
