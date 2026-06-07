<?php
$conn = new mysqli("localhost", "root", "", "mtkt_db");

echo "<h2>Direct Login Test</h2>";

$mobile = "7588164854";
$password = "yourpassword"; // Change this to the actual password

$stmt = $conn->prepare("SELECT * FROM admin_users WHERE mobile = ?");
$stmt->bind_param("s", $mobile);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    echo "Found user: " . $user['username'] . "<br>";
    echo "Locked status: " . $user['locked'] . "<br>";
    echo "Password verify: " . (password_verify($password, $user['password']) ? 'TRUE' : 'FALSE') . "<br>";
    
    if ($user['locked'] == 1) {
        echo "<strong style='color:red;'>USER IS LOCKED - Should show locked message!</strong><br>";
    } else {
        echo "<strong style='color:green;'>User is NOT locked</strong><br>";
    }
} else {
    echo "User not found<br>";
}

$stmt->close();
$conn->close();
?>
