<?php
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "mtkt_db";

header('Content-Type: application/json');

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$mobile = $_POST['mobile'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($mobile) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

$sql = "SELECT * FROM admin_users WHERE mobile = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $mobile);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Debug output - remove after testing
    error_log("Login attempt for mobile: $mobile, locked status: " . $user['locked']);
    
    if ($user['locked'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Your account has been locked by admin. Please contact support.']);
    } elseif (password_verify($password, $user['password'])) {
        echo json_encode([
            'success' => true, 
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'mobile' => $user['mobile'],
                'email' => $user['email'] ?? '',
                'created_at' => $user['created_at'] ?? ''
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid password']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'User not found. Mobile: ' . $mobile]);
}

$stmt->close();
$conn->close();
?>
