<?php
header('Content-Type: application/json');
require_once 'config.php';

$conn->query("CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('upi', 'bank') NOT NULL,
    label VARCHAR(100) NOT NULL,
    upi_id VARCHAR(100) DEFAULT '',
    upi_name VARCHAR(100) DEFAULT '',
    bank_name VARCHAR(100) DEFAULT '',
    account_number VARCHAR(50) DEFAULT '',
    ifsc VARCHAR(50) DEFAULT '',
    qr_code TEXT DEFAULT '',
    account_name VARCHAR(100) DEFAULT '',
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

function ensureColumn($conn, $table, $column, $definition) {
    $r = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($r && $r->num_rows === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

ensureColumn($conn, 'payment_methods', 'account_name', "VARCHAR(100) DEFAULT ''");
ensureColumn($conn, 'payment_methods', 'qr_code', "TEXT DEFAULT ''");
ensureColumn($conn, 'payment_methods', 'is_active', "TINYINT(1) DEFAULT 1");
ensureColumn($conn, 'payment_methods', 'sort_order', "INT DEFAULT 0");
ensureColumn($conn, 'payment_methods', 'admin_id', "INT DEFAULT 0");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $type_filter = isset($_GET['type']) ? $_GET['type'] : '';
    $sql = "SELECT * FROM payment_methods WHERE is_active = 1";
    $params = [];
    $types = '';

    if ($type_filter === 'upi' || $type_filter === 'bank') {
        $sql .= " AND type = ?";
        $params[] = $type_filter;
        $types .= 's';
    }

    $sql .= " ORDER BY sort_order ASC, id ASC";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $methods = [];
    while ($row = $result->fetch_assoc()) {
        $methods[] = $row;
    }

    echo json_encode(['success' => true, 'methods' => $methods]);
    $conn->close();
    exit;
}

    if ($method === 'POST') {
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        if ($action === 'set_admin') {
            $id = intval($_POST['id'] ?? 0);
            $admin_id = intval($_POST['admin_id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid payment method ID']);
                $conn->close();
                exit;
            }
            ensureColumn($conn, 'payment_methods', 'admin_id', "INT DEFAULT 0");
            $stmt = $conn->prepare("UPDATE payment_methods SET admin_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $admin_id, $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Admin assigned to payment method']);
            $conn->close();
            exit;
        }

        if ($action === 'add') {
        $type = $_POST['type'] ?? '';
        $label = $_POST['label'] ?? '';
        $upi_id = $_POST['upi_id'] ?? '';
        $upi_name = $_POST['upi_name'] ?? '';
        $bank_name = $_POST['bank_name'] ?? '';
        $account_number = $_POST['account_number'] ?? '';
        $ifsc = $_POST['ifsc'] ?? '';

        if (!in_array($type, ['upi', 'bank']) || empty($label)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            $conn->close();
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO payment_methods (type, label, upi_id, upi_name, bank_name, account_number, ifsc) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $type, $label, $upi_id, $upi_name, $bank_name, $account_number, $ifsc);
        $stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Payment method added']);
        $conn->close();
        exit;
    }

    if ($action === 'toggle_active') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            $conn->close();
            exit;
        }
        $conn->query("UPDATE payment_methods SET is_active = NOT is_active WHERE id = $id");
        echo json_encode(['success' => true, 'message' => 'Toggled']);
        $conn->close();
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            $conn->close();
            exit;
        }
        $conn->query("DELETE FROM payment_methods WHERE id = $id");
        echo json_encode(['success' => true, 'message' => 'Deleted']);
        $conn->close();
        exit;
    }

    if ($action === 'sync_all') {
        $data_json = isset($_POST['data']) ? $_POST['data'] : '';
        $items = json_decode($data_json, true);
        if (!is_array($items)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            $conn->close();
            exit;
        }

        $conn->query("TRUNCATE TABLE payment_methods");

        ensureColumn($conn, 'payment_methods', 'admin_id', "INT DEFAULT 0");

        $stmt = $conn->prepare("INSERT INTO payment_methods (type, label, upi_id, upi_name, qr_code, account_name, bank_name, account_number, ifsc, sort_order, is_active, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($items as $item) {
            $type = isset($item['type']) ? $item['type'] : 'bank';
            $label = isset($item['label']) ? $item['label'] : (isset($item['name']) ? $item['name'] : '');
            $upi_id = isset($item['upi_id']) ? $item['upi_id'] : '';
            $upi_name = isset($item['upi_name']) ? $item['upi_name'] : '';
            $qr_code = isset($item['qr_code']) ? $item['qr_code'] : '';
            $account_name = isset($item['accountName']) ? $item['accountName'] : '';
            $bank_name = isset($item['name']) ? $item['name'] : '';
            $account_number = isset($item['accountNumber']) ? $item['accountNumber'] : '';
            $ifsc = isset($item['ifsc']) ? $item['ifsc'] : '';
            $sort_order = isset($item['priority']) ? intval($item['priority']) : 0;
            $is_active = (isset($item['active']) && $item['active'] !== false && $item['active'] !== 'false') ? 1 : 0;

            if (empty($label)) continue;

            $admin_id = isset($item['admin_id']) ? intval($item['admin_id']) : 0;

            $stmt->bind_param("sssssssssiii", $type, $label, $upi_id, $upi_name, $qr_code, $account_name, $bank_name, $account_number, $ifsc, $sort_order, $is_active, $admin_id);
            $stmt->execute();
        }

        echo json_encode(['success' => true, 'message' => 'Payment methods synced']);
        $conn->close();
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    $conn->close();
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
$conn->close();
?>
