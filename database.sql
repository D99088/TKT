-- Create database
CREATE DATABASE IF NOT EXISTS mtkt_db;

USE mtkt_db;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    mobile VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample data (optional)
-- INSERT INTO users (username, mobile, password) VALUES ('test', '1234567890', '$2y$10$examplehashedpassword');
