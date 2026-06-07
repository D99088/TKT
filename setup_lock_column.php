<?php
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "mtkt_db";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "ALTER TABLE admin_users ADD COLUMN locked TINYINT(1) DEFAULT 0";
if ($conn->query($sql) === TRUE) {
    echo "Column 'locked' added successfully";
} else {
    echo "Error adding column: " . $conn->error;
}

$conn->close();
?>
