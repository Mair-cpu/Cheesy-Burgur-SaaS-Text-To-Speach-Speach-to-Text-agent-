<?php
// ── TEMP: Show errors (remove after fixing) ───────────────────
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ─────────────────────────────────────────────────────────────
// ================================================================
//  agent.php — Cheesy Burgers AI Ordering Agent
//  Place in: xampp/htdocs/cheesyburgers/agent.php
//  Works with: db_config.php, auth.php, cart_action.php
// ================================================================
session_start();
include 'db_config.php';

// ── Load LIVE menu from database ─────────────────────────────
$menu_by_cat = [];
$all_menu = [];
$res = $conn->query("SELECT * FROM menu WHERE avail=1 ORDER BY cat, name");
while ($r = $res->fetch_assoc()) {
  $menu_by_cat[$r['cat']][] = $r;
  $all_menu[] = $r;
}

// ── Session info ─────────────────────────────────────────────
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? 'Guest';
$userInitials = $isLoggedIn ? mb_strtoupper(mb_substr($userName, 0, 2)) : 'GU';
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$cart = $_SESSION['cart'] ?? [];
$cartCount = array_sum(array_column($cart, 'qty'));

// ── Build menu JSON for JS ────────────────────────────────────
$menu_json = json_encode($all_menu, JSON_UNESCAPED_UNICODE);

