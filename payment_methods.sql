CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('upi', 'bank') NOT NULL,
    label VARCHAR(100) NOT NULL,
    upi_id VARCHAR(100) DEFAULT '',
    upi_name VARCHAR(100) DEFAULT '',
    bank_name VARCHAR(100) DEFAULT '',
    account_name VARCHAR(100) DEFAULT '',
    account_number VARCHAR(50) DEFAULT '',
    ifsc VARCHAR(50) DEFAULT '',
    qr_code TEXT DEFAULT '',
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
