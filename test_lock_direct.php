<?php
header('Content-Type: text/html');

$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "mtkt_db";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Step 1: Check if locked column exists</h2>";
$result = $conn->query("SHOW COLUMNS FROM admin_users LIKE 'locked'");
if ($result->num_rows == 0) {
    echo "Column 'locked' does not exist. Adding it now...<br>";
    $conn->query("ALTER TABLE admin_users ADD COLUMN locked TINYINT(1) DEFAULT 0");
    echo "Column added!<br>";
} else {
    echo "Column 'locked' exists!<br>";
}

echo "<h2>Step 2: Toggle lock for user ID 3</h2>";
$conn->query("UPDATE admin_users SET locked = 1 WHERE id = 3");
$result = $conn->query("SELECT id, username, locked FROM admin_users WHERE id = 3");
if ($row = $result->fetch_assoc()) {
    echo "User: " . $row['username'] . " - Locked: " . ($row['locked'] ? 'Yes' : 'No') . "<br>";
}

echo "<h2>Step 3: Test login for user 3 (should be blocked)</h2>";
$sql = "SELECT * FROM admin_users WHERE mobile = (SELECT mobile FROM admin_users WHERE id = 3)";
$result = $conn->query($sql);
if ($row = $result->fetch_assoc()) {
    if ($row['locked'] == 1) {
        echo "User IS locked - Login will be blocked!<br>";
    } else {
        echo "User is NOT locked<br>";
    }
}

echo "<h2>Step 4: Unlock user 3</h2>";
$conn->query("UPDATE admin_users SET locked = 0 WHERE id = 3");
$result = $conn->query("SELECT id, username, locked FROM admin_users WHERE id = 3");
if ($row = $result->fetch_assoc()) {
    echo "User: " . $row['username'] . " - Locked: " . ($row['locked'] ? 'Yes' : 'No') . "<br>";
}

echo "<h2>All users:</h2>";
$result = $conn->query("SELECT id, username, locked FROM admin_users");
echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Username</th><th>Locked</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>" . $row['id'] . "</td><td>" . $row['username'] . "</td><td>" . ($row['locked'] ? 'Yes' : 'No') . "</td></tr>";
}
echo "</table>";

$conn->close();
?>
