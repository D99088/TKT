-- Run this in phpMyAdmin SQL to add email column to users table
ALTER TABLE users ADD COLUMN email VARCHAR(100) AFTER mobile;
