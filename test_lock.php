<?php
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "mtkt_db";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$result = $conn->query("DESCRIBE admin_users");
echo "<h2>admin_users table columns:</h2>";
echo "<ul>";
while ($row = $result->fetch_assoc()) {
    echo "<li>" . $row['Field'] . " - " . $row['Type'] . "</li>";
}
echo "</ul>";

echo "<h2>Test toggle lock for user ID 3:</h2>";
$conn->query("UPDATE admin_users SET locked = 1 WHERE id = 3");
$result = $conn->query("SELECT id, username, locked FROM admin_users WHERE id = 3");
if ($row = $result->fetch_assoc()) {
    echo "User: " . $row['username'] . " - Locked: " . ($row['locked'] ? 'Yes' : 'No');
}

$conn->close();
?>
