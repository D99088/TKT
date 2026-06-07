<?php
// Test login API directly
$conn = new mysqli("localhost", "root", "", "mtkt_db");

echo "<h2>Simulating Login API Call</h2>";

$mobile = "7588164854";
$password = "dev123"; // Try a common password

echo "Mobile: $mobile<br>";
echo "Password: $password<br><br>";

$stmt = $conn->prepare("SELECT * FROM admin_users WHERE mobile = ?");
$stmt->bind_param("s", $mobile);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    echo "User found: " . $user['username'] . "<br>";
    echo "Locked: " . ($user['locked'] == 1 ? "YES (should block)" : "NO") . "<br><br>";
    
    // Check each condition
    echo "Checking conditions:<br>";
    echo "1. user['locked'] == 1: " . ($user['locked'] == 1 ? "TRUE" : "FALSE") . "<br>";
    echo "2. password_verify result: " . (password_verify($password, $user['password']) ? "TRUE" : "FALSE") . "<br>";
    
    // What should happen
    if ($user['locked'] == 1) {
        echo "<br><strong style='color:red;'>RESULT: Should return 'locked' message</strong>";
    } elseif (password_verify($password, $user['password'])) {
        echo "<br><strong style='color:green;'>RESULT: Should return success</strong>";
    } else {
        echo "<br><strong style='color:orange;'>RESULT: Should return 'invalid password'</strong>";
    }
} else {
    echo "User not found with mobile: $mobile";
}

$conn->close();
?>
