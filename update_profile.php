<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mtkt_db";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $account_name = isset($_POST['account_name']) ? trim($_POST['account_name']) : '';
    $bank_name = isset($_POST['bank_name']) ? trim($_POST['bank_name']) : '';
    $account_number = isset($_POST['account_number']) ? trim($_POST['account_number']) : '';
    $ifsc = isset($_POST['ifsc']) ? trim($_POST['ifsc']) : '';
    $upi_id = isset($_POST['upi_id']) ? trim($_POST['upi_id']) : '';
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user']);
        exit;
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    function ensureColumn($conn, $table, $column, $definition) {
        $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($r && $r->num_rows === 0) {
            $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    }
    
    ensureColumn($conn, 'users', 'account_name', "VARCHAR(100) DEFAULT ''");
    ensureColumn($conn, 'users', 'bank_name', "VARCHAR(100) DEFAULT ''");
    ensureColumn($conn, 'users', 'account_number', "VARCHAR(50) DEFAULT ''");
    ensureColumn($conn, 'users', 'ifsc', "VARCHAR(50) DEFAULT ''");
    ensureColumn($conn, 'users', 'upi_id', "VARCHAR(100) DEFAULT ''");
    
    $stmt = $conn->prepare("UPDATE users SET email = ?, account_name = ?, bank_name = ?, account_number = ?, ifsc = ?, upi_id = ? WHERE id = ?");
    $stmt->bind_param("ssssssi", $email, $account_name, $bank_name, $account_number, $ifsc, $upi_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating profile']);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
