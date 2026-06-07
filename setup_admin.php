<?php
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "mtkt_db";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    mobile VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table admin_users created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

$check = $conn->query("SELECT id FROM admin_users WHERE mobile = '1234567890'");
if ($check->num_rows == 0) {
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admin_users (username, mobile, password) VALUES (?, ?, ?)");
    $username = 'Admin';
    $mobile = '1234567890';
    $stmt->bind_param("sss", $username, $mobile, $hashed_password);
    
    if ($stmt->execute()) {
        echo "Admin user created successfully<br>";
        echo "Login credentials:<br>";
        echo "Mobile: 1234567890<br>";
        echo "Password: admin123<br>";
    } else {
        echo "Error creating admin user: " . $stmt->error . "<br>";
    }
    $stmt->close();
} else {
    echo "Admin user already exists<br>";
}

$conn->close();
?>
