<?php
// get_new_orders.php — returns total order count for admin auto-refresh
session_start();
include 'db_config.php';
header('Content-Type: application/json');

// ── Re-fetch role if missing ─────────────────────────────────
if (isset($_SESSION['user_id']) && !isset($_SESSION['user_role'])) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) $_SESSION['user_role'] = $row['role'];
    $stmt->close();
}

if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['count' => 0]); exit;
}

$res   = $conn->query("SELECT COUNT(*) as cnt FROM orders");
$row   = $res->fetch_assoc();
echo json_encode(['count' => (int)$row['cnt']]);
?>
