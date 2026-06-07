<?php
header('Content-Type: application/json');

$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "mtkt_db";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = trim($_POST['mobile']);
    $password = trim($_POST['password']);
    
    if (empty($mobile) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    $conn = new mysqli($servername, $username_db, $password_db, $dbname);
    
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    $sql = "SELECT * FROM admin_main WHERE mobile = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $mobile);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            echo json_encode([
                'success' => true, 
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'mobile' => $user['mobile'],
                    'email' => $user['email'] ?? ''
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid password']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    
    $stmt->close();
    $conn->close();
}
?>
