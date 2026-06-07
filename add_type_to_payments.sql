ALTER TABLE payments ADD COLUMN type ENUM('credit', 'debit') DEFAULT 'credit' AFTER amount;
