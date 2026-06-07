-- Create admin_users table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    mobile VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin user (password: admin123)
-- INSERT INTO admin_users (username, mobile, password) VALUES ('Admin', '1234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
