<?php
$conn = new mysqli("localhost", "root", "", "mtkt_db");

echo "<h2>Database Check</h2>";

// Check all users
echo "<h3>All Admin Users:</h3>";
$result = $conn->query("SELECT id, username, mobile, locked FROM admin_users");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Username</th><th>Mobile</th><th>Locked</th></tr>";
while ($row = $result->fetch_assoc()) {
    $lockedText = $row['locked'] == 1 ? '<strong style="color:red">YES</strong>' : 'No';
    echo "<tr><td>{$row['id']}</td><td>{$row['username']}</td><td>{$row['mobile']}</td><td>$lockedText</td></tr>";
}
echo "</table>";

// Check table structure
echo "<h3>Table Structure:</h3>";
$result = $conn->query("DESCRIBE admin_users");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td></tr>";
}
echo "</table>";

$conn->close();
?>