// ── Load recent order history if logged in ───────────────────
$orders = [];
if ($isLoggedIn) {
  $uid = $_SESSION['user_id'];
  $oq = $conn->query("SELECT id, total AS total_price, status, time AS created_at FROM orders WHERE customer_id=$uid ORDER BY id DESC LIMIT 5");
  if ($oq) {
    while ($or = $oq->fetch_assoc()) { $orders[] = $or; }
  }
}
$orders_json = json_encode($orders);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CheeseBot AI — Cheesy Burgers</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    :root {
      --bg: #070400; --panel: #110b03; --accent: #ffb800; --accent-rgb: 255,184,0;
      --text: #ffffff; --text-muted: #a19a91; --border: rgba(255,184,0,0.12);
      --glass: rgba(17,11,3,0.75); --shadow: rgba(0,0,0,0.6);
      --msg-usr: #ffb800; --msg-usr-text: #000000; --msg-bot: #1c1308;
    }
    [data-theme="light"] {
      --bg: #f9f6f0; --panel: #ffffff; --accent: #d48800; --accent-rgb: 212,136,0;
      --text: #1a1610; --text-muted: #70695f; --border: rgba(212,136,0,0.18);
      --glass: rgba(255,255,255,0.85); --shadow: rgba(140,120,90,0.15);
      --msg-usr: #d48800; --msg-usr-text: #ffffff; --msg-bot: #f0eae1;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; transition: background-color 0.25s, border-color 0.25s; }
    body { background: var(--bg); color: var(--text); overflow: hidden; height: 100vh; display: flex; }

    /* Layout */
    .app-container { display: flex; width: 100vw; height: 100vh; position: relative; }
    
    /* Sidebar */
    .sidebar { width: 280px; background: var(--panel); border-right: 1px solid var(--border); display: flex; flex-direction: column; z-index: 99; }
    .brand { padding: 24px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid var(--border); }
    .brand-logo { width: 40px; height: 40px; background: var(--accent); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; box-shadow: 0 4px 12px rgba(var(--accent-rgb), 0.3); }
    .brand-name { font-weight: 800; font-size: 19px; letter-spacing: -0.5px; }
    .brand-name span { color: var(--accent); }
    
    .sb-menu { flex: 1; padding: 16px; display: flex; flex-direction: column; gap: 8px; overflow-y: auto; }
    .sb-btn { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 10px; color: var(--text-muted); text-decoration: none; font-weight: 500; font-size: 14.5px; border: none; background: transparent; cursor: pointer; text-align: left; width: 100%; }
    .sb-btn:hover, .sb-btn.active { color: var(--text); background: rgba(var(--accent-rgb), 0.08); }
    .sb-btn i { font-size: 16px; width: 20px; text-align: center; color: var(--accent); }
    .sb-btn .badge { margin-left: auto; background: var(--accent); color: #000; font-size: 11px; font-weight: 700; padding: 2px 7px; border-radius: 20px; }
    
    .sb-user { padding: 16px; border-top: 1px solid var(--border); display: flex; align-items: center; gap: 12px; }
    .u-avatar { width: 40px; height: 40px; background: rgba(var(--accent-rgb), 0.15); border: 1px solid var(--border); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--accent); font-size: 14px; }
    .u-info { flex: 1; min-width: 0; }
    .u-name { font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .u-role { font-size: 11px; color: var(--text-muted); }

    /* Main Area */
    .main-content { flex: 1; display: flex; flex-direction: column; height: 100%; background: var(--bg); position: relative; }
    
    /* Top Bar */
    .top-bar { height: 70px; padding: 0 24px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); background: var(--glass); backdrop-filter: blur(12px); z-index: 10; }
    .bot-status-wrap { display: flex; align-items: center; gap: 12px; }
    .status-dot { width: 8px; height: 8px; background: #00e676; border-radius: 50%; box-shadow: 0 0 10px #00e676; }
    .top-title { font-weight: 700; font-size: 16px; }
    .top-subtitle { font-size: 12px; color: var(--text-muted); }
    .top-actions { display: flex; align-items: center; gap: 12px; }
    
    .ib { background: rgba(var(--accent-rgb), 0.08); border: 1px solid var(--border); color: var(--text); padding: 8px 14px; border-radius: 8px; font-size: 13.5px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px; }
    .ib:hover { background: rgba(var(--accent-rgb), 0.15); }
    .ib.on { border-color: var(--accent); color: var(--accent); font-weight: 600; }

    /* Messages Stream */
    .messages-box { flex: 1; overflow-y: auto; padding: 24px; display: flex; flex-direction: column; gap: 20px; }
    .msg-row { display: flex; gap: 14px; max-width: 80%; }
    .msg-row.user { align-self: flex-end; flex-direction: row-reverse; }
    .msg-av { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
    .msg-av.bot { background: rgba(var(--accent-rgb), 0.15); border: 1px solid var(--border); }
    .msg-av.usr { background: var(--accent); color: #000; font-weight: 700; font-size: 13px; border-radius: 50%; }
    
    .bubble { padding: 14px 18px; border-radius: 16px; font-size: 14.5px; line-height: 1.55; box-shadow: 0 4px 12px var(--shadow); position: relative; }
    .msg-row:not(.user) .bubble { background: var(--msg-bot); border: 1px solid var(--border); border-top-left-radius: 4px; }
    .msg-row.user .bubble { background: var(--msg-usr); color: var(--msg-usr-text); border-top-right-radius: 4px; font-weight: 500; }
    .msg-meta { font-size: 11px; color: var(--text-muted); margin-top: 6px; padding: 0 4px; display: flex; gap: 8px; }
    .msg-row.user .msg-meta { justify-content: flex-end; }

    /* Typing Animation */
    .typing-wrap { display: flex; align-items: center; gap: 5px; padding: 6px 4px; }
    .td { width: 7px; height: 7px; background: var(--accent); border-radius: 50%; animation: bounce 1.4s infinite ease-in-out both; }
    .td:nth-child(1) { animation-delay: -0.32s; }
    .td:nth-child(2) { animation-delay: -0.16s; }
    @keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1.0); } }

    /* Structured Layout Components */
    .mgrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; margin-top: 12px; width: 100%; max-width: 480px; }
    .mi { background: rgba(var(--accent-rgb), 0.04); border: 1px solid var(--border); border-radius: 12px; padding: 12px; text-align: center; position: relative; }
    .mi:hover { border-color: rgba(var(--accent-rgb), 0.3); background: rgba(var(--accent-rgb), 0.08); }
    .mi-em { font-size: 24px; display: block; margin-bottom: 6px; }
    .mi-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .mi-price { font-size: 12px; color: var(--accent); font-weight: 700; margin: 4px 0 8px 0; }
    .mi-add { width: 100%; background: var(--accent); color: #000; border: none; padding: 6px 0; border-radius: 6px; font-size: 11.5px; font-weight: 700; cursor: pointer; }
    .mi-add:hover { opacity: 0.9; }

    .w-card { background: rgba(var(--accent-rgb), 0.03); border: 1px solid var(--border); border-radius: 14px; padding: 16px; margin-top: 12px; width: 100%; max-width: 400px; box-shadow: inset 0 0 12px rgba(var(--accent-rgb), 0.02); }
    .w-title { font-weight: 700; font-size: 14px; color: var(--accent); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
    .ci-row { display: flex; justify-content: space-between; font-size: 13.5px; padding: 6px 0; border-bottom: 1px solid rgba(var(--accent-rgb), 0.05); }
    .ci-row:last-of-type { border-bottom: none; }
    .w-total { margin-top: 12px; padding-top: 10px; border-top: 1px dashed var(--border); display: flex; justify-content: space-between; font-weight: 700; font-size: 15px; color: var(--text); }
    
    .deal-item { display: flex; align-items: center; gap: 12px; padding: 10px; border-radius: 8px; background: rgba(var(--accent-rgb), 0.04); margin-bottom: 6px; cursor: pointer; border: 1px solid transparent; }
    .deal-item:hover { border-color: var(--border); background: rgba(var(--accent-rgb), 0.08); }
    .di-em { font-size: 20px; }
    .di-name { flex: 1; font-size: 13px; font-weight: 600; }
    .di-price { font-size: 12px; font-weight: 700; color: var(--accent); }

    .wf-group { margin-bottom: 10px; text-align: left; }
    .wf-group label { display: block; font-size: 11px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 4px; font-weight: 600; }
    .wf-input { width: 100%; background: rgba(0,0,0,0.2); border: 1px solid var(--border); padding: 8px 12px; border-radius: 6px; color: #fff; font-size: 13px; }
    [data-theme="light"] .wf-input { background: rgba(0,0,0,0.04); color: #000; }
    .wf-submit { width: 100%; background: #00e676; color: #000; font-weight: 700; border: none; padding: 10px; border-radius: 6px; cursor: pointer; margin-top: 6px; font-size: 13.5px; box-shadow: 0 4px 10px rgba(0,230,118,0.25); }

    /* Quick Replies Suggestions */
    .qr-container { display: flex; gap: 8px; padding: 0 24px 12px 24px; overflow-x: auto; flex-shrink: 0; white-space: nowrap; }
    .qr-container::-webkit-scrollbar { height: 0px; }
    .qr-chip { background: var(--panel); border: 1px solid var(--border); color: var(--text); padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 500; cursor: pointer; display: inline-block; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
    .qr-chip:hover { border-color: var(--accent); background: rgba(var(--accent-rgb), 0.05); color: var(--accent); }

    /* Floating Cart Activator Sticky Anchor */
    .float-cart { position: absolute; bottom: 95px; left: 24px; right: 24px; background: linear-gradient(135deg, #231604, #120a00); border: 1px solid var(--accent); padding: 12px 20px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 8px 24px rgba(0,0,0,0.5); opacity: 0; transform: translateY(10px); pointer-events: none; transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); z-index: 5; }
    .float-cart.show { opacity: 1; transform: translateY(0); pointer-events: auto; }
    .fc-left { display: flex; align-items: center; gap: 12px; color: #fff; }
    .fc-icon { font-size: 20px; color: var(--accent); animation: pulse 2s infinite; }
    @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
    .fc-title { font-weight: 700; font-size: 14.5px; }
    .fc-sub { font-size: 11.5px; color: #a19a91; margin-top: 1px; }
    .fc-btn { background: var(--accent); color: #000; font-weight: 700; border: none; padding: 8px 16px; border-radius: 8px; font-size: 13px; cursor: pointer; display: flex; align-items: center; gap: 6px; }

    /* Chat Input Controls Engine */
    .chat-input-area { padding: 16px 24px; background: var(--glass); backdrop-filter: blur(12px); border-top: 1px solid var(--border); display: flex; flex-direction: column; gap: 8px; flex-shrink: 0; z-index: 10; }
    .input-wrapper { display: flex; align-items: center; gap: 12px; width: 100%; position: relative; }
    .chat-input-box { flex: 1; height: 48px; background: rgba(0,0,0,0.25); border: 1px solid var(--border); border-radius: 10px; padding: 0 16px; color: #fff; font-size: 14px; outline: none; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2); }
    [data-theme="light"] .chat-input-box { background: rgba(0,0,0,0.03); color: #000; }
    .chat-input-box:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(var(--accent-rgb), 0.15); }
    
    .mic-btn { width: 48px; height: 48px; border-radius: 10px; background: rgba(var(--accent-rgb), 0.08); border: 1px solid var(--border); color: var(--accent); font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .mic-btn:hover { background: rgba(var(--accent-rgb), 0.15); }
    .send-btn { height: 48px; padding: 0 20px; border-radius: 10px; background: var(--accent); color: #000; font-weight: 700; border: none; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 14px; }
    .send-btn:hover { opacity: 0.95; box-shadow: 0 4px 12px rgba(var(--accent-rgb), 0.25); }
    .send-btn:disabled { opacity: 0.4; cursor: not-allowed; }

    /* Voice Call Interface Overlay Views */
    .voice-overlay { position: absolute; inset: 0; background: rgba(7,4,0,0.96); z-index: 100; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
    .voice-overlay.active { opacity: 1; pointer-events: auto; }
    .vo-avatar-wrap { position: relative; width: 140px; height: 140px; display: flex; align-items: center; justify-content: center; margin-bottom: 24px; }
    .vo-circle { position: absolute; inset: 0; border-radius: 50%; background: rgba(var(--accent-rgb), 0.05); border: 1px solid rgba(var(--accent-rgb), 0.15); transform: scale(1); }
    .vo-avatar-wrap.listening .vo-circle { animation: pulseCircle 2s infinite cubic-bezier(0.4, 0, 0.6, 1); }
    .vo-avatar-wrap.speaking .vo-circle { border-color: rgba(0,230,118,0.3); background: rgba(0,230,118,0.03); animation: pulseCircle 1.5s infinite ease-in-out; }
    @keyframes pulseCircle { 0% { transform: scale(0.95); opacity: 0.8; } 50% { transform: scale(1.3); opacity: 0.2; } 100% { transform: scale(1.4); opacity: 0; } }
    
    .vo-center { width: 90px; height: 90px; background: var(--panel); border: 2px solid var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 42px; box-shadow: 0 8px 32px rgba(0,0,0,0.5); z-index: 2; }
    .vo-avatar-wrap.speaking .vo-center { border-color: #00e676; }
    
    .vo-status { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 4px; }
    .vo-status.listening { color: var(--accent); }
    .vo-status.speaking { color: #00e676; }
    .vo-hint { font-size: 13px; color: var(--text-muted); margin-bottom: 32px; }
    
    .vo-wave { display: flex; align-items: center; gap: 4px; height: 40px; margin-bottom: 4px; }
    .vo-bar { width: 4px; height: 4px; background: rgba(255,184,0,0.2); border-radius: 2px; transition: height 0.1s ease, background-color 0.3s; }
    .vo-bar.active { background: var(--accent); }
    .vo-bar.green { background: #00e676; }
    
    .vo-close { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); padding: 10px 24px; border-radius: 30px; color: #fff; font-weight: 600; font-size: 13.5px; cursor: pointer; display: flex; align-items: center; gap: 8px; margin-top: 20px; }
    .vo-close:hover { background: rgba(255,44,44,0.15); border-color: rgba(255,44,44,0.3); color: #ff4444; }

    .vo-transcript-box { width: 85%; max-width: 440px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 12px; padding: 14px; max-height: 120px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; }
    .vo-bubble { font-size: 13px; line-height: 1.4; color: #e1dacb; }
    .vo-bubble.u { color: var(--accent); font-weight: 500; text-align: right; }

    /* Toast System Notify alerts */
    .toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(40px); background: #1c150c; border: 1px solid var(--accent); color: #fff; padding: 12px 24px; border-radius: 30px; font-weight: 600; font-size: 13.5px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); z-index: 1000; opacity: 0; pointer-events: none; transition: 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    
    .mob-sb { display: none; position: fixed; top: 18px; left: 16px; z-index: 100; background: var(--panel); border: 1px solid var(--border); color: var(--text); width: 36px; height: 36px; border-radius: 8px; align-items: center; justify-content: center; font-size: 18px; cursor: pointer; }
    
    @media (max-width: 820px) {
      .sidebar { position: fixed; top: 0; bottom: 0; left: -280px; transition: left 0.3s ease; }
      .sidebar.open { left: 0; }
      .mob-sb { display: flex; }
      .top-bar { padding-left: 68px; }
      .messages-box { padding: 16px; }
      .msg-row { max-width: 90%; }
    }
  </style>
</head>
<body>

  <div class="app-container">
    
    <div class="sidebar" id="sidebar">
      <div class="brand">
        <div class="brand-logo">🧀</div>
        <div class="brand-name">Cheese<span>Bot</span></div>
      </div>
      
      <div class="sb-menu">
        <a href="index.php" class="sb-btn"><i class="fa-solid fa-house"></i> Home Screen</a>
        <button class="sb-btn active" onclick="clearChat()"><i class="fa-solid fa-message"></i> New Order Chat</button>
        <button class="sb-btn" onclick="sq('🍔 Show Burgers')"><i class="fa-solid fa-burger"></i> Burgers Menu</button>
        <button class="sb-btn" onclick="sq('🍕 Show Pizzas')"><i class="fa-solid fa-pizza-slice"></i> Pizza Stream</button>
        <button class="sb-btn" onclick="sq('🔥 Hot Deals')"><i class="fa-solid fa-fire"></i> Best Deals</button>
        <button class="sb-btn" onclick="sq('🛒 Place an order')"><i class="fa-solid fa-basket-shopping"></i> Checkout View <span class="badge" id="sbCart">0</span></button>
        
        <div style="margin-top: auto; padding: 8px 0; border-top: 1px solid var(--border); display: flex; flex-direction: column; gap: 4px;">
          <button class="sb-btn tts-toggle" id="ttsTgl" onclick="toggleTTS()"><i class="fa-solid fa-volume-high"></i> Voice Engine</button>
          <button class="sb-btn" onclick="toggleDark()"><i class="fa-solid fa-moon" id="dmIco"></i> <span id="dmLbl">Dark Theme</span></button>
        </div>
      </div>

      <div class="sb-user">
        <div class="u-avatar"><?= $userInitials ?></div>
        <div class="u-info">
          <div class="u-name"><?= htmlspecialchars($userName) ?></div>
          <div class="u-role"><?= $isAdmin ? 'Store Administrator' : 'Valued Customer' ?></div>
        </div>
        <?php if($isLoggedIn): ?>
          <a href="#" onclick="doLogout()" style="color: var(--text-muted); font-size: 15px;" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></a>
        <?php else: ?>
          <a href="login.php" style="color: var(--accent); font-size: 13px; font-weight: 700; text-decoration: none;">Login</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="main-content">
      
      <div class="top-bar">
        <div class="bot-status-wrap">
          <div class="status-dot"></div>
          <div>
            <div class="top-title">CheeseBot 2.0</div>
            <div class="top-subtitle">Ultra-fast AI ordering agent engine</div>
          </div>
        </div>
        
        <div class="top-actions">
          <button class="ib" id="ttsBtn" onclick="toggleTTS()"><i class="fa-solid fa-volume-high"></i> Voice On</button>
          <button class="ib" onclick="activateVoiceCall()"><i class="fa-solid fa-phone"></i> Voice Call Mode</button>
        </div>
      </div>

      <div class="messages-box" id="messages"></div>

      <div class="qr-container" id="qrContainer"></div>

      <div class="float-cart" id="floatCart">
        <div class="fc-left">
          <i class="fa-solid fa-cart-shopping fc-icon"></i>
          <div>
            <div class="fc-title"><span id="fcCt">0</span> Items Selected</div>
            <div class="fc-sub">Current order valuation: <strong id="fcTotal">Rs.0</strong></div>
          </div>
        </div>
        <button class="fc-btn" onclick="sq('🛒 Place an order')">Checkout Order <i class="fa-solid fa-arrow-right"></i></button>
      </div>

      <div class="chat-input-area">
        <div class="input-wrapper">
          <button id="micBtn" type="button" class="mic-btn" onclick="toggleVoiceRecording()" title="Tap to Speak">
            🎤
          </button>
          <input type="text" id="msgInput" class="chat-input-box" placeholder="Type a message or tap mic to speak your order..." onkeydown="if(event.key==='Enter') sendMsg()" />
          <button id="sendBtn" type="button" class="send-btn" onclick="sendMsg()">
            Send <i class="fa-solid fa-paper-plane"></i>
          </button>
        </div>
        <div id="audioStatusIndicator" style="display: none; margin-top: 4px; font-size: 12px; color: #ffb800; font-weight: bold; font-style: italic; padding-left: 2px;">
          🔴 Recording... Tap mic again to stop and send!
        </div>
      </div>

    </div>
  </div>

  <button class="mob-sb" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
  <div class="toast" id="toast"></div>

  <div class="voice-overlay" id="voiceOverlay">
    <div class="vo-avatar-wrap" id="voAvatarWrap">
      <div class="vo-circle"></div>
      <div class="vo-circle" style="animation-delay: 0.5s;"></div>
      <div class="vo-center">🧀</div>
    </div>
    
    <div class="vo-status" id="voStatus">Connecting...</div>
    <div class="vo-hint" id="voHint">Starting AI call stream service</div>
    
    <div class="vo-wave" id="voWave">
      <div class="vo-bar"></div><div class="vo-bar"></div><div class="vo-bar"></div>
      <div class="vo-bar"></div><div class="vo-bar"></div><div class="vo-bar"></div>
      <div class="vo-bar"></div><div class="vo-bar"></div><div class="vo-bar"></div>
    </div>

    <div class="vo-transcript-box" id="voTranscript"></div>

    <button class="vo-close" onclick="deactivateVoiceCall()"><i class="fa-solid fa-phone-slash"></i> Disconnect Call</button>
  </div>

  <script>
    /* ── Live Menu Context State data bindings ────────────────────── */
    const DB_MENU = <?= $menu_json ?>;
    const USER_ORDERS = <?= $orders_json ?>;
    const LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;
    const USER_NAME = <?= json_encode($userName) ?>;
    const USER_INITIALS = <?= json_encode($userInitials) ?>;

    const MENU = { burger: [], pizza: [], fries: [], wrap: [], dessert: [], drink: [] };
    DB_MENU.forEach(item => {
      const cat = item.cat || 'burger';
      if (!MENU[cat]) MENU[cat] = [];
      MENU[cat].push({ id: parseInt(item.id), name: item.name, e: item.emoji, price: parseInt(item.price), desc: item.desc || '' });
    });
    function allItems() { return Object.values(MENU).flat(); }
    function itemById(id) { return allItems().find(i => i.id === id); }

    const DEALS = allItems().slice(0, 6).map((it, i) => ({
      ...it,
      badge: ['20% OFF', 'HOT 🔥', 'BUY 1 GET 1', '25% OFF', 'FAN FAV ⭐', 'NEW'][i % 6],
      savings: [200, 250, it.price, 150, 70, 100][i % 6]
    }));

    const PROMOS = { CHEESE10: 10, NEWUSER: 15, WELCOME20: 20 };

    let cart = [];
    let convHistory = [];
    let ttsEnabled = true;
    let isTyping = false; 
    let promoApplied = null;
    let selectedPay = 'cod';

    // ── CORE AUDIO VARIABLES FOR INSTANT SPEECH TO TEXT ──────────
    let mediaRecorder = null;
    let audioChunks = [];
    let isRecordingAudio = false;

    <?php if (!empty($cart)): ?>
        (function () {
          const sessCart = <?= json_encode(array_values($cart)) ?>;
          sessCart.forEach(si => {
            cart.push({ id: si.id, name: si.name, e: si.e, price: si.price, qty: si.qty });
          });
          syncCart();
        })();
    <?php endif; ?>

    /* ── Cart processing functions ─────────────────────────────── */
    function cartSub() { return cart.reduce((s, i) => s + i.price * i.qty, 0); }
    function delivFee(sub) { return sub >= 1500 ? 0 : 80; }
    function discount(sub) { return promoApplied ? Math.round(sub * promoApplied / 100) : 0; }
    function cartTotal() { const s = cartSub(); return s + delivFee(s) - discount(s); }

    function addToCart(id, qty = 1) {
      const item = itemById(id); if (!item) return;
      const ex = cart.find(c => c.id === id);
      if (ex) ex.qty += qty; else cart.push({ ...item, qty });
      syncCart();
      fetch('cart_action.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'add', id: item.id, name: item.name, emoji: item.e, price: item.price })
      });
      toast(`${item.e} ${item.name} — cart mein add!`);
    }
    function changeQty(id, delta) {
      const ex = cart.find(c => c.id === id); if (!ex) return;
      ex.qty = Math.max(0, ex.qty + delta);
      if (ex.qty === 0) cart = cart.filter(c => c.id !== id);
      fetch('cart_action.php', { method: 'POST', body: new URLSearchParams({ action: 'update', id, delta }) });
      syncCart();
    }
    function removeFromCart(id) {
      cart = cart.filter(c => c.id !== id);
      fetch('cart_action.php', { method: 'POST', body: new URLSearchParams({ action: 'update', id, delta: -99 }) });
      syncCart();
    }
    function syncCart() {
      const count = cart.reduce((s, i) => s + i.qty, 0);
      const total = count ? cartTotal() : 0;
      const sbCart = document.getElementById('sbCart');
      if (sbCart) sbCart.textContent = count;
      document.getElementById('fcCt').textContent = count;
      document.getElementById('fcTotal').textContent = 'Rs.' + total.toLocaleString();
      document.getElementById('floatCart').classList.toggle('show', count > 0);
    }

    /* ── System dynamic build modules ──────────────────────────── */
    function buildPrompt() {
      const menuStr = Object.entries(MENU).map(([cat, items]) =>
        items.length ? cat.toUpperCase() + ': ' + items.map(i => `${i.name} Rs.${i.price} (id:${i.id})`).join(', ') : ''
      ).filter(Boolean).join('\n');

      const cartStr = cart.length ? cart.map(i => `${i.name} x${i.qty} = Rs.${i.price * i.qty}`).join(' | ') : 'Empty';
      const sub = cartSub(), fee = delivFee(sub), disc = discount(sub), total = sub + fee - disc;
      const orderHist = USER_ORDERS.length ? USER_ORDERS.slice(0, 3).map(o => `#${o.id} (${o.status})`).join(', ') : 'None';

      return `You are CheeseBot, a voice and chat ordering assistant for Cheesy Burgers in Rawalpindi, Pakistan.

STRICT RULES:
- Keep answers SHORT: 1 to 2 sentences only
- NO emojis, NO bullet points, NO asterisks, NO markdown
- Use ONLY the prices from the menu below, never make up prices
- Speak naturally like a real person on a phone call
- Say prices as "950 rupees" not "Rs.950"

REAL MENU WITH EXACT PRICES (use ONLY these prices):
${menuStr}

CURRENT CART: ${cartStr}
CART TOTAL: ${total} rupees
PREVIOUS ORDERS: ${orderHist}

ACTIONS (silently append at end of your reply, customer will not see these):
- Customer wants to see burgers: [SHOW_MENU:burger]
- Customer wants pizza: [SHOW_MENU:pizza]
- Customer wants fries: [SHOW_MENU:fries]
- Customer wants drinks: [SHOW_MENU:drink]
- Customer wants wraps: [SHOW_MENU:wrap]
- Customer wants dessert: [SHOW_MENU:dessert]
- Customer orders item by name or id: [ADD_CART:id:qty]
- Customer wants deals or discounts: [SHOW_DEALS]
- Customer says "show cart", "my cart", "what did I order": [SHOW_CART]
- Customer wants to checkout or place order: [SHOW_ORDER_FORM]

EXAMPLE good reply: "The Classic Smash Burger is 750 rupees. Want me to add it to your cart?"`;
    }

    async function callClaude(msg) {
      convHistory.push({ role: 'user', content: msg });
      if (convHistory.length > 5) convHistory = convHistory.slice(-5);
      const messages = [{ role: 'system', content: buildPrompt() }, ...convHistory];
      
      console.log("Sending payload to proxy:", JSON.stringify({ messages }));

      const res = await fetch('api_proxy.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ messages })
      });
      
      if (!res.ok) { 
        console.error("Server responded with error status:", res.status);
        convHistory.pop(); 
        throw new Error('API Server Error ' + res.status); 
      }
      
      const rawText = await res.text();
      console.log("Raw Server Response Text:", rawText);

      // ✅ Robust JSON Parser Fallback Mechanism
      try {
        const data = JSON.parse(rawText);
        const reply = data.choices?.[0]?.message?.content || data.reply || data.response || rawText;
        convHistory.push({ role: 'assistant', content: reply });
        return reply;
      } catch (jsonErr) {
        console.warn("Response is not JSON format. Treating raw response as chat text.");
        if (rawText && rawText.trim().length > 0) {
          convHistory.push({ role: 'assistant', content: rawText });
          return rawText;
        }
        throw new Error('Empty response payload');
      }
    }

    function parseActions(raw) {
      let text = raw; const actions = [];
      const re = /\[([A-Z_]+)(?::([^\]]*))?\]/g; let m;
      while ((m = re.exec(raw)) !== null) {
        actions.push({ type: m[1], args: (m[2] || '').split(':').filter(Boolean) });
        text = text.replace(m[0], '');
      }
      return { text: text.trim(), actions };
    }

    function runActions(actions) {
      actions.forEach(a => {
        const { type: T, args } = a;
        if (T === 'SHOW_MENU') setTimeout(() => attachMenu(args[0] || 'all'), 200);
        else if (T === 'ADD_CART') addToCart(parseInt(args[0]), parseInt(args[1]) || 1);
        else if (T === 'SHOW_CART') setTimeout(attachCartWidget, 200);
        else if (T === 'SHOW_DEALS') setTimeout(attachDealsWidget, 200);
        else if (T === 'SHOW_ORDER_FORM') setTimeout(attachOrderForm, 200);
      });
    }

    /* ── TEXT INPUT EXECUTION PIPELINE ──────────────────────────── */
    async function sendMsg(forced) {
      const inp = document.getElementById('msgInput');
      const msg = (forced || inp.value).trim();
      if (!msg || isTyping) return;
      if (!forced) { inp.value = ''; }
      
      appendUser(msg); 
      showTyping();
      isTyping = true; 
      document.getElementById('sendBtn').disabled = true;
      
      try {
        const raw = await callClaude(msg);
        hideTyping();
        const { text, actions } = parseActions(raw);
        appendBot(text);
        runActions(actions);
        if (ttsEnabled) speakText(text);
      } catch (err) {
        console.error("Detailed Pipeline Failure Catch:", err);
        hideTyping();
        appendBot(`Oops! Server connection issue or empty response received. Please check backend proxy config. 🙏`);
      }
      isTyping = false;
      document.getElementById('sendBtn').disabled = false;
    }
    function sq(msg) { document.getElementById('msgInput').value = msg; sendMsg(); }

    /* ── PUSH TO TALK AUDIO RECORDER SYSTEM PIPELINE ────────────── */
    async function toggleVoiceRecording() {
      const micBtn = document.getElementById('micBtn');
      const indicator = document.getElementById('audioStatusIndicator');
      
      if (!isRecordingAudio) {
        audioChunks = [];
        try {
          const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
          mediaRecorder = new MediaRecorder(stream);
          mediaRecorder.ondataavailable = (e) => { if (e.data.size > 0) audioChunks.push(e.data); };
          
          mediaRecorder.onstop = async () => {
            indicator.textContent = "⏳ Transcribing via Whisper...";
            const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
            await dispatchAudioToWhisper(audioBlob);
          };
          
          mediaRecorder.start();
          isRecordingAudio = true;
          micBtn.style.background = "#ff4444";
          micBtn.style.borderColor = "#ff4444";
          micBtn.style.color = "#fff";
          micBtn.textContent = "🛑";
          indicator.style.display = "block";
          indicator.textContent = "🔴 Recording... Tap mic again to stop and auto-send!";
        } catch (err) {
          alert("Please allow Microphone Access!");
        }
      } else {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
          mediaRecorder.stop();
          mediaRecorder.stream.getTracks().forEach(track => track.stop());
        }
        isRecordingAudio = false;
        micBtn.style.background = "rgba(255, 184, 0, 0.08)";
        micBtn.style.borderColor = "rgba(255, 184, 0, 0.12)";
        micBtn.style.color = "#ffb800";
        micBtn.textContent = "🎤";
      }
    }

    async function dispatchAudioToWhisper(blob) {
      const fd = new FormData();
      fd.append('audio', blob);
      try {
        const res = await fetch('voice_stt.php', { method: 'POST', body: fd });
        const data = await res.json();
        document.getElementById('audioStatusIndicator').style.display = "none";
        
        if (data.text && data.text.trim()) {
          document.getElementById('msgInput').value = data.text.trim();
          sendMsg();
        }
      } catch (err) {
        document.getElementById('audioStatusIndicator').style.display = "none";
        toast('❌ Whisper transcription failed');
      }
    }

    /* ── Document Interface Elements Renderer modules ────────────── */
    function appendUser(text) {
      const msgs = document.getElementById('messages'), now = nowTime();
      const d = document.createElement('div'); d.className = 'msg-row user';
      d.innerHTML = `<div><div class="bubble usr">${esc(text)}</div><div class="msg-meta">${now}</div></div><div class="msg-av usr">${USER_INITIALS}</div>`;
      msgs.appendChild(d); scrollEnd();
    }
    function appendBot(text) {
      const msgs = document.getElementById('messages'), now = nowTime();
      const d = document.createElement('div'); d.className = 'msg-row';
      d.innerHTML = `<div class="msg-av bot">🧀</div><div><div class="bubble bot">${fmt(text)}</div><div class="msg-meta">CheeseBot • ${now}</div></div>`;
      msgs.appendChild(d); scrollEnd(); return d;
    }
    function showTyping() {
      const msgs = document.getElementById('messages');
      if(document.getElementById('typingRow')) return;
      const d = document.createElement('div'); d.className = 'msg-row'; d.id = 'typingRow';
      d.innerHTML = `<div class="msg-av bot">🧀</div><div class="bubble bot"><div class="typing-wrap"><div class="td"></div><div class="td"></div><div class="td"></div></div></div>`;
      msgs.appendChild(d); scrollEnd();
    }
    function hideTyping() { const e = document.getElementById('typingRow'); if (e) e.remove(); }
    function scrollEnd() { const m = document.getElementById('messages'); m.scrollTop = m.scrollHeight; }
    function lastBotBubble() {
      const all = document.getElementById('messages').querySelectorAll('.msg-row:not(.user)');
      return all.length ? all[all.length - 1].querySelector('.bubble') : null;
    }
    function attachToBot(el) { const b = lastBotBubble(); if (b) b.appendChild(el); scrollEnd(); }
    function nowTime() { return new Date().toLocaleTimeString('en-PK', { hour: '2-digit', minute: '2-digit' }); }
    function esc(s) { return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    function fmt(t) { return esc(t).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>'); }

    function showQR(arr) {
      const container = document.getElementById('qrContainer'); container.innerHTML = '';
      arr.forEach(txt => {
        const d = document.createElement('div'); d.className = 'qr-chip'; d.textContent = txt;
        d.onclick = () => { container.innerHTML = ''; sq(txt); };
        container.appendChild(d);
      });
    }

    function attachMenu(cat) {
      let items = cat === 'all' ? allItems() : (MENU[cat] || allItems());
      const w = document.createElement('div'); w.className = 'mgrid'; w.style.marginTop = '.45rem';
      items.forEach(it => {
        const d = document.createElement('div'); d.className = 'mi';
        d.innerHTML = `<span class="mi-em">${it.e}</span><div class="mi-name">${it.name}</div><div class="mi-price">Rs.${it.price}</div><button class="mi-add" onclick="addToCart(${it.id},1)">+ Add</button>`;
        w.appendChild(d);
      });
      attachToBot(w);
    }
    function attachDealsWidget() {
      const w = document.createElement('div'); w.className = 'w-card';
      w.innerHTML = `<div class="w-title"><i class="fa-solid fa-fire"></i> Aaj ke Hot Deals</div>`;
      DEALS.forEach(d => {
        const row = document.createElement('div'); row.className = 'deal-item';
        row.innerHTML = `<div class="di-em">${d.e}</div><div class="di-name">${d.name} <span style="font-size:10px; background:var(--accent); color:#000; padding:1px 4px; border-radius:4px; margin-left:6px;">${d.badge}</span></div><div class="di-price">Rs.${d.price}</div>`;
        row.onclick = () => { addToCart(d.id, 1); };
        w.appendChild(row);
      });
      attachToBot(w);
    }
    function attachCartWidget() { const w = buildCartEl(); attachToBot(w); }
    function buildCartEl() {
      const w = document.createElement('div'); w.className = 'w-card';
      const sub = cartSub(), fee = delivFee(sub), disc = discount(sub), total = sub + fee - disc;
      let html = `<div class="w-title"><i class="fa-solid fa-cart-shopping"></i> Your Selected Items</div>`;
      cart.forEach(it => {
        html += `<div class="ci-row" style="align-items:center;">
          <span style="flex:1;">${it.e} <strong>${it.name}</strong></span>
          <div style="display:flex; align-items:center; gap:8px; margin-right:16px;">
            <button onclick="changeQty(${it.id},-1)" style="width:20px;height:20px;border-radius:4px;border:1px solid var(--border);background:transparent;color:var(--text);font-weight:bold;cursor:pointer;">-</button>
            <span style="font-size:13px;font-weight:700;min-width:14px;text-align:center;">${it.qty}</span>
            <button onclick="changeQty(${it.id},1)" style="width:20px;height:20px;border-radius:4px;border:1px solid var(--border);background:transparent;color:var(--text);font-weight:bold;cursor:pointer;">+</button>
          </div>
          <span style="font-weight:600;min-width:65px;text-align:right;">Rs.${(it.price * it.qty).toLocaleString()}</span>
        </div>`;
      });
      html += `
        <div style="margin-top:12px; font-size:12px; color:var(--text-muted); display:flex; flex-direction:column; gap:4px;">
          <div style="display:flex; justify-content:between;"><span>Subtotal:</span><span style="margin-left:auto;">Rs.${sub.toLocaleString()}</span></div>
          <div style="display:flex; justify-content:between;"><span>Delivery Fee:</span><span style="margin-left:auto;">${fee===0?'FREE':'Rs.'+fee}</span></div>
          ${disc>0?`<div style="display:flex; justify-content:between; color:#00e676;"><span>Discount:</span><span style="margin-left:auto;">-Rs.${disc.toLocaleString()}</span></div>`:''}
        </div>
        <div class="w-total"><span>Total Payable:</span><span>Rs.${total.toLocaleString()}</span></div>`;
      return w;
    }

    function attachOrderForm() {
      if(!LOGGED_IN) {
        appendBot("🔒 You must be logged in to complete checkout orders! [SHOW_AUTH]");
        showQR(['🔐 Login to Account', '🏠 Back to Home']); return;
      }
      if(!cart.length) {
        appendBot("🛒 Your basket is currently empty. Please select food items first!"); return;
      }
      const sub = cartSub(), fee = delivFee(sub), disc = discount(sub), total = sub + fee - disc;
      const w = document.createElement('div'); w.className = 'w-card';
      w.innerHTML = `
        <div class="w-title"><i class="fa-solid fa-receipt"></i> Complete Your Delivery Details</div>
        <form id="orderLiveForm" onsubmit="submitLiveOrder(event)">
          <div class="wf-group"><label>Delivery Address</label><input type="text" id="oAddr" class="wf-input" placeholder="House#, Street#, Sector / Area Rawalpindi" required /></div>
          <div class="wf-group"><label>Contact Cell Number</label><input type="tel" id="oPhone" class="wf-input" placeholder="03xx-xxxxxxx" required /></div>
          <div class="wf-group">
            <label>Payment Method</label>
            <select id="oPay" class="wf-input" onchange="selectedPay=this.value" style="padding-right:24px;">
              <option value="cod">💵 Cash on Delivery (COD)</option>
              <option value="easypaisa">📱 Easypaisa / JazzCash Mobile Transfer</option>
            </select>
          </div>
          <div style="margin:12px 0; font-size:12.5px; color:var(--text-muted); border-top:1px solid var(--border); padding-top:8px;">Final value to collect: <strong style="color:var(--accent); font-size:13.5px;">Rs.${total.toLocaleString()}</strong></div>
          <button type="submit" class="wf-submit">Confirm & Place Order 🏁</button>
        </form>`;
      attachOrderFormElement = w; // tracking
      attachToBot(w);
    }

    async function submitLiveOrder(e) {
      e.preventDefault();
      const addr = document.getElementById('oAddr').value.trim();
      const phone = document.getElementById('oPhone').value.trim();
      const fbtn = e.target.querySelector('.wf-submit');
      fbtn.disabled = true; fbtn.textContent = "Processing order details...";
      
      try {
        const res = await fetch('place_order_action.php', {
          method: 'POST',
          body: new URLSearchParams({ address: addr, phone: phone, payment_method: selectedPay })
        });
        const data = await res.json();
        if(data.success) {
          document.getElementById('orderLiveForm').innerHTML = `<div style="text-align:center;color:#00e676;padding:12px 0;font-weight:600;"><i class="fa-solid fa-circle-check"></i> Order Placed Successfully!<br><span style="font-size:12px;color:#fff;">Order Reference ID: #${data.order_id}</span></div>`;
          cart = []; syncCart();
          appendBot(`🎉 Fantastic! Your order **#${data.order_id}** has been generated successfully and sent directly to the kitchen line! 🧑‍🍳\n\nOur delivery rider will reach your address at **"${addr}"** within 35-45 minutes. Cash collection point value is **Rs.${data.final_total}**. Thank you for choosing Cheesy Burgers! 🧀`);
          showQR(['🍔 View Menu again', '📍 Track Order Status']);
        } else { alert(data.error || "Order generation failed."); fbtn.disabled = false; fbtn.textContent = "Confirm & Place Order 🏁"; }
      } catch(err) { alert("Network transmission issue. Please try again."); fbtn.disabled = false; fbtn.textContent = "Confirm & Place Order 🏁"; }
    }

    function showWelcome() {
      const greet = new Date().getHours() < 12 ? 'Good Morning' : 'Good Afternoon';
      const u = LOGGED_IN ? ` ${USER_NAME}` : '';
      appendBot(`${greet}${u}! 🧀 I'm **CheeseBot** — your AI ordering assistant at Cheesy Burgers! \n\nToday's **Special:** Cheese Overload Burger **20% OFF** — only Rs.950! 🔥\n\nHere's what I can help you with:\n🍔 Browse menu  •  🛒 Place an order  •  📍 Track your order\n🎁 Promo codes  •  📞 Live voice call supported!\n\nWhat would you like today?`);
      showQR(['🍔 Show Burgers', '🍕 Show Pizzas', '🔥 Hot Deals', '🛒 Place an Order']);
    }

    /* ── FULL VOICE PHONE INTERFACE FLOATING SYSTEM MODULES ─────── */
    let voiceActive = false;
    let voiceRecognition = null;
    let voiceAnimI = null;

    function activateVoiceCall() {
      if(!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) { alert("Speech recognition features are not supported in this browser version. Use text inputs."); return; }
      voiceActive = true; document.getElementById('voiceOverlay').classList.add('active');
      document.getElementById('voTranscript').innerHTML = '';
      setVoiceStatus('ready', 'Connecting service...');
      
      setTimeout(() => {
        setVoiceStatus('ready', 'Connected to CheeseBot Engine');
        startListeningCycle();
      }, 800);
    }
    function deactivateVoiceCall() {
      voiceActive = false; stopVoiceWave();
      if(voiceRecognition) { voiceRecognition.abort(); voiceRecognition = null; }
      document.getElementById('voiceOverlay').classList.remove('active');
      if (typeof window.speechSynthesis !== 'undefined') window.speechSynthesis.cancel();
    }

    function startListeningCycle() {
      if(!voiceActive) return;
      if (typeof window.speechSynthesis !== 'undefined') window.speechSynthesis.cancel();
      
      const Speech = window.SpeechRecognition || window.webkitSpeechRecognition;
      voiceRecognition = new Speech();
      voiceRecognition.continuous = false; voiceRecognition.interimResults = false; voiceRecognition.language = 'en-US';
      
      voiceRecognition.onstart = () => { setVoiceStatus('listening', 'Listening closely...'); runVoiceWave(true); };
      voiceRecognition.onerror = (e) => { if(voiceActive) { console.log("SR Error:", e.error); restartListeningShort(); } };
      voiceRecognition.onend = () => { if(voiceActive && document.getElementById('voStatus').className.includes('listening')) { restartListeningShort(); } };
      
      voiceRecognition.onresult = async (e) => {
        const txt = e.results[0][0].transcript; if(!txt.trim()) return;
        stopVoiceWave(); voiceRecognition.abort();
        addVoBubble('u', txt);
        setVoiceStatus('ready', 'Processing speech text...');
        
        try {
          const raw = await callClaude(txt);
          const { text, actions } = parseActions(raw);
          addVoBubble('b', text);
          runActions(actions);
          speakVoiceResponse(text);
        } catch(err) { addVoBubble('b', "Service interrupted. Please say again."); speakVoiceResponse("Service interrupted."); }
      };
      try { voiceRecognition.start(); } catch(err){}
    }
    function restartListeningShort() { if(voiceActive) { stopVoiceWave(); setTimeout(startListeningCycle, 400); } }

    function speakVoiceResponse(txt) {
      if(!voiceActive) return;
      setVoiceStatus('speaking', 'CheeseBot speaking...'); runVoiceWave(false);
      const clean = txt
        .replace(/\[[A-Z_:0-9]+\]/g, '')
        .replace(/[\u{1F300}-\u{1FFFF}]/gu, '')
        .replace(/[\u{2600}-\u{27BF}]/gu, '')
        .replace(/[\*#_`~]/g, '')
        .replace(/Rs\.\s*/g, 'Rs ')
        .replace(/\s+/g, ' ')
        .trim()
        .substring(0, 300);
      
      if ('speechSynthesis' in window) {
        const u = new SpeechSynthesisUtterance(clean); u.lang = 'en-US';
        u.onend = () => { if(voiceActive) restartListeningShort(); };
        u.onerror = () => { if(voiceActive) restartListeningShort(); };
        window.speechSynthesis.speak(u);
      } else {
        const url = 'https://translate.google.com/translate_tts?ie=UTF-8&tl=en&client=tw-idx&q=' + encodeURIComponent(clean);
        const a = new Audio(url);
        a.onended = () => { if(voiceActive) restartListeningShort(); };
        a.onerror = () => { if(voiceActive) restartListeningShort(); };
        a.play().catch(() => { restartListeningShort(); });
      }
    }

    function runVoiceWave(isGreen) {
      if(voiceAnimI) cancelAnimationFrame(voiceAnimI);
      const bars = document.querySelectorAll('#voWave .vo-bar');
      let t = 0;
      function anim() {
        t += 0.12;
        bars.forEach((b,i)=>{
          const v = (Math.sin(t*3 + i*0.7)+1)/2;
          b.style.height = Math.max(3, v*32)+'px';
          b.className = 'vo-bar '+(isGreen?'green':'active');
        });
        voiceAnimI = requestAnimationFrame(anim);
      }
      anim();
      document.getElementById('voAvatarWrap').className = 'vo-avatar-wrap '+(isGreen?'listening':'speaking');
    }
    function stopVoiceWave() {
      if(voiceAnimI) { cancelAnimationFrame(voiceAnimI); voiceAnimI=null; }
      document.querySelectorAll('#voWave .vo-bar').forEach(b=>{ b.style.height='4px'; b.className='vo-bar'; });
      document.getElementById('voAvatarWrap').className = 'vo-avatar-wrap';
    }
    function setVoiceStatus(state, msg) {
      const el = document.getElementById('voStatus'); el.textContent = msg;
      el.className = 'vo-status '+(state==='ready'?'':state);
      document.getElementById('voHint').textContent = state==='ready'?'Tap mic to speak':'';
    }
    function addVoBubble(who, text) {
      const d = document.getElementById('voTranscript');
      const el = document.createElement('div'); el.className = 'vo-bubble '+(who==='u'?'u':'');
      el.innerHTML = who==='u' ? `<strong>You:</strong> ${esc(text)}` : `<strong>Bot:</strong> ${fmt(text)}`;
      d.appendChild(el); d.scrollTop = d.scrollHeight;
    }

    /* ── Utilities systems layout matrix ───────────────────────── */
    function toast(m) { const e = document.getElementById('toast'); if(e) { e.textContent = m; e.classList.add('show'); setTimeout(()=>e.classList.remove('show'),3000); } }
    function toggleTTS() {
      ttsEnabled = !ttsEnabled;
      const t1 = document.getElementById('ttsTgl'); const t2 = document.getElementById('ttsBtn');
      t1.classList.toggle('on', ttsEnabled); t2.classList.toggle('on', ttsEnabled);
      t1.innerHTML = ttsEnabled ? '<i class="fa-solid fa-volume-high"></i> Voice Engine' : '<i class="fa-solid fa-volume-xmark"></i> Muted';
      t2.innerHTML = ttsEnabled ? '<i class="fa-solid fa-volume-high"></i> Voice On' : '<i class="fa-solid fa-volume-xmark"></i> Voice Off';
    }
    function speakText(t) {
      if(!ttsEnabled || voiceActive) return;
      const clean = t.replace(/\[[A-Z_:]+\]/g, '').replace(/[\*#_]/g, '');
      const u = 'https://translate.google.com/translate_tts?ie=UTF-8&tl=en&client=tw-idx&q=' + encodeURIComponent(clean);
      const a = new Audio(u); a.play().catch(()=>{});
    }
    function clearChat() { document.getElementById('messages').innerHTML = ''; convHistory = []; showWelcome(); }
    function toggleDark() {
      const th = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', th);
      localStorage.setItem('cb-theme', th);
      document.getElementById('dmIco').className = th==='dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
      document.getElementById('dmLbl').textContent = th==='dark' ? 'Dark Theme' : 'Light Theme';
    }

    /* ── INIT DOM BOOT ─────────────────────────────────────────── */
    window.addEventListener('load', () => {
      const t = localStorage.getItem('cb-theme') || 'dark';
      document.documentElement.setAttribute('data-theme', t);
      document.getElementById('dmIco').className = t==='dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
      document.getElementById('dmLbl').textContent = t==='dark' ? 'Dark Theme' : 'Light Theme';

      document.getElementById('ttsTgl').innerHTML = '<i class="fa-solid fa-volume-high"></i> Voice Engine';
      document.getElementById('ttsTgl').className = 'sb-btn tts-toggle on';
      document.getElementById('ttsBtn').innerHTML = '<i class="fa-solid fa-volume-high"></i> Voice On';
      document.getElementById('ttsBtn').className = 'ib on';

      showWelcome();
      syncCart();
    });
    document.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); document.getElementById('msgInput').focus(); } });
</script>
</body>
</html>
