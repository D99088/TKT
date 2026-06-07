<?php
header('Content-Type: application/json');

$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "mtkt_db";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$result = [
    'users' => 0,
    'admins' => 0,
    'tickets' => 0,
    'total_credit' => 0,
    'total_debit' => 0
];

$res = $conn->query("SELECT COUNT(*) as count FROM users");
if ($res) {
    $result['users'] = $res->fetch_assoc()['count'];
}

$res = $conn->query("SELECT COUNT(*) as count FROM admin_users");
if ($res) {
    $result['admins'] = $res->fetch_assoc()['count'];
}

$res = $conn->query("SELECT COUNT(*) as count FROM tickets");
if ($res) {
    $result['tickets'] = $res->fetch_assoc()['count'];
}

$res = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE type = 'credit' AND status = 'completed'");
if ($res) {
    $result['total_credit'] = $res->fetch_assoc()['total'];
}

$res = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE type = 'debit' AND status = 'completed'");
if ($res) {
    $result['total_debit'] = $res->fetch_assoc()['total'];
}

echo json_encode(['success' => true, 'data' => $result]);

$conn->close();
?>
