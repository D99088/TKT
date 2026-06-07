<?php
$conn = new mysqli("localhost", "root", "", "mtkt_db");

echo "Locking user 3...<br>";
$conn->query("UPDATE admin_users SET locked = 1 WHERE id = 3");

$result = $conn->query("SELECT id, username, locked FROM admin_users WHERE id = 3");
$row = $result->fetch_assoc();
echo "User 3 - Locked: " . ($row['locked'] ? 'YES' : 'NO') . "<br>";

$conn->close();
?>
