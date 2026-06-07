<?php
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "mtkt_db";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create admin_main table
$sql = "CREATE TABLE IF NOT EXISTS admin_main (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    mobile VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Table admin_main created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Check if main admin exists
$check = $conn->query("SELECT id FROM admin_main WHERE mobile = '9999999999'");
if ($check->num_rows == 0) {
    $hashed_password = password_hash('mainadmin', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admin_main (username, mobile, password) VALUES (?, ?, ?)");
    $username = 'Main Admin';
    $mobile = '9999999999';
    $stmt->bind_param("sss", $username, $mobile, $hashed_password);
    
    if ($stmt->execute()) {
        echo "Main Admin created successfully!<br>";
        echo "Login credentials:<br>";
        echo "Mobile: 9999999999<br>";
        echo "Password: mainadmin<br>";
    } else {
        echo "Error creating main admin: " . $stmt->error . "<br>";
    }
    $stmt->close();
} else {
    echo "Main Admin already exists<br>";
}

$conn->close();
?>
