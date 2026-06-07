<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once 'config.php';

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

function ensureColumn($conn, $table, $column, $definition) {
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($r && $r->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'count') {
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $count = $result ? $result->fetch_assoc()['count'] : 0;
    echo json_encode(['success' => true, 'count' => $count]);
    $conn->close();
    exit;
}

if ($action === 'getAll') {
    ensureColumn($conn, 'users', 'balance', "DECIMAL(10,2) DEFAULT 0");
    ensureColumn($conn, 'users', 'status', "VARCHAR(20) DEFAULT 'active'");
    $users = [];
    $result = $conn->query("SELECT id, username, mobile, COALESCE(status, 'active') as status, balance FROM users WHERE COALESCE(status, 'active') != 'deleted' ORDER BY id DESC");
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Query failed']);
    } else {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode(['success' => true, 'users' => $users]);
    }
    $conn->close();
    exit;
}

if ($action === 'getDeleted') {
    ensureColumn($conn, 'users', 'status', "VARCHAR(20) DEFAULT 'active'");
    ensureColumn($conn, 'users', 'deleted_at', "DATETIME DEFAULT NULL");
    $users = [];
    $result = $conn->query("SELECT id, username, mobile, COALESCE(status, 'active') as status, balance, deleted_at FROM users WHERE COALESCE(status, 'active') = 'deleted' ORDER BY id DESC");
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Query failed']);
    } else {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode(['success' => true, 'users' => $users]);
    }
    $conn->close();
    exit;
}

if ($action === 'getUser') {
    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    if (empty($userId)) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        $conn->close();
        exit;
    }
    $sql = "SELECT id, username, mobile, status, balance, created_at FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    $stmt->close();
    $conn->close();
    exit;
}

if ($action === 'verify') {
    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    if (empty($userId)) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        $conn->close();
        exit;
    }
    $sql = "SELECT id, username, mobile, password, status FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    $stmt->close();
    $conn->close();
    exit;
}

if ($action === 'checkPassword') {
    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $testPassword = isset($_GET['password']) ? $_GET['password'] : '';
    if (empty($userId) || empty($testPassword)) {
        echo json_encode(['success' => false, 'message' => 'User ID and password required']);
        $conn->close();
        exit;
    }
    $result = $conn->query("SELECT password FROM users WHERE id = $userId");
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $verify = password_verify($testPassword, $user['password']);
        echo json_encode(['success' => true, 'match' => $verify, 'hash' => $user['password']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    $conn->close();
    exit;
}

$postAction = isset($_POST['action']) ? $_POST['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($postAction)) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    
    if (empty($username) || empty($mobile) || empty($password) || empty($confirm_password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }
    
    $check_mobile = "SELECT * FROM users WHERE mobile = ?";
    $check_stmt = $conn->prepare($check_mobile);
    $check_stmt->bind_param("s", $mobile);
    $check_stmt->execute();
    $mobile_result = $check_stmt->get_result();
    
    if ($mobile_result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'This mobile number is already registered']);
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (username, mobile, password) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $username, $mobile, $hashed_password);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Account created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error creating account']);
    }
    
    $stmt->close();
    $conn->close();
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
    $balance = isset($_POST['balance']) ? floatval($_POST['balance']) : null;
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'active';
    $newPassword = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    if (empty($id) || empty($username) || empty($mobile)) {
        echo json_encode(['success' => false, 'message' => 'ID, username and mobile are required']);
        $conn->close();
        exit;
    }
    
    ensureColumn($conn, 'users', 'balance', "DECIMAL(10,2) DEFAULT 0");
    ensureColumn($conn, 'users', 'status', "VARCHAR(20) DEFAULT 'active'");

    if (!empty($newPassword)) {
        if (strlen($newPassword) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
            $conn->close();
            exit;
        }
        $hashed_password = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET username = ?, mobile = ?, password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $username, $mobile, $hashed_password, $id);
    } else {
        $sql = "UPDATE users SET username = ?, mobile = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $username, $mobile, $id);
    }
    
    if ($stmt->execute()) {
        if ($balance !== null) {
            $conn->query("UPDATE users SET balance = {$balance} WHERE id = " . $id);
        }
        $conn->query("UPDATE users SET status = '{$status}' WHERE id = " . $id);
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating user']);
    }
    
    $stmt->close();
    $conn->close();
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'restore') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        $conn->close();
        exit;
    }

    ensureColumn($conn, 'users', 'status', "VARCHAR(20) DEFAULT 'active'");
    ensureColumn($conn, 'users', 'deleted_at', "DATETIME DEFAULT NULL");

    $sql = "UPDATE users SET status = 'active', deleted_at = NULL WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User restored successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error restoring user']);
    }

    $stmt->close();
    $conn->close();
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        $conn->close();
        exit;
    }
    
    ensureColumn($conn, 'users', 'deleted_at', "DATETIME DEFAULT NULL");
    $sql = "UPDATE users SET status = 'deleted', deleted_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'User moved to deleted users']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting user']);
    }
    
    $stmt->close();
    $conn->close();
    exit;
}
?>
