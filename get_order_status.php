<?php
// get_order_status.php — real-time order status AJAX endpoint
session_start();
include 'db_config.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$order_id = trim($_GET['order_id'] ?? '');
if (!$order_id) { echo json_encode(['error'=>'No order ID']); exit; }

$stmt = $conn->prepare(
    "SELECT o.status, o.rider, r.name AS rider_name, r.phone AS rider_phone
     FROM orders o
     LEFT JOIN riders r ON o.rider = r.id
     WHERE o.id = ?"
);
$stmt->bind_param("s", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) { echo json_encode(['error'=>'Order not found']); exit; }

// Get status history
$stmt = $conn->prepare("SELECT status, changed_at FROM order_status_log WHERE order_id = ? ORDER BY changed_at ASC");
$stmt->bind_param("s", $order_id);
$stmt->execute();
$history = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $history[] = [
        'status' => $row['status'],
        'time' => date('H:i', strtotime($row['changed_at']))
    ];
}
$stmt->close();

echo json_encode([
    'ok'           => true,
    'status'       => $order['status'],
    'rider_name'   => $order['rider_name'],
    'rider_phone'  => $order['rider_phone'],
    'step'         => match($order['status']) {
        'pending'   => 1,
        'cooking'   => 2,
        'out'       => 3,
        'delivered' => 4,
        default     => 1
    },
    'history'      => $history
]);
?>
