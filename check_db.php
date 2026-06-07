<?php
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "mtkt_db";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected to database successfully<br>";

// Check tables
$tables = ['users', 'admin_users', 'tickets', 'payments'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "$table: $count records<br>";
    } else {
        echo "$table: Table does not exist<br>";
    }
}

$conn->close();
?>
