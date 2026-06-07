<?php
header('Content-Type: application/json');

$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "mtkt_db";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'check') {
    $id = intval($_POST['id'] ?? 0);
    $result = $conn->query("SELECT id, locked FROM admin_users WHERE id = $id");
    if ($row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'locked' => (bool)$row['locked']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
} 
elseif ($action === 'toggle') {
    $id = intval($_POST['id'] ?? 0);
    $locked = intval($_POST['locked'] ?? 0);
    
    $conn->query("UPDATE admin_users SET locked = $locked WHERE id = $id");
    
    if ($conn->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Lock status updated']);
    } else {
        $result = $conn->query("SELECT id FROM admin_users WHERE id = $id");
        if ($result->num_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Lock status unchanged']);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
    }
} 
else {
    echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
}

$conn->close();
?>
