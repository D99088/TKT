-- Run this SQL in phpMyAdmin to add email column to existing users table

ALTER TABLE users ADD COLUMN email VARCHAR(100) AFTER mobile;
