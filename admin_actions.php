<?php
// admin_actions.php
session_start();
include 'db_config.php';
header('Content-Type: application/json');

// ── ADMIN GUARD ──────────────────────────────────────────────
if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

$action = $_POST['action'] ?? '';

// ── ASSIGN RIDER ─────────────────────────────────────────────
if ($action === 'assign_rider') {
    $order_id = trim($_POST['order_id'] ?? '');
    $rider_id = (int)($_POST['rider_id'] ?? 0);

    if (!$order_id || !$rider_id) {
        echo json_encode(['success' => false, 'error' => 'Missing params']); exit;
    }

    $stmt = $conn->prepare("UPDATE orders SET rider = ? WHERE id = ?");
    $stmt->bind_param("is", $rider_id, $order_id);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok]);
}

// ── UPDATE STATUS ─────────────────────────────────────────────
elseif ($action === 'update_status') {
    $order_id = trim($_POST['order_id'] ?? '');
    $status   = trim($_POST['status']   ?? '');
    $allowed  = ['pending', 'cooking', 'out', 'delivered', 'cancelled'];

    if (!$order_id || !in_array($status, $allowed)) {
        echo json_encode(['success' => false, 'error' => 'Invalid params']); exit;
    }

    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("ss", $status, $order_id);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        // Log the status change
        $stmt = $conn->prepare(
            "INSERT INTO order_status_log (order_id, status, changed_by)
             VALUES (?, ?, ?)"
        );
        $stmt->bind_param("ssi", $order_id, $status, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['success' => $ok]);
}

// ── TOGGLE MENU ITEM ──────────────────────────────────────────
elseif ($action === 'toggle_menu') {
    $id    = (int)($_POST['id']    ?? 0);
    $avail = (int)($_POST['avail'] ?? 0);

    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing id']); exit; }

    $stmt = $conn->prepare("UPDATE menu SET avail = ? WHERE id = ?");
    $stmt->bind_param("ii", $avail, $id);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok]);
}

// ── DELETE MENU ITEM ──────────────────────────────────────────
elseif ($action === 'delete_menu') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing id']); exit; }

    $stmt = $conn->prepare("DELETE FROM menu WHERE id = ?");
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok]);
}

// ── ADD MENU ITEM ─────────────────────────────────────────────
elseif ($action === 'add_menu') {
    $emoji = trim($_POST['emoji'] ?? '🍔');
    $name  = trim($_POST['name']  ?? '');
    $desc  = trim($_POST['desc']  ?? '');
    $price = (int)($_POST['price'] ?? 0);
    $cat   = trim($_POST['cat']   ?? 'burger');

    if (!$name || !$price) { echo json_encode(['success' => false, 'error' => 'Name and price required']); exit; }

    $stmt = $conn->prepare("INSERT INTO menu (emoji,name,`desc`,price,cat,avail) VALUES(?,?,?,?,?,1)");
    $stmt->bind_param("sssiss", $emoji, $name, $desc, $price, $cat);
    // fix: price is int
    $stmt = $conn->prepare("INSERT INTO menu (emoji,name,`desc`,price,cat,avail) VALUES(?,?,?,?,?,1)");
    $stmt->bind_param("sssis", $emoji, $name, $desc, $price, $cat);
    $ok = $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();
    echo json_encode(['success' => $ok, 'id' => $new_id]);
}

else {
    echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
?>
