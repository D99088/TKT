<?php
$conn = new mysqli("localhost", "root", "", "mtkt_db");

echo "<h2>Testing Login Check</h2>";

// Get user 3 details
$result = $conn->query("SELECT * FROM admin_users WHERE id = 3");
$user = $result->fetch_assoc();

echo "User 3 details:<br>";
echo "- ID: " . $user['id'] . "<br>";
echo "- Username: " . $user['username'] . "<br>";
echo "- Mobile: " . $user['mobile'] . "<br>";
echo "- Locked: " . ($user['locked'] ? 'YES' : 'NO') . "<br>";

echo "<br>If locked=1, login should be blocked.<br>";
echo "If locked=0, login should work.<br>";

$conn->close();
?>
