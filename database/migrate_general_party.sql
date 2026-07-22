-- General Party Module
-- Run this migration to create general_parties and party_transactions tables

CREATE TABLE IF NOT EXISTS general_parties (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    phone           VARCHAR(20),
    address         TEXT,
    opening_balance DECIMAL(12,2) DEFAULT 0.00,
    balance         DECIMAL(12,2) DEFAULT 0.00,
    status          ENUM('active','inactive') DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS party_transactions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    party_id        INT NOT NULL,
    date            DATE NOT NULL,
    type            ENUM('payable','receivable','paid') NOT NULL,
    amount          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance_after   DECIMAL(12,2) DEFAULT 0.00,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (party_id) REFERENCES general_parties(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Add General Party accounts to Chart of Accounts
INSERT INTO chart_of_accounts (code, name, type, parent_id, balance, status)
SELECT '6-1000', 'General Party Payable', 'Liability', 6, 0.00, 'active'
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE code = '6-1000');

INSERT INTO chart_of_accounts (code, name, type, parent_id, balance, status)
SELECT '7-1000', 'General Party Receivable', 'Asset', 7, 0.00, 'active'
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE code = '7-1000');
