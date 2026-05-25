<?php
// checkout.php — Complete with full original-style UI
session_start();
include 'db_config.php';

// ── Re-fetch role if missing ─────────────────────────────────
if (isset($_SESSION['user_id']) && !isset($_SESSION['user_role'])) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) $_SESSION['user_role'] = $row['role'];
    $stmt->close();
}

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); exit;
}

$user_id = $_SESSION['user_id'];

// Load user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) { session_destroy(); header('Location: index.php'); exit; }

// Load cart
$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) { header('Location: index.php'); exit; }

// ── PLACE ORDER ───────────────────────────────────────────────
$orderError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'place_order') {
    $order_id      = 'CB-' . time();
    $items         = json_encode(array_values($cart));
    $subtotal      = (int) array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));
    $deliv_fee     = 80;
    $total         = $subtotal + $deliv_fee;
    $payment       = $_POST['payment']  ?? 'cod';
    $status        = 'pending';
    $address       = trim($_POST['address'] ?? $user['address']);
    $phone         = trim($_POST['phone']   ?? $user['phone']);
    $note          = trim($_POST['note']    ?? '');
    $customer_name = $user['name'];
    $lat = isset($_POST['lat']) && is_numeric($_POST['lat']) ? (float)$_POST['lat'] : null;
    $lng = isset($_POST['lng']) && is_numeric($_POST['lng']) ? (float)$_POST['lng'] : null;

    $stmt = $conn->prepare(
        "INSERT INTO orders
         (id, customer_id, customer_name, items, subtotal, deliv_fee, total,
          payment, status, address, phone, note, lat, lng)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    );
    $stmt->bind_param(
        "sissiiisssssdd",
        $order_id, $user_id, $customer_name, $items,
        $subtotal, $deliv_fee, $total,
        $payment, $status, $address, $phone, $note, $lat, $lng
    );

    if ($stmt->execute()) {
        // Clear cart from session + DB
        $_SESSION['cart'] = [];
        $conn->query("DELETE FROM user_cart WHERE user_id = $user_id");
        header("Location: track.php?order_id=" . urlencode($order_id));
        exit;
    } else {
        $orderError = "Order failed: " . $stmt->error;
    }
    $stmt->close();
}

