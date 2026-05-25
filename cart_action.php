<?php
// cart_action.php — DB-backed persistent cart
session_start();
include 'db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

$action  = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

function syncToDB($conn, $user_id, $cart) {
    if (!$user_id) return;
    $conn->query("DELETE FROM user_cart WHERE user_id=$user_id");
    if (empty($cart)) return;
    $stmt = $conn->prepare("INSERT INTO user_cart (user_id,item_id,name,emoji,price,qty) VALUES(?,?,?,?,?,?)");
    foreach ($cart as $item) {
        $stmt->bind_param("iissii",$user_id,$item['id'],$item['name'],$item['e'],$item['price'],$item['qty']);
        $stmt->execute();
    }
    $stmt->close();
}

if ($action === 'add') {
    $id    = (int)($_POST['id']    ?? 0);
    $name  = trim($_POST['name']   ?? '');
    $emoji = trim($_POST['emoji']  ?? '🍔');
    $price = (int)($_POST['price'] ?? 0);
    if ($id && $name && $price > 0) {
        if (isset($_SESSION['cart'][$id])) $_SESSION['cart'][$id]['qty']++;
        else $_SESSION['cart'][$id] = ['id'=>$id,'name'=>$name,'e'=>$emoji,'price'=>$price,'qty'=>1];
        syncToDB($conn, $user_id, $_SESSION['cart']);
    }
    echo json_encode(['success'=>true,'cart'=>$_SESSION['cart']]);
}

elseif ($action === 'update') {
    $id    = (int)($_POST['id']    ?? 0);
    $delta = (int)($_POST['delta'] ?? 0);
    if (isset($_SESSION['cart'][$id])) {
        $_SESSION['cart'][$id]['qty'] += $delta;
        if ($_SESSION['cart'][$id]['qty'] <= 0) unset($_SESSION['cart'][$id]);
        syncToDB($conn, $user_id, $_SESSION['cart']);
    }
    echo json_encode(['success'=>true,'cart'=>$_SESSION['cart']]);
}

elseif ($action === 'clear') {
    $_SESSION['cart'] = [];
    if ($user_id) $conn->query("DELETE FROM user_cart WHERE user_id=$user_id");
    echo json_encode(['success'=>true,'cart'=>[]]);
}

else { echo json_encode(['success'=>false,'error'=>'Unknown']); }
?>
