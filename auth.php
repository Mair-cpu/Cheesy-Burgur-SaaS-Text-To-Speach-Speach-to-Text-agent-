<?php
// auth.php — handles login / signup / logout with DB-backed cart sync
session_start();
include 'db_config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'error' => 'Bad request']); exit;
}

$action = $_POST['action'];

// ── helpers ──────────────────────────────────────────────────
function loadCartFromDB($conn, $user_id) {
    $cart = [];
    $stmt = $conn->prepare("SELECT item_id,name,emoji,price,qty FROM user_cart WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $cart[$r['item_id']] = [
            'id'    => $r['item_id'],
            'name'  => $r['name'],
            'e'     => $r['emoji'],
            'price' => (int)$r['price'],
            'qty'   => (int)$r['qty'],
        ];
    }
    $stmt->close();
    return $cart;
}

function saveCartToDB($conn, $user_id, $cart) {
    $conn->query("DELETE FROM user_cart WHERE user_id=$user_id");
    if (empty($cart)) return;
    $stmt = $conn->prepare("INSERT INTO user_cart (user_id,item_id,name,emoji,price,qty) VALUES(?,?,?,?,?,?)");
    foreach ($cart as $item) {
        $stmt->bind_param("iissii", $user_id, $item['id'], $item['name'], $item['e'], $item['price'], $item['qty']);
        $stmt->execute();
    }
    $stmt->close();
}

// ── SIGNUP ───────────────────────────────────────────────────
if ($action === 'signup') {
    $email   = trim($_POST['email']   ?? '');
    $name    = trim($_POST['name']    ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $address = trim($_POST['address'] ?? '');
    $city    = trim($_POST['city']    ?? '');
    $raw_pw  = $_POST['password']     ?? '';

    if (!$email || !$name || !$raw_pw) {
        echo json_encode(['success'=>false,'error'=>'Fill all required fields']); exit;
    }
    $chk = $conn->prepare("SELECT id FROM users WHERE email=?");
    $chk->bind_param("s",$email); $chk->execute(); $chk->store_result();
    if ($chk->num_rows > 0) {
        echo json_encode(['success'=>false,'error'=>'Email already registered']); $chk->close(); exit;
    }
    $chk->close();

    $pw = password_hash($raw_pw, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (email,password,name,phone,address,city,role) VALUES(?,?,?,?,?,?,'user')");
    $stmt->bind_param("ssssss",$email,$pw,$name,$phone,$address,$city);
    if ($stmt->execute()) {
        $uid = $stmt->insert_id;
        $_SESSION['user_id']    = $uid;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name']  = $name;
        $_SESSION['user_role']  = 'user';
        // save any guest cart to DB
        if (!empty($_SESSION['cart'])) saveCartToDB($conn, $uid, $_SESSION['cart']);
        echo json_encode(['success'=>true,'name'=>$name,'is_admin'=>false]);
    } else {
        echo json_encode(['success'=>false,'error'=>'Signup failed']);
    }
    $stmt->close();
}

// ── LOGIN ────────────────────────────────────────────────────
elseif ($action === 'login') {
    $email  = trim($_POST['email']    ?? '');
    $raw_pw = $_POST['password']      ?? '';
    if (!$email || !$raw_pw) {
        echo json_encode(['success'=>false,'error'=>'Enter email and password']); exit;
    }
    $stmt = $conn->prepare("SELECT id,password,name,role FROM users WHERE email=?");
    $stmt->bind_param("s",$email); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();

    if (!$row)                                   { echo json_encode(['success'=>false,'error'=>'No account with this email']); exit; }
    if (!password_verify($raw_pw,$row['password'])) { echo json_encode(['success'=>false,'error'=>'Wrong password']); exit; }

    $_SESSION['user_id']    = $row['id'];
    $_SESSION['user_email'] = $email;
    $_SESSION['user_name']  = $row['name'];
    $_SESSION['user_role']  = $row['role'];

    // Merge guest cart with DB cart, then load
    $guestCart = $_SESSION['cart'] ?? [];
    $dbCart    = loadCartFromDB($conn, $row['id']);
    foreach ($guestCart as $id => $item) {
        if (isset($dbCart[$id])) $dbCart[$id]['qty'] += $item['qty'];
        else $dbCart[$id] = $item;
    }
    saveCartToDB($conn, $row['id'], $dbCart);
    $_SESSION['cart'] = $dbCart;

    echo json_encode(['success'=>true,'name'=>$row['name'],'is_admin'=>($row['role']==='admin')]);
}

// ── LOGOUT ───────────────────────────────────────────────────
elseif ($action === 'logout') {
    // save cart to DB before logout
    if (isset($_SESSION['user_id']) && !empty($_SESSION['cart'])) {
        saveCartToDB($conn, $_SESSION['user_id'], $_SESSION['cart']);
    }
    session_unset(); session_destroy();
    echo json_encode(['success'=>true]);
}

else { echo json_encode(['success'=>false,'error'=>'Unknown action']); }
?>