// Totals for display
$subtotal  = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));
$deliv_fee = 80;
$total     = $subtotal + $deliv_fee;
$itemCount = array_sum(array_column($cart, 'qty'));
$userName  = $_SESSION['user_name'] ?? 'User';
$userInitials = mb_strtoupper(mb_substr($userName, 0, 2));
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Checkout — CheesyBurgers</title>
<link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --cheese:#F4A800;--cheese-dark:#C97D00;--melt:#FF6B00;
  --sidebar-w:260px;--nav-h:68px;
  --bg:#FFFDF5;--surface:#FFFFFF;--surface2:#FFFBF0;
  --text:#3D1F00;--text2:#5A3A10;--muted:#8A6040;
  --border:rgba(244,168,0,.18);--cshadow:0 4px 20px rgba(0,0,0,.06);
  --sidebar-bg:linear-gradient(180deg,#2D1500,#1A0A00);
  --navbar-bg:rgba(255,253,245,.94);
}
[data-theme="dark"]{
  --bg:#0F0700;--surface:#1C0E00;--surface2:#241100;
  --text:#FFE8B0;--text2:#FFD580;--muted:#A07840;
  --border:rgba(244,168,0,.1);--cshadow:0 4px 20px rgba(0,0,0,.4);
  --navbar-bg:rgba(12,5,0,.96);--sidebar-bg:linear-gradient(180deg,#0A0400,#060200);
}
html{scroll-behavior:smooth;}
body{font-family:'Nunito',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;transition:background .3s,color .3s;}

/* ── SIDEBAR ── */
.sidebar{
  position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;
  background:var(--sidebar-bg);
  display:flex;flex-direction:column;z-index:300;
  border-right:1px solid rgba(244,168,0,.08);
  transition:transform .3s;
}
@media(max-width:900px){.sidebar{transform:translateX(-100%);}.sidebar.open{transform:none;}}
.sb-brand{padding:1.4rem 1.4rem 1rem;border-bottom:1px solid rgba(244,168,0,.1);}
.sb-logo{font-family:'Fredoka One',cursive;font-size:1.3rem;color:var(--cheese);}
.sb-sub{font-size:.7rem;color:#A07840;margin-top:.1rem;}
.sb-user{display:flex;align-items:center;gap:.8rem;padding:1rem 1.4rem;border-bottom:1px solid rgba(244,168,0,.1);}
.sb-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--cheese),var(--melt));display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:800;color:#fff;flex-shrink:0;}
.sb-uname{font-size:.85rem;font-weight:800;color:#FFE8B0;}
.sb-utag{font-size:.7rem;color:#A07840;}
.sb-scroll{flex:1;overflow-y:auto;padding:.6rem 0;}
.sb-scroll::-webkit-scrollbar{width:3px;}
.sb-scroll::-webkit-scrollbar-thumb{background:rgba(244,168,0,.2);}
.sb-label{font-size:.65rem;font-weight:800;color:#A07840;letter-spacing:1.5px;text-transform:uppercase;padding:.6rem 1.4rem .3rem;}
.sb-link{display:flex;align-items:center;gap:.7rem;padding:.6rem 1.4rem;cursor:pointer;transition:all .2s;border-left:3px solid transparent;}
.sb-link:hover{background:rgba(244,168,0,.08);}
.sb-link.active{background:rgba(244,168,0,.12);border-left-color:var(--cheese);}
.sb-link .ico{font-size:1.05rem;width:22px;text-align:center;}
.sb-link .lbl{font-size:.85rem;font-weight:700;color:#C8A060;}
.sb-link:hover .lbl,.sb-link.active .lbl{color:var(--cheese);}
.sb-bottom{padding:1rem 1.2rem;border-top:1px solid rgba(244,168,0,.1);display:flex;flex-direction:column;gap:.5rem;}
.dm-row{display:flex;align-items:center;gap:.6rem;background:rgba(244,168,0,.07);border:1px solid rgba(244,168,0,.1);border-radius:10px;padding:.55rem .9rem;cursor:pointer;font-size:.83rem;color:#C8A060;transition:all .2s;}
.dm-row:hover{background:rgba(244,168,0,.12);}
.dm-pill{width:26px;height:13px;border-radius:7px;background:rgba(244,168,0,.2);margin-left:auto;position:relative;transition:.3s;}
.dm-pill::after{content:'';position:absolute;width:9px;height:9px;border-radius:50%;background:#A07840;top:2px;left:2px;transition:.3s;}
[data-theme="dark"] .dm-pill{background:var(--cheese);}
[data-theme="dark"] .dm-pill::after{transform:translateX(13px);background:#fff;}
.sb-act{display:flex;align-items:center;gap:.6rem;background:rgba(244,168,0,.07);border:1px solid rgba(244,168,0,.1);border-radius:10px;padding:.55rem .9rem;cursor:pointer;font-size:.83rem;color:#C8A060;font-family:inherit;width:100%;transition:all .2s;}
.sb-act:hover{background:rgba(255,107,0,.1);color:var(--melt);}

/* ── TOPBAR ── */
.topbar{
  position:sticky;top:0;z-index:200;
  display:flex;align-items:center;gap:1rem;padding:0 1.5rem;height:var(--nav-h);
  background:var(--navbar-bg);backdrop-filter:blur(12px);
  border-bottom:1px solid var(--border);
  margin-left:var(--sidebar-w);transition:margin .3s;
}
@media(max-width:900px){.topbar{margin-left:0;}}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:.3rem;}
@media(max-width:900px){.hamburger{display:flex;}}
.hamburger span{width:20px;height:2px;background:var(--cheese);border-radius:2px;}
.tb-brand{font-family:'Fredoka One',cursive;font-size:1.3rem;color:var(--cheese);text-decoration:none;}
.tb-brand em{color:var(--melt);font-style:normal;}
.tb-back{margin-left:auto;font-size:.83rem;color:var(--muted);text-decoration:none;padding:.38rem .85rem;border:1px solid var(--border);border-radius:8px;transition:all .2s;}
.tb-back:hover{color:var(--cheese);border-color:var(--cheese);}

/* ── MAIN WRAP ── */
.main-wrap{margin-left:var(--sidebar-w);transition:margin .3s;}
@media(max-width:900px){.main-wrap{margin-left:0;}}

/* ── PAGE TITLE ── */
.page-title{background:linear-gradient(135deg,#FFF5D6,#FFFBF0);padding:2.5rem 2rem;border-bottom:1px solid var(--border);}
[data-theme=dark] .page-title{background:linear-gradient(135deg,#1C0E00,#0F0700);}
.page-title h1{font-family:'Fredoka One',cursive;font-size:2rem;color:var(--text);}
.page-title h1 span{color:var(--cheese);}
.page-title p{color:var(--muted);margin-top:.3rem;font-size:.9rem;}

/* ── STEPS BAR ── */
.steps-bar{display:flex;align-items:center;padding:1.2rem 2rem;background:var(--surface);border-bottom:1px solid var(--border);}
.step-item{display:flex;align-items:center;gap:.5rem;}
.step-circle{width:28px;height:28px;border-radius:50%;border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:800;color:var(--muted);transition:.3s;}
.step-circle.done{background:var(--cheese);border-color:var(--cheese);color:#fff;}
.step-circle.active{background:var(--cheese);border-color:var(--cheese);color:#fff;box-shadow:0 0 0 4px rgba(244,168,0,.2);}
.step-lbl{font-size:.78rem;font-weight:700;color:var(--muted);}
.step-lbl.active{color:var(--cheese);}
.step-line{flex:1;height:2px;background:var(--border);margin:0 .6rem;min-width:20px;}
.step-line.done{background:var(--cheese);}

/* ── CONTENT GRID ── */
.content{display:grid;grid-template-columns:1fr 360px;gap:1.5rem;padding:1.5rem 2rem;align-items:start;}
@media(max-width:860px){.content{grid-template-columns:1fr;padding:1rem;}}

/* ── PANEL / CARD ── */
.panel{display:none;}
.panel.active{display:block;}
.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;overflow:hidden;margin-bottom:1.2rem;box-shadow:var(--cshadow);}
.card-hdr{display:flex;align-items:center;gap:.8rem;padding:1.1rem 1.4rem;border-bottom:1px solid var(--border);}
.card-hdr .hdr-ico{font-size:1.5rem;}
.card-hdr h2{font-size:.95rem;font-weight:800;}
.card-hdr p{font-size:.75rem;color:var(--muted);margin-top:.15rem;}
.card-body{padding:1.2rem 1.4rem;}

/* ── FORM ── */
.frow{display:grid;grid-template-columns:1fr 1fr;gap:.9rem;}
@media(max-width:500px){.frow{grid-template-columns:1fr;}}
.fg{display:flex;flex-direction:column;gap:.3rem;margin-bottom:.85rem;}
.fg label{font-size:.78rem;font-weight:800;color:var(--text2);}
.fg label .req{color:var(--melt);}
.fi{padding:.72rem 1rem;border:1.5px solid var(--border);border-radius:10px;font-size:.9rem;font-family:inherit;background:var(--surface);color:var(--text);outline:none;transition:border .2s;width:100%;}
.fi:focus{border-color:var(--cheese);}
textarea.fi{resize:vertical;min-height:72px;}

/* ── PAYMENT ── */
.pay-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-top:.4rem;}
.pay-opt{border:2px solid var(--border);border-radius:12px;padding:.9rem .8rem;cursor:pointer;transition:all .2s;text-align:center;}
.pay-opt:hover{border-color:var(--cheese);}
.pay-opt.sel{border-color:var(--cheese);background:rgba(244,168,0,.06);}
.po-ico{font-size:1.6rem;margin-bottom:.3rem;}
.po-nm{font-size:.85rem;font-weight:800;}
.po-note{font-size:.72rem;color:var(--muted);margin-top:.15rem;}

/* ── NAV BUTTONS ── */
.btn-row{display:flex;justify-content:flex-end;gap:.75rem;margin-top:.5rem;}
.btn-next{padding:.7rem 1.6rem;background:linear-gradient(135deg,var(--cheese),var(--melt));color:#fff;border:none;border-radius:10px;font-size:.88rem;font-weight:800;cursor:pointer;font-family:inherit;transition:all .2s;}
.btn-next:hover{opacity:.9;transform:translateY(-1px);}
.btn-back{padding:.7rem 1.4rem;border:1.5px solid var(--border);background:transparent;color:var(--text2);border-radius:10px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s;}
.btn-back:hover{border-color:var(--cheese);color:var(--cheese);}

/* ── ORDER SUMMARY (sticky right panel) ── */
.summary-card{position:sticky;top:calc(var(--nav-h) + 1rem);}
.oi{display:flex;align-items:center;gap:.6rem;padding:.5rem 0;border-bottom:1px solid var(--border);}
.oi-em{font-size:1.1rem;width:24px;text-align:center;}
.oi-info{flex:1;}
.oi-name{font-size:.85rem;font-weight:700;}
.oi-qty{font-size:.75rem;color:var(--muted);}
.oi-price{font-size:.85rem;font-weight:800;color:var(--cheese);}
.sum-row{display:flex;justify-content:space-between;padding:.3rem 0;font-size:.83rem;color:var(--muted);}
.sum-total{display:flex;justify-content:space-between;font-size:1.05rem;font-weight:800;color:var(--cheese);border-top:2px solid var(--border);margin-top:.6rem;padding-top:.7rem;}
.btn-place{width:100%;padding:.9rem;background:linear-gradient(135deg,var(--cheese),var(--melt));color:#fff;border:none;border-radius:12px;font-size:.95rem;font-weight:800;cursor:pointer;font-family:inherit;margin-top:1rem;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:.5rem;}
.btn-place:hover{opacity:.9;transform:translateY(-1px);}
.btn-place:disabled{opacity:.5;cursor:not-allowed;transform:none;}

/* ── SUCCESS ── */
.success-box{text-align:center;padding:2.5rem 1rem;}
.s-anim{font-size:4.5rem;display:block;animation:spop .5s ease;}
@keyframes spop{0%{transform:scale(0);}70%{transform:scale(1.2);}100%{transform:scale(1);}}
.success-box h2{font-size:1.6rem;font-weight:800;margin:.8rem 0 .4rem;}
.success-box p{color:var(--muted);font-size:.88rem;}
.s-ord-id{display:inline-block;background:rgba(244,168,0,.1);border:1px solid rgba(244,168,0,.3);border-radius:10px;padding:.5rem 1.2rem;font-size:1rem;font-weight:800;color:var(--cheese);margin:.8rem 0 1.2rem;}
.success-acts{display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap;}
.s-btn{padding:.75rem 1.5rem;border-radius:12px;font-size:.88rem;font-weight:800;cursor:pointer;text-decoration:none;border:none;font-family:inherit;transition:all .2s;}
.s-btn.primary{background:linear-gradient(135deg,var(--cheese),var(--melt));color:#fff;}
.s-btn.outline{border:2px solid var(--cheese);color:var(--cheese);background:transparent;}
.s-btn:hover{opacity:.9;transform:translateY(-1px);}

/* ── ERROR BOX ── */
.err-box{background:#FFE0E0;color:#C00;padding:.75rem 1rem;border-radius:10px;margin-bottom:1rem;font-size:.85rem;border-left:3px solid #C00;}

/* ── MOBILE OVERLAY ── */
.mob-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:299;}
.mob-overlay.show{display:block;}
</style>
</head>
<body>

<!-- MOBILE OVERLAY -->
<div class="mob-overlay" id="mobOverlay" onclick="closeSb()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sb-brand">
    <div class="sb-logo">🧀 CheesyBurgers</div>
    <div class="sb-sub">Seriously Cheesy™</div>
  </div>
  <div class="sb-user">
    <div class="sb-avatar"><?= $userInitials ?></div>
    <div>
      <div class="sb-uname"><?= htmlspecialchars($userName) ?></div>
      <div class="sb-utag">⭐ Gold Member</div>
    </div>
  </div>
  <div class="sb-scroll">
    <div class="sb-label">Navigation</div>
    <div class="sb-link" onclick="window.location='index.php'"><span class="ico">🏠</span><span class="lbl">Home</span></div>
    <div class="sb-link" onclick="window.location='index.php#deals'"><span class="ico">🔥</span><span class="lbl">Hot Deals</span></div>
    <div class="sb-link" onclick="window.location='index.php#menu-section'"><span class="ico">🍔</span><span class="lbl">Full Menu</span></div>
    <div class="sb-link" onclick="window.location='track.php'"><span class="ico">📍</span><span class="lbl">Track Order</span></div>
    <div class="sb-link active"><span class="ico">🛒</span><span class="lbl">Checkout</span></div>

    <div class="sb-label">Your Order</div>
    <?php foreach($cart as $item): ?>
    <div class="sb-link" style="cursor:default;opacity:.8;">
      <span class="ico"><?= $item['e'] ?></span>
      <span class="lbl" style="font-size:.78rem;"><?= htmlspecialchars($item['name']) ?> ×<?= $item['qty'] ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="sb-bottom">
    <button class="dm-row" onclick="toggleDark()">
      <span>🌙</span><span>Dark Mode</span><div class="dm-pill"></div>
    </button>
    <button class="sb-act" onclick="doLogout()"><span class="ico">🚪</span>Logout</button>
  </div>
</aside>

<!-- TOPBAR -->
<div class="topbar">
  <div class="hamburger" onclick="openSb()">
    <span></span><span></span><span></span>
  </div>
  <a href="index.php" class="tb-brand">🧀 Cheesy<em>Burgers</em></a>
  <a href="index.php" class="tb-back">← Back to Menu</a>
</div>

<!-- MAIN WRAP -->
<div class="main-wrap">

  <!-- PAGE TITLE -->
  <div class="page-title">
    <h1>Complete Your <span>Order</span> 🛒</h1>
    <p>Fill details → Confirm → Track in real time</p>
  </div>

  <!-- STEPS BAR -->
  <div class="steps-bar">
    <div class="step-item">
      <div class="step-circle active" id="sc1">1</div>
      <span class="step-lbl active" id="sl1">Your Details</span>
    </div>
    <div class="step-line" id="sline1"></div>
    <div class="step-item">
      <div class="step-circle" id="sc2">2</div>
      <span class="step-lbl" id="sl2">Review</span>
    </div>
    <div class="step-line" id="sline2"></div>
    <div class="step-item">
      <div class="step-circle" id="sc3">✓</div>
      <span class="step-lbl" id="sl3">Confirmed!</span>
    </div>
  </div>

  <?php if($orderError): ?>
  <div style="padding:1rem 2rem;"><div class="err-box">❌ <?= htmlspecialchars($orderError) ?></div></div>
  <?php endif; ?>

  <div class="content">
    <!-- LEFT PANELS -->
    <div>

      <!-- STEP 1: Details -->
      <div class="panel active" id="panel1">
        <div class="card">
          <div class="card-hdr">
            <div class="hdr-ico">👤</div>
            <div><h2>Personal Details</h2><p>Your name, phone and address</p></div>
          </div>
          <div class="card-body">
            <div class="frow">
              <div class="fg">
                <label>Full Name <span class="req">*</span></label>
                <input class="fi" id="custName" type="text" value="<?= htmlspecialchars($user['name']) ?>">
              </div>
              <div class="fg">
                <label>Phone Number <span class="req">*</span></label>
                <input class="fi" id="custPhone" type="tel" value="<?= htmlspecialchars($user['phone']) ?>">
              </div>
            </div>
            <div class="fg">
              <label>Delivery Address <span class="req">*</span></label>
              <input class="fi" id="custAddr" type="text" value="<?= htmlspecialchars($user['address']) ?>">
            </div>
            <div class="frow">
              <div class="fg">
                <label>City</label>
                <select class="fi" id="custCity">
                  <option value="Rawalpindi" <?= $user['city']==='Rawalpindi'?'selected':'' ?>>Rawalpindi</option>
                  <option value="Islamabad"  <?= $user['city']==='Islamabad' ?'selected':'' ?>>Islamabad</option>
                  <option value="Lahore"     <?= $user['city']==='Lahore'    ?'selected':'' ?>>Lahore</option>
                  <option value="Karachi"    <?= $user['city']==='Karachi'   ?'selected':'' ?>>Karachi</option>
                </select>
              </div>
              <div class="fg">
                <label>Order Type</label>
                <select class="fi">
                  <option>🛵 Home Delivery</option>
                </select>
              </div>
            </div>
            <div class="fg">
              <label>Special Instructions</label>
              <textarea class="fi" id="custNote" placeholder="e.g. Extra cheese, no onions, ring the bell..."></textarea>
            </div>
            <div class="fg">
              <label>Payment Method <span class="req">*</span></label>
              <div class="pay-grid">
                <div class="pay-opt sel" id="pay-cod" onclick="selPay('cod')">
                  <div class="po-ico">💵</div>
                  <div class="po-nm">Cash on Delivery</div>
                  <div class="po-note">Pay when delivered</div>
                </div>
                <div class="pay-opt" id="pay-jazzcash" onclick="selPay('jazzcash')">
                  <div class="po-ico">📱</div>
                  <div class="po-nm">JazzCash</div>
                  <div class="po-note">Mobile payment</div>
                </div>
              </div>
            </div>
            <div class="btn-row">
              <button class="btn-next" onclick="goStep(2)">Review Order →</button>
            </div>
          </div>
        </div>
      </div>

      <!-- STEP 2: Review -->
      <div class="panel" id="panel2">
        <div class="card">
          <div class="card-hdr">
            <div class="hdr-ico">📋</div>
            <div><h2>Review Your Order</h2><p>Check everything before confirming</p></div>
          </div>
          <div class="card-body">
            <div id="reviewDetails" style="font-size:.88rem;line-height:2;color:var(--text2);margin-bottom:1rem;background:var(--surface2);padding:.9rem 1rem;border-radius:10px;border:1px solid var(--border);"></div>
            <div class="btn-row">
              <button class="btn-back" onclick="goStep(1)">← Edit Details</button>
              <button class="btn-next" onclick="confirmOrder()">✅ Place Order</button>
            </div>
          </div>
        </div>
      </div>

      <!-- STEP 3: Success -->
      <div class="panel" id="panel3">
        <div class="card">
          <div class="card-body">
            <div class="success-box">
              <span class="s-anim">🎉</span>
              <h2>Order Placed!</h2>
              <p>Your cheesy order is on its way to the kitchen!</p>
              <div class="s-ord-id" id="successOrderId"></div>
              <div class="success-acts">
                <a href="index.php" class="s-btn primary">🍔 Order More</a>
                <a href="#" class="s-btn outline" id="trackBtn">📍 Track Order</a>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /left -->

    <!-- RIGHT: Summary -->
    <div class="summary-card">
      <div class="card">
        <div class="card-hdr">
          <div class="hdr-ico">🛒</div>
          <div><h2>Order Summary</h2><p><?= $itemCount ?> item(s) in cart</p></div>
        </div>
        <div class="card-body">
          <?php foreach($cart as $item): ?>
          <div class="oi">
            <div class="oi-em"><?= $item['e'] ?></div>
            <div class="oi-info">
              <div class="oi-name"><?= htmlspecialchars($item['name']) ?></div>
              <div class="oi-qty">×<?= $item['qty'] ?></div>
            </div>
            <div class="oi-price">Rs.<?= number_format($item['price'] * $item['qty']) ?></div>
          </div>
          <?php endforeach; ?>
          <div style="margin-top:.8rem">
            <div class="sum-row"><span>Subtotal</span><span>Rs.<?= number_format($subtotal) ?></span></div>
            <div class="sum-row"><span>Delivery Fee</span><span>Rs.<?= number_format($deliv_fee) ?></span></div>
            <div class="sum-total"><span>Total</span><span>Rs.<?= number_format($total) ?></span></div>
          </div>
          <button class="btn-place" id="placeBtn" onclick="confirmOrder()">🛒 Place Order — Rs.<?= number_format($total) ?></button>
        </div>
      </div>
      <div style="font-size:.75rem;color:var(--muted);text-align:center;margin-top:.5rem;">
        🔒 Secure checkout &nbsp;|&nbsp; 30-min delivery guarantee
      </div>
    </div>

  </div>
</div><!-- /main-wrap -->

<!-- Hidden order form -->
<form id="orderForm" method="POST" style="display:none">
  <input name="action"  value="place_order">
  <input name="payment" id="fPayment" value="cod">
  <input name="address" id="fAddress">
  <input name="phone"   id="fPhone">
  <input name="note"    id="fNote">
  <input name="lat"     id="fLat" value="">
  <input name="lng"     id="fLng" value="">
</form>

<script>
let selPayment = 'cod';
let curStep    = 1;

// ── Payment select ───────────────────────────────────────────
function selPay(m) {
  selPayment = m;
  document.querySelectorAll('.pay-opt').forEach(o => o.classList.remove('sel'));
  document.getElementById('pay-' + m).classList.add('sel');
}

// ── Step navigation ──────────────────────────────────────────
function goStep(n) {
  // Validate step 1
  if (n === 2) {
    const addr  = document.getElementById('custAddr').value.trim();
    const phone = document.getElementById('custPhone').value.trim();
    const name  = document.getElementById('custName').value.trim();
    if (!name)  { alert('Please enter your name');    return; }
    if (!phone) { alert('Please enter phone number'); return; }
    if (!addr)  { alert('Please enter your address'); return; }

    // Fill review panel
    const payLabel = selPayment === 'cod' ? '💵 Cash on Delivery' : '📱 JazzCash';
    document.getElementById('reviewDetails').innerHTML =
      `<b>👤 Name:</b> ${name}<br>
       <b>📞 Phone:</b> ${phone}<br>
       <b>📍 Address:</b> ${addr}<br>
       <b>🏙️ City:</b> ${document.getElementById('custCity').value}<br>
       <b>💳 Payment:</b> ${payLabel}<br>
       <b>📝 Note:</b> ${document.getElementById('custNote').value || '—'}`;
  }

  // Hide all panels
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.getElementById('panel' + n).classList.add('active');
  curStep = n;

  // Update step bar
  for (let i = 1; i <= 3; i++) {
    const c = document.getElementById('sc' + i);
    const l = document.getElementById('sl' + i);
    if (i < n)       { c.classList.add('done');   c.classList.remove('active'); }
    else if (i === n){ c.classList.add('active');  c.classList.remove('done');  }
    else             { c.classList.remove('done','active'); }
    l.classList.toggle('active', i === n);
    if (i < 3) {
      const line = document.getElementById('sline' + i);
      line.classList.toggle('done', i < n);
    }
  }

  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Confirm & submit order ────────────────────────────────────
function confirmOrder() {
  const addr  = document.getElementById('custAddr').value.trim();
  const phone = document.getElementById('custPhone').value.trim();
  const name  = document.getElementById('custName').value.trim();
  if (!name || !addr || !phone) { goStep(1); return; }

  const btn = document.getElementById('placeBtn');
  btn.disabled = true;
  btn.textContent = '⏳ Placing order...';

  document.getElementById('fPayment').value = selPayment;
  document.getElementById('fAddress').value = addr;
  document.getElementById('fPhone').value   = phone;
  document.getElementById('fNote').value    = document.getElementById('custNote').value;

  document.getElementById('orderForm').submit();
}

// ── Sidebar ──────────────────────────────────────────────────
function openSb() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('mobOverlay').classList.add('show');
}
function closeSb() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('mobOverlay').classList.remove('show');
}

// ── Logout ───────────────────────────────────────────────────
function doLogout() {
  const fd = new FormData(); fd.append('action','logout');
  fetch('auth.php',{method:'POST',body:fd}).then(()=>window.location='index.php');
}

// ── Dark mode ─────────────────────────────────────────────────
function toggleDark() {
  const t = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', t);
  localStorage.setItem('cb_theme', t);
}
(()=>{ const t=localStorage.getItem('cb_theme'); if(t) document.documentElement.setAttribute('data-theme',t); })();
</script>
</body>
</html>
