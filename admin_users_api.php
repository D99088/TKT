<?php
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mtkt_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'get':
        $result = $conn->query("SELECT id, username, mobile, email, created_at, locked FROM admin_users ORDER BY id DESC");
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        echo json_encode(['success' => true, 'users' => $users]);
        break;
        
    case 'add':
        $username = trim($_POST['username']);
        $mobile = trim($_POST['mobile']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($mobile) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            break;
        }
        
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
            break;
        }
        
        $check = $conn->prepare("SELECT id FROM admin_users WHERE mobile = ?");
        $check->bind_param("s", $mobile);
        $check->execute();
        $checkResult = $check->get_result();
        
        if ($checkResult->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Mobile number already exists']);
            break;
        }
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admin_users (username, mobile, email, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $mobile, $email, $hashed_password);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Admin user added successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding admin user']);
        }
        break;
        
    case 'update':
        $id = intval($_POST['id']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        
        $stmt = $conn->prepare("UPDATE admin_users SET username = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $username, $email, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Admin user updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating admin user']);
        }
        break;
        
    case 'delete':
        $id = intval($_POST['id']);
        
        if ($id == $_SESSION['admin_id']) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
            break;
        }
        
        $stmt = $conn->prepare("DELETE FROM admin_users WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Admin user deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting admin user']);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>
