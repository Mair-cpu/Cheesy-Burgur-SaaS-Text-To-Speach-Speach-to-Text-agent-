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

$order = null; $error = '';
if (!empty($_GET['order_id'])) {
    $oid = trim($_GET['order_id']);
    $stmt = $conn->prepare("SELECT o.*,r.name AS rider_name,r.phone AS rider_phone FROM orders o LEFT JOIN riders r ON o.rider=r.id WHERE o.id=?");
    $stmt->bind_param("s",$oid);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) $error = 'Order not found. Check your Order ID.';
}

function stepOf($s){ return match($s){'pending'=>1,'cooking'=>2,'out'=>3,'delivered'=>4,default=>1}; }
$step = $order ? stepOf($order['status']) : 0;
$isActive = $order && in_array($order['status'],['pending','cooking','out']);

$history = [];
if($order){
  $stmt = $conn->prepare("SELECT status, changed_at FROM order_status_log WHERE order_id = ? ORDER BY changed_at ASC");
  $stmt->bind_param("s", $order['id']);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $history[] = [
      'status' => $row['status'],
      'time' => date('H:i', strtotime($row['changed_at']))
    ];
  }
  $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Track Order — CheesyBurgers</title>
<link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{--cheese:#F4A800;--melt:#FF6B00;--bg:#FFFDF5;--surface:#fff;--surface2:#FFF5D6;--text:#3D1F00;--text2:#5A3A10;--muted:#8A6040;--border:rgba(244,168,0,.18);--green:#22c55e;}
[data-theme=dark]{--bg:#0F0700;--surface:#1C0E00;--surface2:#241100;--text:#FFE8B0;--text2:#FFD580;--muted:#A07840;--border:rgba(244,168,0,.1);}
body{font-family:'Nunito',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
/* NAV */
.topnav{display:flex;align-items:center;justify-content:space-between;padding:0 1.5rem;height:64px;background:var(--surface);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:99;box-shadow:0 2px 12px rgba(0,0,0,.05);}
.tn-brand{font-family:'Fredoka One',cursive;font-size:1.4rem;color:var(--cheese);text-decoration:none;}
.tn-brand em{color:var(--melt);font-style:normal;}
.tn-right{display:flex;gap:.7rem;align-items:center;}
.tn-btn{font-size:.85rem;color:var(--muted);text-decoration:none;padding:.4rem .9rem;border:1px solid var(--border);border-radius:8px;background:var(--surface);cursor:pointer;font-family:inherit;transition:all .2s;}
.tn-btn:hover{border-color:var(--cheese);color:var(--cheese);}
/* PAGE */
.page{max-width:680px;margin:0 auto;padding:2.5rem 1.5rem;}
.page-title{text-align:center;margin-bottom:2rem;}
.page-title h1{font-size:1.8rem;font-weight:800;}
.page-title h1 span{color:var(--cheese);}
.page-title p{color:var(--muted);margin-top:.4rem;}
/* SEARCH BOX */
.search-box{display:flex;gap:.7rem;margin-bottom:2.5rem;}
.search-inp{flex:1;padding:.85rem 1.2rem;border:2px solid var(--border);border-radius:12px;font-size:1rem;background:var(--surface);color:var(--text);outline:none;font-family:inherit;transition:border .2s;}
.search-inp:focus{border-color:var(--cheese);}
.search-btn{padding:.85rem 1.8rem;background:linear-gradient(135deg,var(--cheese),var(--melt));color:#fff;border:none;border-radius:12px;font-size:.95rem;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap;transition:all .2s;}
.search-btn:hover{opacity:.9;transform:translateY(-1px);}
/* LIVE BADGE */
.live-badge{display:inline-flex;align-items:center;gap:.4rem;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);border-radius:20px;padding:.3rem .9rem;font-size:.78rem;font-weight:700;color:#16a34a;margin-bottom:1rem;}
.live-dot{width:7px;height:7px;background:#22c55e;border-radius:50%;animation:blink 1s infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:.3;}}
/* TIMELINE */
.timeline{display:flex;align-items:center;justify-content:center;padding:1.5rem 0;gap:0;}
.t-step{display:flex;flex-direction:column;align-items:center;gap:.5rem;}
.t-circle{
  width:54px;height:54px;border-radius:50%;
  border:2.5px solid var(--border);
  display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;background:var(--surface);
  transition:all .4s;position:relative;
}
.t-circle.done{background:var(--green);border-color:var(--green);}
.t-circle.current{background:var(--cheese);border-color:var(--cheese);box-shadow:0 0 0 6px rgba(244,168,0,.2);animation:tpulse 1.5s infinite;}
@keyframes tpulse{0%,100%{box-shadow:0 0 0 6px rgba(244,168,0,.2);}50%{box-shadow:0 0 0 14px rgba(244,168,0,.04);}}
.t-lbl{font-size:.72rem;font-weight:700;color:var(--muted);text-align:center;max-width:68px;line-height:1.3;}
.t-lbl.active{color:var(--cheese);}
.t-line{flex:1;height:3px;background:var(--border);transition:background .4s;min-width:20px;}
.t-line.done{background:var(--green);}
/* ORDER CARD */
.od-card{background:var(--surface);border:1px solid var(--border);border-radius:18px;overflow:hidden;margin-top:1.5rem;box-shadow:0 4px 20px rgba(0,0,0,.05);}
.od-top{display:flex;justify-content:space-between;align-items:center;padding:1.2rem 1.5rem;border-bottom:1px solid var(--border);}
.od-id{font-size:1.15rem;font-weight:800;color:var(--cheese);}
.status-pill{padding:.3rem .9rem;border-radius:20px;font-size:.82rem;font-weight:700;}
.sp-pending{background:#FFF5D6;color:#C97D00;}
.sp-cooking{background:#FFE8D6;color:#C95200;}
.sp-out{background:#D6F0FF;color:#0050C9;}
.sp-delivered{background:#D6FFE8;color:#006B2E;}
.sp-cancelled{background:#FFD6D6;color:#C90000;}
.od-body{padding:1.2rem 1.5rem;}
.rider-box{display:flex;align-items:center;gap:.7rem;background:var(--surface2);border-radius:10px;padding:.8rem 1rem;margin-bottom:1rem;}
.rider-ico{font-size:1.5rem;}
.rider-name{font-weight:800;font-size:.9rem;}
.rider-phone{font-size:.78rem;color:var(--muted);}
.od-items{border-top:1px solid var(--border);padding-top:.9rem;margin-top:.5rem;}
.oi{display:flex;justify-content:space-between;padding:.4rem 0;font-size:.88rem;}
.oi-name{flex:1;color:var(--text2);}
.oi-price{font-weight:700;}
.od-sep{height:1px;background:var(--border);margin:.5rem 0;}
.od-fee{display:flex;justify-content:space-between;font-size:.85rem;color:var(--muted);padding:.2rem 0;}
.od-total{display:flex;justify-content:space-between;font-size:1.05rem;font-weight:800;color:var(--cheese);padding:.5rem 0;}
.od-meta{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;margin-top:1rem;font-size:.82rem;}
.od-meta-item{background:var(--surface2);border-radius:8px;padding:.5rem .7rem;}
.od-meta-item b{display:block;color:var(--muted);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:.15rem;}
.status-history { margin-top: 1rem; }
.sh-row { display:flex; align-items:center; gap:.6rem; padding:.3rem 0; font-size:.8rem; color:var(--muted); }
.sh-dot { width:8px; height:8px; border-radius:50%; background:var(--cheese); flex-shrink:0; }
.sh-status { font-weight:700; color:var(--text2); text-transform:capitalize; }
.sh-time { margin-left:auto; }
/* NOT FOUND */
.not-found{text-align:center;padding:3rem 1rem;background:var(--surface);border:1px solid var(--border);border-radius:16px;}
.not-found .em{font-size:3.5rem;display:block;margin-bottom:.8rem;}
.not-found p{color:var(--muted);font-size:.95rem;}
/* MY ORDERS */
.my-orders{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:1rem;margin-bottom:2rem;}
.order-link{display:flex;justify-content:space-between;align-items:center;padding:.5rem;cursor:pointer;border-radius:8px;transition:background .2s;}
.order-link:hover{background:var(--surface2);}
.ol-id{font-weight:800;color:var(--cheese);}
.ol-status{font-size:.85rem;}
.ol-time{color:var(--muted);font-size:.75rem;}

/* REFRESH BAR */
.refresh-bar{text-align:center;margin-top:1rem;font-size:.78rem;color:var(--muted);}
.refresh-bar span{color:var(--cheese);font-weight:700;}
</style>
</head>
<body>
<nav class="topnav">
  <a href="index.php" class="tn-brand">🧀 Cheesy<em>Burgers</em></a>
  <div class="tn-right">
    <button class="tn-btn" onclick="toggleDark()">🌙</button>
    <a href="index.php" class="tn-btn">← Menu</a>
  </div>
</nav>

<div class="page">
  <div class="page-title">
    <h1>Track Your <span>Order</span> 📍</h1>
    <p>Enter your Order ID to see live status</p>
  </div>

  <div class="search-box">
    <input class="search-inp" id="orderIdInput"
           placeholder="e.g. CB-1720000000"
           value="<?= htmlspecialchars($_GET['order_id'] ?? '') ?>"
           type="text">
    <button class="search-btn" onclick="trackOrder()">Track →</button>
  </div>

  <?php if(isset($_SESSION['user_id'])): ?>
  <?php
  $stmt = $conn->prepare("SELECT id, status, time FROM orders WHERE customer_id=? ORDER BY time DESC");
  $stmt->bind_param("i", $_SESSION['user_id']);
  $stmt->execute();
  $userOrders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
  ?>
  <?php if(count($userOrders) > 0): ?>
  <div class="my-orders">
    <h3 style="text-align:center;margin-bottom:1rem;color:var(--cheese);">📋 My Orders</h3>
    <?php foreach($userOrders as $o): ?>
    <?php $icon = match($o['status']){'pending'=>'🕐','cooking'=>'🍳','out'=>'🛵','delivered'=>'✅','cancelled'=>'❌',default=>'❓'}; ?>
    <div class="order-link" onclick="trackOrderById('<?= $o['id'] ?>')">
      <span class="ol-id">#<?= $o['id'] ?></span>
      <span class="ol-status"><?= $icon ?> <?= ucfirst($o['status']) ?></span>
      <span class="ol-time"><?= date('d M H:i', strtotime($o['time'])) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <?php if($order): ?>
  <?php if($isActive): ?>
  <div style="text-align:center">
    <div class="live-badge"><div class="live-dot"></div>Live Tracking Active</div>
  </div>
  <?php endif; ?>

  <!-- TIMELINE -->
  <div class="timeline" id="timeline">
    <?php
    $steps = [
      ['icon'=>'📦','label'=>'Order Placed'],
      ['icon'=>'🍳','label'=>'Cooking'],
      ['icon'=>'🛵','label'=>'Out for Delivery'],
      ['icon'=>'✅','label'=>'Delivered'],
    ];
    foreach($steps as $i=>$s):
      $n = $i+1;
      $cls = $n < $step ? 'done' : ($n === $step ? 'current' : '');
    ?>
    <div class="t-step">
      <div class="t-circle <?= $cls ?>" id="tc<?= $n ?>"><?= $s['icon'] ?></div>
      <div class="t-lbl <?= $cls ? 'active' : '' ?>" id="tl<?= $n ?>"><?= $s['label'] ?></div>
    </div>
    <?php if($i<3): ?>
    <div class="t-line <?= $step > $n ? 'done' : '' ?>" id="tline<?= $n ?>"></div>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <!-- ORDER CARD -->
  <div class="od-card">
    <div class="od-top">
      <div class="od-id">#<?= htmlspecialchars($order['id']) ?></div>
      <span class="status-pill sp-<?= $order['status'] ?>" id="statusPill">
        <?= match($order['status']){
          'pending'=>'🕐 Pending','cooking'=>'🍳 Cooking',
          'out'=>'🛵 Out for Delivery','delivered'=>'✅ Delivered','cancelled'=>'❌ Cancelled',
          default=>ucfirst($order['status'])
        } ?>
      </span>
    </div>
    <div class="od-body">
      <?php if($order['rider_name']): ?>
      <div class="rider-box" id="riderBox">
        <div class="rider-ico">🛵</div>
        <div>
          <div class="rider-name"><?= htmlspecialchars($order['rider_name']) ?></div>
          <div class="rider-phone"><?= htmlspecialchars($order['rider_phone']) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <div class="od-items">
        <?php $items=json_decode($order['items'],true); foreach($items as $it): ?>
        <div class="oi">
          <span class="oi-name"><?= $it['e'].' '.htmlspecialchars($it['name']).' ×'.$it['qty'] ?></span>
          <span class="oi-price">Rs.<?= number_format($it['price']*$it['qty']) ?></span>
        </div>
        <?php endforeach; ?>
        <div class="od-sep"></div>
        <div class="od-fee"><span>Delivery Fee</span><span>Rs.<?= number_format($order['deliv_fee']) ?></span></div>
        <div class="od-total"><span>Total</span><span>Rs.<?= number_format($order['total']) ?></span></div>
      </div>

      <div class="od-meta">
        <div class="od-meta-item"><b>📍 Address</b><?= htmlspecialchars($order['address']) ?></div>
        <div class="od-meta-item"><b>📞 Phone</b><?= htmlspecialchars($order['phone']) ?></div>
        <div class="od-meta-item"><b>💳 Payment</b><?= strtoupper($order['payment']) ?></div>
        <div class="od-meta-item"><b>🕐 Placed</b><?= date('d M, H:i',strtotime($order['time'])) ?></div>
      </div>

      <div class="status-history" id="statusHistory"></div>
    </div>
  </div>

  <?php if($isActive): ?>
  <div class="refresh-bar">Auto-updating every <span>5</span> seconds</div>
  <?php endif; ?>

  <?php elseif($error): ?>
  <div class="not-found"><div class="em">🔍</div><p><?= htmlspecialchars($error) ?></p></div>
  <?php else: ?>
  <div class="not-found"><div class="em">📋</div><p>Enter your Order ID above to see live status</p></div>
  <?php endif; ?>
</div>

<script>
function trackOrder(){
  const id=document.getElementById('orderIdInput').value.trim().toUpperCase();
  if(!id){alert('Enter an Order ID');return;}
  window.location.href='track.php?order_id='+encodeURIComponent(id);
}

function trackOrderById(id){
  document.getElementById('orderIdInput').value = id;
  trackOrder();
}
document.getElementById('orderIdInput').addEventListener('keydown',e=>{if(e.key==='Enter')trackOrder();});

function toggleDark(){
  const t=document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark';
  document.documentElement.setAttribute('data-theme',t); localStorage.setItem('cb_theme',t);
}
(()=>{const t=localStorage.getItem('cb_theme');if(t)document.documentElement.setAttribute('data-theme',t);})();

<?php if($order): ?>
// ── Real-time polling ────────────────────────────────────────
const ORDER_ID = '<?= $order['id'] ?>';
let lastStatus = '<?= $order['status'] ?>';

const STATUS_MAP = {
  pending:  {step:1, icon:'🕐 Pending',   cls:'sp-pending'},
  cooking:  {step:2, icon:'🍳 Cooking',   cls:'sp-cooking'},
  out:      {step:3, icon:'🛵 Out for Delivery', cls:'sp-out'},
  delivered:{step:4, icon:'✅ Delivered', cls:'sp-delivered'},
  cancelled:{step:0, icon:'❌ Cancelled', cls:'sp-cancelled'},
};

let orderHistory = <?= json_encode($history) ?>;

function updateTimeline(step) {
  for (let n = 1; n <= 4; n++) {
    const circle = document.getElementById('tc' + n);
    const label  = document.getElementById('tl' + n);
    if (!circle || !label) continue;
    
    circle.className = 't-circle';
    label.className  = 't-lbl';
    
    if (n < step) {
      circle.classList.add('done');
      label.classList.add('done');
    } else if (n === step) {
      circle.classList.add('current');
      label.classList.add('current');
    }
    
    if (n < 4) {
      const line = document.getElementById('tline' + n);
      if (line) line.className = 't-line' + (step > n ? ' done' : '');
    }
  }
}

function renderHistory(history) {
  const el = document.getElementById('statusHistory');
  if (!el || !history) return;
  el.innerHTML = history.map(h =>
    '<div class="sh-row">' +
    '<span class="sh-dot"></span>' +
    '<span class="sh-status">' + h.status + '</span>' +
    '<span class="sh-time">' + h.time + '</span>' +
    '</div>'
  ).join('');
}

function poll(){
  fetch('get_order_status.php?order_id='+ORDER_ID+'&_='+Date.now())
    .then(r=>r.json())
    .then(d=>{
      if(d.error) return;
      if(d.status !== lastStatus){
        lastStatus = d.status;
        const m = STATUS_MAP[d.status] || STATUS_MAP.pending;
        // Update pill
        const pill = document.getElementById('statusPill');
        pill.textContent = m.icon;
        pill.className = 'status-pill '+m.cls;
        // Update timeline
        updateTimeline(m.step);
        // If delivered, show celebration and stop polling
        if(d.status==='delivered'){
          const msg = document.createElement('div');
          msg.innerHTML = '🎉 Order Delivered! Enjoy your meal!';
          msg.style.cssText = 'text-align:center;margin-top:1rem;font-size:1.2rem;font-weight:bold;color:#22c55e;';
          document.querySelector('.od-card').appendChild(msg);
          clearInterval(pollTimer);
        } else if(d.status==='cancelled'){
          clearInterval(pollTimer);
        }
      }
      renderHistory(d.history);
    }).catch(()=>{});
}

poll();
pollTimer = setInterval(poll, 5000);
renderHistory(orderHistory);
<?php endif; ?>
</script>
</body>
</html>
