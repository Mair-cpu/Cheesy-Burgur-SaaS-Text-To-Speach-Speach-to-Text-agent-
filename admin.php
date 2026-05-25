<?php
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

// ── ADMIN ONLY ───────────────────────────────────────────────
if (empty($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php'); exit;
}

function statusIcon($s){
    return match($s){'pending'=>'🕐','cooking'=>'🍳','out'=>'🛵','delivered'=>'✅','cancelled'=>'❌',default=>'❓'};
}

// Load data
$orders = [];
$res = $conn->query("SELECT o.*,r.name AS rider_name FROM orders o LEFT JOIN riders r ON o.rider=r.id ORDER BY o.time DESC");
while($r=$res->fetch_assoc()) $orders[]=$r;

$menu = [];
$res = $conn->query("SELECT * FROM menu ORDER BY cat,name");
while($r=$res->fetch_assoc()) $menu[]=$r;

$riders = [];
$res = $conn->query("SELECT * FROM riders");
while($r=$res->fetch_assoc()) $riders[]=$r;

$counts = ['all'=>count($orders),'pending'=>0,'cooking'=>0,'out'=>0,'delivered'=>0,'cancelled'=>0];
foreach($orders as $o) if(isset($counts[$o['status']])) $counts[$o['status']]++;

$adminName = $_SESSION['user_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Panel — CheesyBurgers</title>
<link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --cheese:#F4A800;--melt:#FF6B00;--sidebar-w:240px;--nav-h:64px;
  --bg:#0A0500;--surface:#150900;--surface2:#1C0E00;--surface3:#241100;
  --text:#FFE8B0;--text2:#FFD580;--muted:#A07840;
  --border:rgba(244,168,0,.1);--shadow:0 4px 20px rgba(0,0,0,.35);
  --green:#22c55e;--red:#ef4444;--blue:#3b82f6;
}
[data-theme=light]{
  --bg:#FFFDF5;--surface:#fff;--surface2:#FFF5D6;--surface3:#FFFBF0;
  --text:#3D1F00;--text2:#5A3A10;--muted:#8A6040;--border:rgba(244,168,0,.18);
}
html{scroll-behavior:smooth;}
body{font-family:'Nunito',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;overflow-x:hidden;}

/* ── SIDEBAR ── */
.sidebar{
  width:var(--sidebar-w);background:linear-gradient(180deg,#1A0800,#0A0400);
  position:fixed;top:0;left:0;height:100vh;
  display:flex;flex-direction:column;z-index:200;
  border-right:1px solid rgba(244,168,0,.08);
  transition:transform .3s;
}
@media(max-width:860px){.sidebar{transform:translateX(-100%);}.sidebar.open{transform:none;}}
.sb-brand{padding:1.2rem 1.3rem;border-bottom:1px solid rgba(244,168,0,.08);}
.sb-logo{font-family:'Fredoka One',cursive;font-size:1.2rem;color:var(--cheese);}
.sb-sub{font-size:.68rem;color:#A07840;margin-top:.1rem;}
.sb-user{display:flex;align-items:center;gap:.7rem;padding:.9rem 1.3rem;border-bottom:1px solid rgba(244,168,0,.08);}
.sb-avatar{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--cheese),var(--melt));display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:800;color:#fff;}
.sb-uname{font-size:.83rem;font-weight:800;color:#FFE8B0;}
.sb-utag{font-size:.67rem;color:#A07840;}
.sb-scroll{flex:1;overflow-y:auto;padding:.6rem 0;}
.sb-scroll::-webkit-scrollbar{width:3px;}
.sb-scroll::-webkit-scrollbar-thumb{background:rgba(244,168,0,.15);}
.sb-label{font-size:.62rem;font-weight:800;color:#A07840;letter-spacing:1.5px;text-transform:uppercase;padding:.5rem 1.3rem .25rem;}
.sb-link{display:flex;align-items:center;gap:.65rem;padding:.6rem 1.3rem;cursor:pointer;transition:all .2s;position:relative;border-left:3px solid transparent;}
.sb-link:hover{background:rgba(244,168,0,.06);}
.sb-link.active{background:rgba(244,168,0,.1);border-left-color:var(--cheese);}
.sb-link .ico{font-size:1rem;width:20px;text-align:center;}
.sb-link .lbl{font-size:.83rem;font-weight:700;color:#C8A060;}
.sb-link:hover .lbl,.sb-link.active .lbl{color:var(--cheese);}
.sb-cnt{margin-left:auto;background:var(--melt);color:#fff;border-radius:10px;padding:.05rem .4rem;font-size:.67rem;font-weight:800;}
.sb-bottom{padding:.9rem 1.1rem;border-top:1px solid rgba(244,168,0,.08);display:flex;flex-direction:column;gap:.5rem;}
.sb-logout{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);border-radius:8px;padding:.55rem .9rem;color:#f87171;cursor:pointer;font-size:.82rem;font-family:inherit;display:flex;align-items:center;gap:.5rem;transition:all .2s;}
.sb-logout:hover{background:rgba(239,68,68,.2);}
.dm-row{display:flex;align-items:center;gap:.5rem;background:rgba(244,168,0,.06);border:1px solid rgba(244,168,0,.1);border-radius:8px;padding:.55rem .9rem;cursor:pointer;font-size:.82rem;color:#C8A060;transition:all .2s;}
.dm-row:hover{background:rgba(244,168,0,.1);}

/* ── MAIN ── */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;}
@media(max-width:860px){.main{margin-left:0;}}
.topbar{
  display:flex;align-items:center;justify-content:space-between;
  padding:0 1.5rem;height:var(--nav-h);
  background:var(--surface);border-bottom:1px solid var(--border);
  position:sticky;top:0;z-index:100;
}
.ham{display:none;flex-direction:column;gap:4px;cursor:pointer;padding:.4rem;}
@media(max-width:860px){.ham{display:flex;}}
.ham span{width:20px;height:2px;background:var(--cheese);border-radius:2px;}
.tb-title{font-weight:800;font-size:1rem;}
.tb-sub{font-size:.72rem;color:var(--muted);margin-top:.1rem;}
.live-chip{display:flex;align-items:center;gap:.4rem;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);border-radius:20px;padding:.3rem .8rem;font-size:.75rem;font-weight:700;color:#16a34a;}
.live-dot{width:7px;height:7px;border-radius:50%;background:#22c55e;animation:blink 1s infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:.3;}}
.tb-btn{padding:.45rem 1rem;background:var(--cheese);color:#fff;border:none;border-radius:8px;cursor:pointer;font-family:inherit;font-weight:700;font-size:.82rem;transition:all .2s;}
.tb-btn:hover{background:var(--melt);}

/* ── PAGE ── */
.page{padding:1.5rem;flex:1;}

/* ── STATS ── */
.stats-row{display:grid;grid-template-columns:repeat(5,1fr);gap:.8rem;margin-bottom:1.5rem;}
@media(max-width:1100px){.stats-row{grid-template-columns:repeat(3,1fr);}}
@media(max-width:600px){.stats-row{grid-template-columns:repeat(2,1fr);}}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:1rem;display:flex;align-items:center;gap:.75rem;}
.sc-ico{font-size:1.7rem;}
.sc-val{font-size:1.6rem;font-weight:800;color:var(--cheese);}
.sc-lbl{font-size:.7rem;color:var(--muted);margin-top:.1rem;}

/* ── CARDS ── */
.card{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:1.2rem;}
.card-hdr{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.3rem;border-bottom:1px solid var(--border);}
.card-hdr h3{font-size:.95rem;font-weight:800;}
.card-hdr p{font-size:.75rem;color:var(--muted);margin-top:.1rem;}

/* ── FILTER TABS ── */
.ftabs{display:flex;gap:.4rem;flex-wrap:wrap;}
.ftab{padding:.3rem .75rem;border-radius:20px;font-size:.75rem;font-weight:700;cursor:pointer;background:var(--surface2);color:var(--muted);border:1px solid var(--border);transition:all .2s;}
.ftab:hover{border-color:var(--cheese);color:var(--cheese);}
.ftab.active{background:var(--cheese);color:#fff;border-color:var(--cheese);}
.f-cnt{background:rgba(0,0,0,.2);border-radius:8px;padding:0 .35rem;margin-left:.25rem;}

/* ── ORDER CARDS ── */
.orders-list{padding:.4rem;}
.order-card{border:1px solid var(--border);border-radius:12px;margin:.5rem;background:var(--surface2);transition:box-shadow .2s;}
.order-card:hover{box-shadow:0 4px 16px rgba(244,168,0,.08);}
.oc-hdr{display:flex;align-items:center;gap:.6rem;padding:.7rem 1rem;flex-wrap:wrap;}
.oc-id{font-weight:800;color:var(--cheese);font-size:.9rem;min-width:90px;}
.oc-name{flex:1;font-weight:700;font-size:.85rem;min-width:80px;}
.oc-time{color:var(--muted);font-size:.75rem;}
.oc-total{font-weight:800;color:var(--text2);font-size:.85rem;}
.oc-expand{background:none;border:1px solid var(--border);border-radius:6px;padding:.15rem .45rem;cursor:pointer;color:var(--muted);font-size:.8rem;transition:all .2s;}
.oc-expand:hover{background:var(--cheese);color:#fff;border-color:var(--cheese);}

/* ── STATUS BADGE (inline in header) ── */
.status-badge{padding:.2rem .6rem;border-radius:12px;font-size:.72rem;font-weight:700;white-space:nowrap;}
.sb-pending{background:rgba(244,168,0,.15);color:#C97D00;}
.sb-cooking{background:rgba(255,107,0,.15);color:#C95200;}
.sb-out{background:rgba(59,130,246,.15);color:#2563eb;}
.sb-delivered{background:rgba(34,197,94,.15);color:#16a34a;}
.sb-cancelled{background:rgba(239,68,68,.15);color:#dc2626;}

/* ── ORDER BODY ── */
.oc-body{display:none;padding:.8rem 1rem 1rem;border-top:1px solid var(--border);}
.oc-body.open{display:block;}
.oc-items{font-size:.82rem;color:var(--text2);margin-bottom:.8rem;line-height:1.9;}
.oc-note{font-size:.78rem;color:var(--muted);font-style:italic;margin-bottom:.8rem;}

/* ── 4 STATUS BUTTONS ── */
.status-btns{display:flex;gap:.4rem;flex-wrap:wrap;margin-bottom:.8rem;padding:.6rem;background:var(--surface3);border-radius:10px;}
.status-btns label{font-size:.7rem;font-weight:700;color:var(--muted);display:block;width:100%;margin-bottom:.3rem;}
.sbtn{padding:.35rem .8rem;border-radius:8px;border:1.5px solid var(--border);background:var(--surface);color:var(--muted);font-size:.75rem;font-weight:700;cursor:pointer;font-family:inherit;transition:all .2s;display:flex;align-items:center;gap:.3rem;}
.sbtn:hover{border-color:var(--cheese);color:var(--cheese);}
.sbtn.active-pending{background:rgba(244,168,0,.15);color:#C97D00;border-color:#C97D00;}
.sbtn.active-cooking{background:rgba(255,107,0,.15);color:#C95200;border-color:#C95200;}
.sbtn.active-out{background:rgba(59,130,246,.15);color:#2563eb;border-color:#3b82f6;}
.sbtn.active-delivered{background:rgba(34,197,94,.15);color:#16a34a;border-color:#22c55e;}
.sbtn.active-cancelled{background:rgba(239,68,68,.15);color:#dc2626;border-color:#ef4444;}

/* ── ACTION BUTTONS ── */
.oc-actions{display:flex;gap:.5rem;flex-wrap:wrap;}
.act-btn{padding:.4rem .85rem;border:1px solid var(--border);border-radius:8px;font-size:.78rem;cursor:pointer;background:var(--surface);color:var(--text);font-family:inherit;transition:all .2s;}
.act-btn:hover{border-color:var(--cheese);}
.btn-wa{background:#25D366;color:#fff;border-color:#25D366;}
.btn-wa:hover{background:#128C7E;border-color:#128C7E;}
.btn-map{background:#4285F4;color:#fff;border-color:#4285F4;}
.btn-map:hover{background:#1a73e8;}

/* ── MENU MANAGER ── */
.menu-item-row{display:flex;align-items:center;gap:.75rem;padding:.8rem 1.2rem;border-bottom:1px solid var(--border);}
.mi-em{font-size:1.3rem;width:28px;text-align:center;}
.mi-name{flex:1;font-weight:700;font-size:.88rem;}
.mi-cat{font-size:.7rem;color:var(--muted);background:var(--surface2);padding:.15rem .5rem;border-radius:8px;}
.mi-price{color:var(--cheese);font-weight:700;min-width:70px;text-align:right;font-size:.88rem;}
.toggle-switch{position:relative;display:inline-block;width:42px;height:22px;flex-shrink:0;}
.toggle-switch input{opacity:0;width:0;height:0;}
.toggle-track{position:absolute;inset:0;background:rgba(244,168,0,.2);border-radius:22px;cursor:pointer;transition:.3s;}
.toggle-track::before{content:'';position:absolute;width:16px;height:16px;left:3px;top:3px;background:#A07840;border-radius:50%;transition:.3s;}
input:checked+.toggle-track{background:var(--cheese);}
input:checked+.toggle-track::before{transform:translateX(20px);background:#fff;}

/* ── TOAST ── */
.toast{position:fixed;bottom:2rem;right:2rem;background:#1A0800;color:#FFE8B0;padding:.7rem 1.3rem;border-radius:10px;font-weight:700;font-size:.82rem;z-index:9999;transform:translateY(20px);opacity:0;transition:all .3s;pointer-events:none;box-shadow:0 8px 24px rgba(0,0,0,.4);}
.toast.show{transform:none;opacity:1;}
</style>
</head>
<body>

<!-- MOBILE OVERLAY -->
<div style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:199;" id="mobOverlay" onclick="closeSb()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sb-brand">
    <div class="sb-logo">🧀 CheesyBurgers</div>
    <div class="sb-sub">Admin Panel</div>
  </div>
  <div class="sb-user">
    <div class="sb-avatar"><?= mb_strtoupper(mb_substr($adminName,0,2)) ?></div>
    <div>
      <div class="sb-uname"><?= htmlspecialchars($adminName) ?></div>
      <div class="sb-utag">⚙️ Administrator</div>
    </div>
  </div>
  <div class="sb-scroll">
    <div class="sb-label">Dashboard</div>
    <div class="sb-link active" id="lnk-orders" onclick="showSec('orders',this)">
      <span class="ico">📋</span><span class="lbl">Live Orders</span>
      <span class="sb-cnt"><?= $counts['pending'] ?></span>
    </div>
    <div class="sb-link" id="lnk-menu" onclick="showSec('menu',this)">
      <span class="ico">🍔</span><span class="lbl">Menu Manager</span>
    </div>
    <div class="sb-link" id="lnk-riders" onclick="showSec('riders',this)">
      <span class="ico">🛵</span><span class="lbl">Riders</span>
    </div>
    <div class="sb-label">Quick Links</div>
    <div class="sb-link" onclick="window.location='index.php'">
      <span class="ico">🏠</span><span class="lbl">Go to Website</span>
    </div>
    <div class="sb-link" onclick="window.location='track.php'">
      <span class="ico">📍</span><span class="lbl">Track Page</span>
    </div>
  </div>
  <div class="sb-bottom">
    <button class="dm-row" onclick="toggleDark()">🌙 Toggle Dark Mode</button>
    <button class="sb-logout" onclick="if(confirm('Logout?'))window.location='index.php?logout=1'">
      🚪 Logout
    </button>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:.8rem;">
      <div class="ham" onclick="openSb()"><span></span><span></span><span></span></div>
      <div>
        <div class="tb-title" id="pageTitle">Live Orders</div>
        <div class="tb-sub">Rawalpindi Branch • <?= date('d M Y') ?></div>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:.7rem;">
      <div class="live-chip"><div class="live-dot"></div>Live Sync</div>
      <button class="tb-btn" onclick="location.reload()">🔄 Refresh</button>
    </div>
  </div>

  <div class="page">
    <!-- STATS -->
    <div class="stats-row">
      <div class="stat-card"><div class="sc-ico">📦</div><div><div class="sc-val"><?= $counts['all'] ?></div><div class="sc-lbl">Total Orders</div></div></div>
      <div class="stat-card"><div class="sc-ico">🕐</div><div><div class="sc-val"><?= $counts['pending'] ?></div><div class="sc-lbl">Pending</div></div></div>
      <div class="stat-card"><div class="sc-ico">🍳</div><div><div class="sc-val"><?= $counts['cooking'] ?></div><div class="sc-lbl">Cooking</div></div></div>
      <div class="stat-card"><div class="sc-ico">🛵</div><div><div class="sc-val"><?= $counts['out'] ?></div><div class="sc-lbl">Out</div></div></div>
      <div class="stat-card"><div class="sc-ico">✅</div><div><div class="sc-val"><?= $counts['delivered'] ?></div><div class="sc-lbl">Delivered</div></div></div>
    </div>

    <!-- ════════ ORDERS ════════ -->
    <div id="sec-orders">
      <div class="card">
        <div class="card-hdr">
          <div><h3>📋 Live Orders</h3><p><?= $counts['all'] ?> total today</p></div>
          <div class="ftabs">
            <div class="ftab active" onclick="filterOrders('all',this)">All<span class="f-cnt"><?= $counts['all'] ?></span></div>
            <div class="ftab" onclick="filterOrders('pending',this)">Pending<span class="f-cnt"><?= $counts['pending'] ?></span></div>
            <div class="ftab" onclick="filterOrders('cooking',this)">Cooking<span class="f-cnt"><?= $counts['cooking'] ?></span></div>
            <div class="ftab" onclick="filterOrders('out',this)">Out<span class="f-cnt"><?= $counts['out'] ?></span></div>
            <div class="ftab" onclick="filterOrders('delivered',this)">Done<span class="f-cnt"><?= $counts['delivered'] ?></span></div>
          </div>
        </div>
        <div class="orders-list" id="ordersList">
          <?php foreach($orders as $o): ?>
          <div class="order-card" id="oc-<?= $o['id'] ?>" data-status="<?= $o['status'] ?>">
            <div class="oc-hdr">
              <span class="oc-id">#<?= $o['id'] ?></span>
              <span class="oc-name"><?= htmlspecialchars($o['customer_name']) ?></span>
              <span class="oc-time"><?= date('H:i',strtotime($o['time'])) ?></span>
              <span class="status-badge sb-<?= $o['status'] ?>" id="sbadge-<?= $o['id'] ?>">
                <?= statusIcon($o['status']) ?> <?= ucfirst($o['status']) ?>
              </span>
              <span class="oc-total">Rs.<?= number_format($o['total']) ?></span>
              <button class="oc-expand" onclick="toggleExpand('<?= $o['id'] ?>')">▶</button>
            </div>
            <div class="oc-body" id="ob-<?= $o['id'] ?>">

              <!-- ── 4 STATUS BUTTONS ── -->
              <div class="status-btns">
                <label>⚡ Change Order Status (updates live on customer tracking)</label>
                <?php
                $statuses = [
                  'pending'   => ['🕐','Pending'],
                  'cooking'   => ['🍳','Cooking'],
                  'out'       => ['🛵','Out for Delivery'],
                  'delivered' => ['✅','Delivered'],
                ];
                foreach($statuses as $st=>[$ico,$lbl]):
                  $active = $o['status']===$st ? "active-$st" : '';
                ?>
                <button class="sbtn <?= $active ?>"
                        id="sbtn-<?= $o['id'] ?>-<?= $st ?>"
                        onclick="updateStatus('<?= $o['id'] ?>','<?= $st ?>')">
                  <?= $ico ?> <?= $lbl ?>
                </button>
                <?php endforeach; ?>
              </div>

              <!-- ITEMS -->
              <div class="oc-items">
                <?php
                $items = json_decode($o['items'],true);
                foreach($items as $it)
                  echo htmlspecialchars($it['e'].' '.$it['name'].' ×'.$it['qty'].' — Rs.'.number_format($it['price']*$it['qty'])).'<br>';
                ?>
              </div>
              <?php if(!empty($o['note'])): ?>
              <div class="oc-note">📝 <?= htmlspecialchars($o['note']) ?></div>
              <?php endif; ?>

              <!-- ACTIONS -->
              <div class="oc-actions">
                <select class="act-btn" onchange="assignRider('<?= $o['id'] ?>',this.value)" id="rdr-<?= $o['id'] ?>">
                  <option value="">🛵 Assign Rider</option>
                  <?php foreach($riders as $r): ?>
                  <option value="<?= $r['id'] ?>" <?= ($o['rider']==$r['id'])?'selected':'' ?>>
                    <?= htmlspecialchars($r['name']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
                <button class="act-btn btn-wa"
                  onclick="window.open('https://wa.me/92<?= ltrim($o['phone'],'0') ?>?text=Hi+<?= urlencode($o['customer_name']) ?>,+your+order+%23<?= $o['id'] ?>+is+now+<?= $o['status'] ?>.+CheesyBurgers')">
                  💬 WhatsApp
                </button>
                <?php if($o['lat']&&$o['lng']): ?>
                <button class="act-btn btn-map"
                  onclick="window.open('https://maps.google.com/?q=<?= $o['lat'] ?>,<?= $o['lng'] ?>')">
                  🗺️ Map
                </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ════════ MENU ════════ -->
    <div id="sec-menu" style="display:none">
      <div class="card">
        <div class="card-hdr">
          <div><h3>🍔 Menu Manager</h3><p>Toggle item availability</p></div>
          <button class="tb-btn" onclick="showToast('✅ All changes saved instantly!')">💾 Changes Auto-Save</button>
        </div>
        <?php foreach($menu as $i): ?>
        <div class="menu-item-row">
          <div class="mi-em"><?= $i['emoji'] ?></div>
          <div class="mi-name"><?= htmlspecialchars($i['name']) ?></div>
          <div class="mi-cat"><?= $i['cat'] ?></div>
          <div class="mi-price">Rs.<?= number_format($i['price']) ?></div>
          <label class="toggle-switch">
            <input type="checkbox" <?= $i['avail']?'checked':'' ?>
                   onchange="toggleMenu(<?= $i['id'] ?>,this.checked)">
            <span class="toggle-track"></span>
          </label>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ════════ RIDERS ════════ -->
    <div id="sec-riders" style="display:none">
      <div class="card">
        <div class="card-hdr"><div><h3>🛵 Delivery Riders</h3><p>Active team</p></div></div>
        <?php foreach($riders as $r): ?>
        <div class="menu-item-row">
          <div class="mi-em">🛵</div>
          <div class="mi-name"><?= htmlspecialchars($r['name']) ?></div>
          <div class="mi-price"><?= htmlspecialchars($r['phone']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
// ── Section switching ────────────────────────────────────────
function showSec(name, el){
  ['orders','menu','riders'].forEach(s=>{
    document.getElementById('sec-'+s).style.display = s===name?'block':'none';
  });
  document.querySelectorAll('.sb-link').forEach(l=>l.classList.remove('active'));
  if(el) el.classList.add('active');
  const titles={orders:'Live Orders',menu:'Menu Manager',riders:'Riders'};
  document.getElementById('pageTitle').textContent = titles[name]||name;
  closeSb();
}

// ── Expand/collapse ──────────────────────────────────────────
function toggleExpand(id){
  const body=document.getElementById('ob-'+id);
  const btn =document.querySelector('#oc-'+id+' .oc-expand');
  const open=body.classList.toggle('open');
  btn.textContent = open ? '▼' : '▶';
}

let currentFilter = 'all';

// ── Filter orders ────────────────────────────────────────────
function filterOrders(status,el){
  document.querySelectorAll('.ftab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  currentFilter = status;
  document.querySelectorAll('.order-card').forEach(c=>{
    c.style.display=(status==='all'||c.dataset.status===status)?'':'none';
  });
}

// ── UPDATE STATUS (4 buttons — AJAX, no reload) ──────────────
function updateStatus(orderId, newStatus){
  const fd=new FormData();
  fd.append('action','update_status');
  fd.append('order_id',orderId);
  fd.append('status',newStatus);

  fetch('admin_actions.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      if(!d.success){showToast('❌ Update failed'); return;}

      // Update card data-status
      const card=document.getElementById('oc-'+orderId);
      card.dataset.status=newStatus;

      // Update status badge in header
      const icons={pending:'🕐',cooking:'🍳',out:'🛵',delivered:'✅',cancelled:'❌'};
      const badge=document.getElementById('sbadge-'+orderId);
      badge.className='status-badge sb-'+newStatus;
      badge.textContent=(icons[newStatus]||'')+ ' '+newStatus.charAt(0).toUpperCase()+newStatus.slice(1);

      // Update 4 buttons — deactivate all, activate selected
      ['pending','cooking','out','delivered'].forEach(st => {
        const btn = document.getElementById('sbtn-' + orderId + '-' + st);
        if (!btn) return;
        btn.className = 'sbtn' + (st === newStatus ? ' active-' + st : '');
      });

      // Re-apply current filter
      filterOrders(currentFilter, document.querySelector('.ftab.active'));

      showToast('✅ Order #'+orderId+' → '+newStatus.toUpperCase());
    }).catch(()=>showToast('❌ Network error'));
}

// ── Assign rider ─────────────────────────────────────────────
function assignRider(orderId, riderId){
  if(!riderId) return;
  const fd=new FormData();
  fd.append('action','assign_rider');
  fd.append('order_id',orderId);
  fd.append('rider_id',riderId);
  fetch('admin_actions.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>showToast(d.success?'🛵 Rider assigned!':'❌ Failed'));
}

// ── Toggle menu ──────────────────────────────────────────────
function toggleMenu(id, val){
  const fd=new FormData();
  fd.append('action','toggle_menu'); fd.append('id',id); fd.append('avail',val?1:0);
  fetch('admin_actions.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>showToast(val?'✅ Item enabled':'🚫 Item disabled'));
}

// ── Sidebar mobile ───────────────────────────────────────────
function openSb(){document.getElementById('sidebar').classList.add('open');document.getElementById('mobOverlay').style.display='block';}
function closeSb(){document.getElementById('sidebar').classList.remove('open');document.getElementById('mobOverlay').style.display='none';}

// ── Dark mode ────────────────────────────────────────────────
function toggleDark(){
  const t=document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark';
  document.documentElement.setAttribute('data-theme',t); localStorage.setItem('cb_theme',t);
}
(()=>{const t=localStorage.getItem('cb_theme');if(t)document.documentElement.setAttribute('data-theme',t);})();

// ── Toast ────────────────────────────────────────────────────
function showToast(msg){
  const t=document.getElementById('toast'); t.textContent=msg; t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'),2800);
}

// ── Auto-refresh new orders every 30s ────────────────────────
setInterval(()=>{
  fetch('get_new_orders.php').then(r=>r.json()).then(d=>{
    if(d.count > <?= $counts['all'] ?>) location.reload();
  }).catch(()=>{});
}, 30000);
</script>
</body>
</html>
