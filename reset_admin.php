<?php
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "mtkt_db";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Delete existing admin if any
$conn->query("DELETE FROM admin_users WHERE mobile = '1234567890'");

// Create new admin with fresh password hash
$hashed = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO admin_users (username, mobile, password) VALUES (?, ?, ?)");
$username = 'Admin';
$mobile = '1234567890';
$stmt->bind_param("sss", $username, $mobile, $hashed);

if ($stmt->execute()) {
    echo "Admin created successfully!<br>";
    echo "Login credentials:<br>";
    echo "Mobile: 1234567890<br>";
    echo "Password: admin123<br>";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
