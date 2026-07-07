-- Phase 1: Masters tables for Flour Mill Management System

CREATE TABLE IF NOT EXISTS vehicles (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_no      VARCHAR(50) NOT NULL,
    vehicle_type    VARCHAR(50),
    owner_name      VARCHAR(150),
    driver_name     VARCHAR(150),
    driver_mobile   VARCHAR(20),
    capacity_kg     DECIMAL(12,2) DEFAULT 0,
    status          ENUM('active','inactive') DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS drivers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    cnic            VARCHAR(20),
    mobile          VARCHAR(20),
    license_no      VARCHAR(50),
    address         TEXT,
    status          ENUM('active','inactive') DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bag_types (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    bag_weight_kg   DECIMAL(10,2) DEFAULT 0,
    empty_bag_cost  DECIMAL(10,2) DEFAULT 0,
    status          ENUM('active','inactive') DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS brokers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    commission_rate DECIMAL(5,2) DEFAULT 0,
    mobile          VARCHAR(20),
    address         TEXT,
    status          ENUM('active','inactive') DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS warehouses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(20) NOT NULL,
    name            VARCHAR(150) NOT NULL,
    location        VARCHAR(200),
    capacity_kg     DECIMAL(12,2) DEFAULT 0,
    type            ENUM('wheat','finished','packing','general') DEFAULT 'general',
    status          ENUM('active','inactive') DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
