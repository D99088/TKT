<?php
header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$payment_method_id = isset($_POST['payment_method_id']) ? intval($_POST['payment_method_id']) : 0;
$payment_method_label = isset($_POST['payment_method_label']) ? trim($_POST['payment_method_label']) : '';
$utr = isset($_POST['utr']) ? trim($_POST['utr']) : '';
$screenshot = isset($_POST['screenshot']) ? $_POST['screenshot'] : '';

$needs_user = in_array($action, ['deposit', 'withdrawal', 'balance', 'list_user_payments', 'ticket_purchase', 'claim_winning']);
if ($needs_user && $user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}
if (in_array($action, ['deposit', 'withdrawal']) && $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount']);
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

$conn->query("CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('credit','debit') DEFAULT 'credit',
    status VARCHAR(50) DEFAULT 'completed',
    description TEXT DEFAULT '',
    payment_method_id INT DEFAULT 0,
    utr VARCHAR(100) DEFAULT '',
    screenshot LONGTEXT DEFAULT '',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

ensureColumn($conn, 'payments', 'type', "ENUM('credit','debit') DEFAULT 'credit'");
ensureColumn($conn, 'payments', 'status', "VARCHAR(50) DEFAULT 'completed'");
ensureColumn($conn, 'payments', 'description', "TEXT DEFAULT ''");
ensureColumn($conn, 'payments', 'payment_method_id', "INT DEFAULT 0");
ensureColumn($conn, 'payments', 'utr', "VARCHAR(100) DEFAULT ''");
ensureColumn($conn, 'payments', 'screenshot', "LONGTEXT DEFAULT ''");
ensureColumn($conn, 'payments', 'ticket_numbers', "TEXT DEFAULT ''");
ensureColumn($conn, 'payments', 'created_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
ensureColumn($conn, 'payments', 'assigned_admin', "INT DEFAULT 0");
ensureColumn($conn, 'payments', 'completed_by', "VARCHAR(100) DEFAULT ''");
ensureColumn($conn, 'users', 'balance', "DECIMAL(10,2) DEFAULT 0");

$res = $conn->query("SELECT status FROM users WHERE id = $user_id");
if ($res && $res->num_rows > 0) {
    $u = $res->fetch_assoc();
    if ($u['status'] !== 'active') {
        echo json_encode(['success' => false, 'message' => 'Your account has been suspended']);
        $conn->close();
        exit;
    }
}

$conn->begin_transaction();

try {
    if ($action === 'deposit') {
        $desc = $payment_method_label ? "Deposit via $payment_method_label" : 'Deposit';
        $stmt2 = $conn->prepare("INSERT INTO payments (user_id, amount, type, status, description, payment_method_id, utr, screenshot) VALUES (?, ?, 'credit', 'pending', ?, ?, ?, ?)");
        $stmt2->bind_param("idsiss", $user_id, $amount, $desc, $payment_method_id, $utr, $screenshot);
        $stmt2->execute();
        $newPaymentId = $conn->insert_id;

        if ($payment_method_id > 0) {
            $adminRes = $conn->query("SELECT admin_id FROM payment_methods WHERE id = $payment_method_id");
            if ($adminRes && $adminRes->num_rows > 0) {
                $pmRow = $adminRes->fetch_assoc();
                $pmAdminId = intval($pmRow['admin_id']);
                if ($pmAdminId > 0) {
                    $conn->query("UPDATE payments SET assigned_admin = $pmAdminId WHERE id = $newPaymentId");
                }
            }
        }

        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Deposit request submitted! Awaiting approval.']);

    } elseif ($action === 'withdrawal') {
        $balRes = $conn->query("SELECT balance FROM users WHERE id = $user_id");
        $balRow = $balRes->fetch_assoc();
        $currentBalance = $balRow['balance'] ?? 0;
        if ($currentBalance < $amount) {
            echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
            $conn->close();
            exit;
        }
        $desc = $payment_method_label ? $payment_method_label : 'Withdrawal Request';
        $stmt2 = $conn->prepare("INSERT INTO payments (user_id, amount, type, status, description) VALUES (?, ?, 'debit', 'pending', ?)");
        $stmt2->bind_param("ids", $user_id, $amount, $desc);
        $stmt2->execute();

        $conn->query("UPDATE users SET balance = balance - $amount WHERE id = $user_id");

        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Withdrawal request submitted! Awaiting approval.']);

    } elseif ($action === 'ticket_purchase') {
        $ticket_name = isset($_POST['ticket_name']) ? trim($_POST['ticket_name']) : '';
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
        $cost_per_ticket = isset($_POST['cost_per_ticket']) ? floatval($_POST['cost_per_ticket']) : 0;

        if (empty($ticket_name) || $total_amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid ticket data']);
            $conn->close();
            exit;
        }

        $res = $conn->query("SELECT balance FROM users WHERE id = $user_id");
        $row = $res->fetch_assoc();
        $bal = $row['balance'] ?? 0;
        if ($bal < $total_amount) {
            echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
            $conn->close();
            exit;
        }

        $ticket_numbers = isset($_POST['ticket_numbers']) ? trim($_POST['ticket_numbers']) : '';
        $desc = "Ticket: {$ticket_name} x{$quantity}";
        $stmt = $conn->prepare("INSERT INTO payments (user_id, amount, type, status, description, ticket_numbers) VALUES (?, ?, 'debit', 'completed', ?, ?)");
        $stmt->bind_param("idss", $user_id, $total_amount, $desc, $ticket_numbers);
        $stmt->execute();

        $conn->query("UPDATE users SET balance = balance - $total_amount WHERE id = $user_id");
        $conn->commit();

        echo json_encode(['success' => true, 'message' => 'Tickets purchased successfully!']);

    } elseif ($action === 'claim_winning') {
        $ticket_name = isset($_POST['ticket_name']) ? trim($_POST['ticket_name']) : '';
        $win_amount = isset($_POST['win_amount']) ? floatval($_POST['win_amount']) : 0;
        if ($win_amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid winning amount']);
            $conn->close();
            exit;
        }
        $desc = "Winning claim - {$ticket_name}";
        $stmt = $conn->prepare("INSERT INTO payments (user_id, amount, type, status, description) VALUES (?, ?, 'credit', 'completed', ?)");
        $stmt->bind_param("ids", $user_id, $win_amount, $desc);
        $stmt->execute();
        $conn->query("UPDATE users SET balance = balance + $win_amount WHERE id = $user_id");
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Winning claimed successfully!']);

    } elseif ($action === 'balance') {
        $res = $conn->query("SELECT balance FROM users WHERE id = $user_id");
        if ($res && $res->num_rows > 0) {
            $balance = $res->fetch_assoc()['balance'];
            echo json_encode(['success' => true, 'balance' => $balance]);
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }

    } elseif ($action === 'approve_deposit') {
        $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
        if ($payment_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
            $conn->close();
            exit;
        }
        $res = $conn->query("SELECT * FROM payments WHERE id = $payment_id AND type = 'credit' AND status IN ('submitted','pending')");
        if ($res && $res->num_rows > 0) {
            $payment = $res->fetch_assoc();
            $conn->begin_transaction();
            try {
                $conn->query("UPDATE users SET balance = balance + {$payment['amount']} WHERE id = {$payment['user_id']}");
                $conn->query("UPDATE payments SET status = 'completed' WHERE id = $payment_id");
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Deposit approved!']);
            } catch (Exception $e) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Approval failed']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Pending deposit not found']);
        }

    } elseif ($action === 'reject_deposit') {
        $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
        if ($payment_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
            $conn->close();
            exit;
        }
        $conn->query("UPDATE payments SET status = 'rejected' WHERE id = $payment_id AND type = 'credit' AND status IN ('submitted','pending')");
        $affected = $conn->affected_rows;
        $conn->commit();
        if ($affected > 0) {
            echo json_encode(['success' => true, 'message' => 'Deposit rejected!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Submitted deposit not found']);
        }

    } elseif ($action === 'approve_withdrawal') {
        $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
        $completed_by = isset($_POST['completed_by']) ? trim($_POST['completed_by']) : '';
        if ($payment_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
            $conn->close();
            exit;
        }
        $res = $conn->query("SELECT * FROM payments WHERE id = $payment_id AND type = 'debit' AND status IN ('submitted','pending')");
        if ($res && $res->num_rows > 0) {
            $payment = $res->fetch_assoc();
            if ($completed_by !== '') {
                $conn->query("UPDATE payments SET status = 'completed', completed_by = '$completed_by' WHERE id = $payment_id");
            } else {
                $conn->query("UPDATE payments SET status = 'completed' WHERE id = $payment_id");
            }
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Withdrawal approved!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Submitted withdrawal not found']);
        }

    } elseif ($action === 'reject_withdrawal') {
        $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
        if ($payment_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
            $conn->close();
            exit;
        }
        $res = $conn->query("SELECT * FROM payments WHERE id = $payment_id AND type = 'debit' AND status IN ('submitted','pending')");
        if ($res && $res->num_rows > 0) {
            $payment = $res->fetch_assoc();
            $conn->query("UPDATE users SET balance = balance + {$payment['amount']} WHERE id = {$payment['user_id']}");
            $conn->query("UPDATE payments SET status = 'rejected' WHERE id = $payment_id");
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Withdrawal rejected!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Submitted withdrawal not found']);
        }
        $conn->close();
        exit;

    } elseif ($action === 'update_ticket_numbers') {
        $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
        $ticket_numbers = isset($_POST['ticket_numbers']) ? trim($_POST['ticket_numbers']) : '';
        if ($payment_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
            $conn->close();
            exit;
        }
        $stmt = $conn->prepare("UPDATE payments SET ticket_numbers = ? WHERE id = ?");
        $stmt->bind_param("si", $ticket_numbers, $payment_id);
        $stmt->execute();
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Ticket numbers updated successfully']);
        $stmt->close();
        $conn->close();
        exit;

    } elseif ($action === 'restore_payment') {
        $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
        if ($payment_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
            $conn->close();
            exit;
        }
        $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ? AND status = 'rejected'");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $payment = $res->fetch_assoc();
            if ($payment['type'] === 'debit') {
                $conn->query("UPDATE users SET balance = balance - {$payment['amount']} WHERE id = {$payment['user_id']}");
            }
            $conn->query("UPDATE payments SET status = 'pending' WHERE id = $payment_id");
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Transaction restored']);
        } else {
            $conn->commit();
            echo json_encode(['success' => false, 'message' => 'Transaction not found or not rejected']);
        }
        $stmt->close();
        $conn->close();
        exit;

    } elseif ($action === 'delete_payment') {
        $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
        if ($payment_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
            $conn->close();
            exit;
        }
        $res = $conn->query("SELECT * FROM payments WHERE id = $payment_id");
        $payment = $res ? $res->fetch_assoc() : null;
        if (!$payment) {
            echo json_encode(['success' => false, 'message' => 'Transaction not found']);
            $conn->close();
            exit;
        }
        if ($payment['status'] === 'completed') {
            $uid = intval($payment['user_id']);
            $amt = floatval($payment['amount']);
            if ($payment['type'] === 'credit') {
                $conn->query("UPDATE users SET balance = balance - $amt WHERE id = $uid");
            } elseif ($payment['type'] === 'debit') {
                $conn->query("UPDATE users SET balance = balance + $amt WHERE id = $uid");
            }
        }
        $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $conn->commit();
        if ($affected > 0) {
            echo json_encode(['success' => true, 'message' => 'Transaction deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        }
        $stmt->close();
        $conn->close();
        exit;

    } elseif ($action === 'list_admins') {
        $res = $conn->query("SELECT id, username, mobile FROM admin_users ORDER BY username ASC");
        $admins = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $admins[] = $row;
            }
        }
        $conn->commit();
        echo json_encode(['success' => true, 'admins' => $admins]);
        $conn->close();
        exit;

    } elseif ($action === 'assign_admin') {
        $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
        $admin_id = isset($_POST['admin_id']) ? intval($_POST['admin_id']) : 0;
        if ($payment_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid payment ID']);
            $conn->close();
            exit;
        }
        $stmt = $conn->prepare("UPDATE payments SET assigned_admin = ? WHERE id = ?");
        $stmt->bind_param("ii", $admin_id, $payment_id);
        $stmt->execute();
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Admin assigned successfully']);
        $stmt->close();
        $conn->close();
        exit;

    } elseif ($action === 'list_payments') {
        $type_filter = isset($_POST['type']) ? $_POST['type'] : '';
        $status_filter = isset($_POST['status']) ? $_POST['status'] : '';
        $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : '';
        $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : '';
        $conditions = [];
        if ($type_filter === 'credit' || $type_filter === 'debit') {
            $conditions[] = "p.type = '$type_filter'";
        }
        if ($status_filter !== '') {
            $conditions[] = "p.status = '$status_filter'";
        }
        if ($date_from !== '') {
            $conditions[] = "p.created_at >= '$date_from 00:00:00'";
        }
        if ($date_to !== '') {
            $conditions[] = "p.created_at <= '$date_to 23:59:59'";
        }
        $payment_method_ids = isset($_POST['payment_method_ids']) ? $_POST['payment_method_ids'] : '';
        if ($payment_method_ids !== '') {
            $ids = array_map('intval', explode(',', $payment_method_ids));
            $ids = array_filter($ids, function($id) { return $id > 0; });
            if (count($ids) > 0) {
                $conditions[] = "p.payment_method_id IN (" . implode(',', $ids) . ")";
            }
        }
        $assigned_admin = isset($_POST['assigned_admin']) ? intval($_POST['assigned_admin']) : 0;
        if ($assigned_admin > 0) {
            $conditions[] = "p.assigned_admin = $assigned_admin";
        }
        $sql = "SELECT p.*, u.username, u.mobile, a.username AS admin_name, a.mobile AS admin_mobile FROM payments p LEFT JOIN users u ON p.user_id = u.id LEFT JOIN admin_users a ON p.assigned_admin = a.id";
        if (count($conditions) > 0) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        $order = isset($_POST['order']) ? trim($_POST['order']) : '';
        if ($order === 'pending_first') {
            $sql .= " ORDER BY CASE WHEN p.status IN ('submitted','pending') THEN 0 ELSE 1 END, p.created_at ASC";
        } else {
            $order_dir = $order === 'asc' ? 'ASC' : 'DESC';
            $sql .= " ORDER BY p.created_at $order_dir";
        }
        $res = $conn->query($sql);
        $payments = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $payments[] = $row;
            }
        }
        echo json_encode(['success' => true, 'payments' => $payments]);

    } elseif ($action === 'get_ticket_numbers') {
        $ticket_name = isset($_POST['ticket_name']) ? trim($_POST['ticket_name']) : '';
        $desc_like = 'Ticket: ' . $ticket_name . '%';
        $res = $conn->prepare("SELECT ticket_numbers FROM payments WHERE description LIKE ? AND ticket_numbers != ''");
        $res->bind_param("s", $desc_like);
        $res->execute();
        $result = $res->get_result();
        $allNums = [];
        while ($row = $result->fetch_assoc()) {
            $nums = explode(',', $row['ticket_numbers']);
            foreach ($nums as $n) {
                $n = trim($n);
                if ($n !== '') $allNums[] = $n;
            }
        }
        echo json_encode(['success' => true, 'numbers' => $allNums]);
        $conn->close();
        exit;

    } elseif ($action === 'list_user_payments') {
        $uid = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if ($uid <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid user']);
            $conn->close();
            exit;
        }
        $res = $conn->query("SELECT * FROM payments WHERE user_id = $uid ORDER BY id DESC");
        $payments = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $payments[] = $row;
            }
        }
        echo json_encode(['success' => true, 'payments' => $payments]);
        $conn->close();
        exit;

    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Transaction failed']);
}

$conn->close();
?>
