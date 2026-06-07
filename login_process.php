<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

function ensureColumn($conn, $table, $column, $definition) {
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($r && $r->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    if (empty($mobile) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    ensureColumn($conn, 'users', 'balance', "DECIMAL(10,2) DEFAULT 0");
    ensureColumn($conn, 'users', 'account_name', "VARCHAR(100) DEFAULT ''");
    ensureColumn($conn, 'users', 'status', "VARCHAR(20) DEFAULT 'active'");
    
    $sql = "SELECT * FROM users WHERE mobile = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $mobile);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        $userStatus = 'active';
        if (isset($user['status'])) {
            $userStatus = $user['status'];
        }
        
        if ($userStatus !== 'active') {
            echo json_encode(['success' => false, 'message' => 'Your account has been suspended']);
            $stmt->close();
            $conn->close();
            exit;
        }
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['mobile'] = $user['mobile'];
            
            echo json_encode([
                'success' => true, 
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'mobile' => $user['mobile'],
                    'email' => $user['email'] ?? '',
                    'balance' => $user['balance'] ?? 0,
                    'account_name' => $user['account_name'] ?? '',
                    'bank_name' => $user['bank_name'] ?? '',
                    'account_number' => $user['account_number'] ?? '',
                    'ifsc' => $user['ifsc'] ?? '',
                    'upi_id' => $user['upi_id'] ?? ''
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
    
    $stmt->close();
    $conn->close();
}
?>
