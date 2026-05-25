<?php
session_start();
include 'db_config.php';

// Load available menu
$menu = [];
$res  = $conn->query("SELECT * FROM menu WHERE avail=1 ORDER BY cat,name");
while ($r = $res->fetch_assoc()) $menu[] = $r;

// Deals
$deals = [
    ['id'=>1,'name'=>"Cheese Overload Burger",'desc'=>"Triple cheese, beef patty, crispy bacon",'price'=>950,'old'=>1200,'cat'=>"burger",'e'=>"🍔",'bg'=>"linear-gradient(135deg,#FFF5D6,#FFE08A)",'badge'=>"20% OFF",'r'=>4.9],
    ['id'=>2,'name'=>"4-Cheese Pizza",'desc'=>"Mozzarella, cheddar, gouda, parmesan",'price'=>1150,'old'=>1400,'cat'=>"pizza",'e'=>"🍕",'bg'=>"linear-gradient(135deg,#FFF0D6,#FFCC80)",'badge'=>"HOT 🔥",'r'=>4.8],
    ['id'=>3,'name'=>"Cheesy Fries Bucket",'desc'=>"Loaded fries with nacho cheese sauce",'price'=>480,'old'=>600,'cat'=>"fries",'e'=>"🍟",'bg'=>"linear-gradient(135deg,#FFF5D6,#FFE08A)",'badge'=>"BUY 1 GET 1",'r'=>4.7],
    ['id'=>4,'name'=>"BBQ Chicken Pizza",'desc'=>"Smoky BBQ, grilled chicken, mozzarella",'price'=>1050,'old'=>1300,'cat'=>"pizza",'e'=>"🍕",'bg'=>"linear-gradient(135deg,#FFE8D6,#FFAB76)",'badge'=>"NEW",'r'=>4.9],
    ['id'=>5,'name'=>"Zinger Cheese Wrap",'desc'=>"Crispy zinger, cheese slice, garlic mayo",'price'=>550,'old'=>700,'cat'=>"wrap",'e'=>"🌯",'bg'=>"linear-gradient(135deg,#FFF0D6,#FFD566)",'badge'=>"25% OFF",'r'=>4.6],
    ['id'=>6,'name'=>"Cheese Lava Shake",'desc'=>"Thick milkshake with real cheese swirl",'price'=>380,'old'=>450,'cat'=>"drink",'e'=>"🥛",'bg'=>"linear-gradient(135deg,#FFF5D6,#FFE08A)",'badge'=>"FAN FAV ⭐",'r'=>5.0],
];

$cart      = $_SESSION['cart'] ?? [];
$cartCount = array_sum(array_column($cart,'qty'));
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin    = ($_SESSION['user_role'] ?? '') === 'admin';
$userName   = $_SESSION['user_name']  ?? 'Guest';
$userInitials = $isLoggedIn ? mb_strtoupper(mb_substr($userName,0,2)) : 'GU';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Cheesy Burgers — Seriously Cheesy</title>
<link href="https://fonts.googleapis.com/css2?family=Fredoka+One&family=Nunito:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{
  --cheese:#F4A800;--cheese-dark:#C97D00;--cheese-light:#FFD966;--cheese-pale:#FFF5D6;
  --melt:#FF6B00;--melt-light:#FF9B4E;
  --sidebar-w:260px;--nav-h:68px;
  --bg:#FFFDF5;--surface:#FFFFFF;--surface2:#FFFBF0;
  --hero-bg:linear-gradient(135deg,#FFF5D6 0%,#FFFBF0 50%,#FFF0D0 100%);
  --deals-bg:#FFF5D6;--menu-bg:linear-gradient(180deg,#FFFDF5,#FFF5D6);
  --text:#3D1F00;--text2:#5A3A10;--muted:#8A6040;
  --border:rgba(244,168,0,.18);
  --cshadow:0 4px 20px rgba(0,0,0,.05);--cshadow-h:0 16px 40px rgba(244,168,0,.22);
  --sidebar-bg:linear-gradient(180deg,#2D1500,#1A0A00);
  --navbar-bg:rgba(255,253,245,.94);--navbar-border:rgba(244,168,0,.2);
  --search-bg:#FFF5D6;--input-col:#3D1F00;
  --cart-bg:#FFFFFF;--mc-bg:#FFF5D6;
}
[data-theme="dark"]{
  --bg:#0F0700;--surface:#1C0E00;--surface2:#241100;
  --hero-bg:linear-gradient(135deg,#1C0E00,#0F0700,#1A0900);
  --deals-bg:#180C00;--menu-bg:linear-gradient(180deg,#0F0700,#180C00);
  --text:#FFE8B0;--text2:#FFD580;--muted:#A07840;
  --border:rgba(244,168,0,.1);
  --cshadow:0 4px 20px rgba(0,0,0,.35);--cshadow-h:0 16px 40px rgba(244,168,0,.15);
  --sidebar-bg:linear-gradient(180deg,#0A0400,#060200);
  --navbar-bg:rgba(12,5,0,.96);--navbar-border:rgba(244,168,0,.1);
  --search-bg:rgba(244,168,0,.06);--input-col:#FFE8B0;
  --cart-bg:#1C0E00;--mc-bg:rgba(244,168,0,.07);
}
html{scroll-behavior:smooth;}
body{font-family:'Nunito',sans-serif;background:var(--bg);color:var(--text);overflow-x:hidden;cursor:none;transition:background .3s,color .3s;}
@media(hover:none){body{cursor:auto;}}

/* ── CUSTOM CURSOR ── */
.cursor,.cursor-trail{pointer-events:none;position:fixed;border-radius:50%;z-index:9999;transition:transform .15s;}
.cursor{width:20px;height:20px;background:var(--cheese);mix-blend-mode:multiply;transform:translate(-50%,-50%);}
.cursor-trail{width:8px;height:8px;background:var(--melt);z-index:9998;opacity:.6;transform:translate(-50%,-50%);}
@media(hover:none){.cursor,.cursor-trail{display:none;}}

/* ── INTRO ── */
#intro{position:fixed;inset:0;z-index:10000;background:linear-gradient(135deg,#2D1500,#1A0A00);display:flex;flex-direction:column;align-items:center;justify-content:center;transition:opacity .8s,visibility .8s;}
#intro.gone{opacity:0;visibility:hidden;pointer-events:none;}
.drip-wrap{position:absolute;top:0;left:0;right:0;display:flex;justify-content:space-around;}
.drip{width:28px;height:0;background:var(--cheese);border-radius:0 0 50% 50%;animation:drip 2s ease-in forwards;}
.drip:nth-child(1){animation-delay:.1s;height:60px;}
.drip:nth-child(2){animation-delay:.3s;height:90px;}
.drip:nth-child(3){animation-delay:.0s;height:50px;}
.drip:nth-child(4){animation-delay:.4s;height:110px;}
.drip:nth-child(5){animation-delay:.2s;height:75px;}
.drip:nth-child(6){animation-delay:.15s;height:55px;}
.drip:nth-child(7){animation-delay:.35s;height:95px;}
.drip:nth-child(8){animation-delay:.05s;height:65px;}
.drip:nth-child(9){animation-delay:.25s;height:85px;}
.drip:nth-child(10){animation-delay:.45s;height:105px;}
@keyframes drip{0%{height:0;opacity:0;}30%{opacity:1;}100%{opacity:1;}}
.intro-logo{text-align:center;color:#FFE8B0;}
.iemoji{font-size:5rem;display:block;animation:ipop .5s .8s both;}
@keyframes ipop{0%{transform:scale(0);}70%{transform:scale(1.2);}100%{transform:scale(1);}}
.intro-logo h1{font-family:'Fredoka One',cursive;font-size:3rem;color:var(--cheese);margin:.5rem 0 .3rem;letter-spacing:2px;}
.intro-logo p{color:#A07840;font-size:1rem;}

/* ── MOBILE OVERLAY ── */
.mob-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:498;backdrop-filter:blur(2px);}
.mob-overlay.show{display:block;}

/* ── SIDEBAR ── */
.sidebar{
  position:fixed;top:0;left:0;width:var(--sidebar-w);height:100vh;
  background:var(--sidebar-bg);
  display:flex;flex-direction:column;z-index:500;
  transition:transform .3s;overflow:hidden;
}
@media(max-width:900px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.open{transform:translateX(0);}
}
.sb-brand{padding:1.4rem 1.4rem 1rem;border-bottom:1px solid rgba(244,168,0,.1);}
.sb-logo{font-family:'Fredoka One',cursive;font-size:1.35rem;color:var(--cheese);letter-spacing:.5px;}
.sb-sub{font-size:.72rem;color:#A07840;margin-top:.15rem;letter-spacing:.5px;}
.sb-user{display:flex;align-items:center;gap:.8rem;padding:1rem 1.4rem;border-bottom:1px solid rgba(244,168,0,.1);}
.sb-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--cheese),var(--melt));display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:800;color:#fff;flex-shrink:0;}
.sb-uname{font-size:.88rem;font-weight:800;color:#FFE8B0;}
.sb-utag{font-size:.72rem;color:#A07840;margin-top:.1rem;}
.sb-scroll{flex:1;overflow-y:auto;padding:.8rem 0;}
.sb-scroll::-webkit-scrollbar{width:3px;}
.sb-scroll::-webkit-scrollbar-thumb{background:rgba(244,168,0,.2);border-radius:3px;}
.sb-label{font-size:.67rem;font-weight:800;color:#A07840;letter-spacing:1.5px;text-transform:uppercase;padding:.6rem 1.4rem .3rem;}
.sb-link{display:flex;align-items:center;gap:.75rem;padding:.65rem 1.4rem;cursor:pointer;transition:all .2s;position:relative;border-radius:0;}
.sb-link:hover{background:rgba(244,168,0,.08);}
.sb-link.active{background:rgba(244,168,0,.12);}
.sb-link.active::before{content:'';position:absolute;left:0;top:20%;height:60%;width:3px;background:var(--cheese);border-radius:0 3px 3px 0;}
.sb-link .ico{font-size:1.1rem;width:22px;text-align:center;flex-shrink:0;}
.sb-link .lbl{font-size:.87rem;font-weight:700;color:#C8A060;}
.sb-link:hover .lbl,.sb-link.active .lbl{color:var(--cheese);}
.sb-badge{margin-left:auto;background:var(--melt);color:#fff;border-radius:10px;padding:.1rem .45rem;font-size:.7rem;font-weight:800;}
.cat-chips{padding:.4rem 1rem;}
.cat-chip{display:flex;align-items:center;gap:.5rem;padding:.45rem .8rem;border-radius:20px;font-size:.8rem;font-weight:700;color:#A07840;cursor:pointer;transition:all .2s;margin-bottom:.3rem;}
.cat-chip:hover{background:rgba(244,168,0,.08);color:var(--cheese);}
.cat-chip.active{background:rgba(244,168,0,.15);color:var(--cheese);}
.chip-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.sb-bottom{padding:1rem 1.2rem;border-top:1px solid rgba(244,168,0,.1);display:flex;flex-direction:column;gap:.6rem;}
.dm-row{display:flex;align-items:center;gap:.6rem;background:rgba(244,168,0,.07);border:1px solid rgba(244,168,0,.12);border-radius:10px;padding:.6rem .9rem;cursor:pointer;transition:all .2s;}
.dm-row:hover{background:rgba(244,168,0,.12);}
.dm-ico,.dm-lbl{font-size:.85rem;color:#C8A060;}
.dm-pill{width:28px;height:14px;border-radius:7px;background:rgba(244,168,0,.2);margin-left:auto;position:relative;transition:background .3s;}
.dm-pill::after{content:'';position:absolute;width:10px;height:10px;border-radius:50%;background:#A07840;top:2px;left:2px;transition:transform .3s,background .3s;}
[data-theme="dark"] .dm-pill{background:var(--cheese);}
[data-theme="dark"] .dm-pill::after{transform:translateX(14px);background:#fff;}
.sb-act{display:flex;align-items:center;gap:.6rem;background:rgba(244,168,0,.07);border:1px solid rgba(244,168,0,.12);border-radius:10px;padding:.6rem .9rem;cursor:pointer;font-size:.85rem;color:#C8A060;font-family:inherit;transition:all .2s;width:100%;}
.sb-act:hover,.sb-act.logout:hover{background:rgba(255,107,0,.12);color:var(--melt);border-color:rgba(255,107,0,.2);}
.sb-act.login:hover{background:rgba(244,168,0,.15);color:var(--cheese);border-color:rgba(244,168,0,.3);}

/* ── TOPBAR ── */
.topbar{
  position:sticky;top:0;z-index:400;
  display:flex;align-items:center;gap:1rem;padding:0 1.5rem;height:var(--nav-h);
  background:var(--navbar-bg);backdrop-filter:blur(12px);
  border-bottom:1px solid var(--navbar-border);
}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:.4rem;}
@media(max-width:900px){.hamburger{display:flex;}}
.hamburger span{width:22px;height:2.5px;background:var(--cheese);border-radius:2px;transition:all .3s;}
.tb-search{flex:1;max-width:380px;position:relative;}
.tb-search input{width:100%;padding:.55rem 1rem .55rem 2.5rem;border:1.5px solid var(--border);border-radius:30px;background:var(--search-bg);color:var(--input-col);font-size:.88rem;font-family:inherit;outline:none;transition:border .2s;}
.tb-search input:focus{border-color:var(--cheese);}
.tb-search::before{content:'🔍';position:absolute;left:.7rem;top:50%;transform:translateY(-50%);font-size:.85rem;}
.tb-icons{margin-left:auto;display:flex;align-items:center;gap:.6rem;}
.tb-ico{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1rem;border:1px solid var(--border);background:var(--surface);transition:all .2s;position:relative;}
.tb-ico:hover{background:var(--cheese-pale);border-color:var(--cheese);}
.tb-cart-wrap{position:relative;}
.cart-badge{position:absolute;top:-4px;right:-4px;background:var(--melt);color:#fff;border-radius:50%;width:18px;height:18px;font-size:.65rem;font-weight:800;display:flex;align-items:center;justify-content:center;font-family:inherit;}
.tb-user-pill{display:flex;align-items:center;gap:.5rem;padding:.35rem .9rem .35rem .5rem;border-radius:20px;background:var(--surface2);border:1px solid var(--border);cursor:pointer;transition:all .2s;}
.tb-user-pill:hover{border-color:var(--cheese);}
.tb-pill-av{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--cheese),var(--melt));display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;color:#fff;}
.tb-pill-nm{font-size:.82rem;font-weight:700;color:var(--text2);}

/* ── MAIN WRAP ── */
.main-wrap{margin-left:var(--sidebar-w);min-height:100vh;transition:margin .3s;}
@media(max-width:900px){.main-wrap{margin-left:0;}}

/* ── HERO ── */
.hero{background:var(--hero-bg);padding:5rem 2rem 4rem;text-align:center;position:relative;overflow:hidden;}
.hero::before{content:'🧀';position:absolute;font-size:18rem;opacity:.04;top:-3rem;right:-3rem;pointer-events:none;}
.hero-badge{display:inline-flex;align-items:center;gap:.4rem;background:rgba(244,168,0,.12);border:1px solid rgba(244,168,0,.3);border-radius:20px;padding:.35rem 1rem;font-size:.8rem;font-weight:700;color:var(--cheese-dark);margin-bottom:1.2rem;}
.hero h1{font-family:'Fredoka One',cursive;font-size:clamp(2.5rem,6vw,4.5rem);color:var(--text);line-height:1.1;margin-bottom:.6rem;}
.hero h1 span{color:var(--cheese);}
.hero p{font-size:1.05rem;color:var(--text2);max-width:480px;margin:0 auto 2rem;line-height:1.6;}
.hero-btns{display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;}
.hbtn{padding:.85rem 2rem;border-radius:14px;font-size:.95rem;font-weight:800;cursor:pointer;text-decoration:none;border:none;font-family:inherit;transition:all .25s;display:inline-flex;align-items:center;gap:.4rem;}
.hbtn.primary{background:linear-gradient(135deg,var(--cheese),var(--melt));color:#fff;box-shadow:0 8px 24px rgba(244,168,0,.4);}
.hbtn.primary:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(244,168,0,.5);}
.hbtn.outline{border:2px solid var(--cheese);color:var(--cheese);background:transparent;}
.hbtn.outline:hover{background:var(--cheese);color:#fff;}
.hero-stats{display:flex;gap:2rem;justify-content:center;margin-top:2.5rem;flex-wrap:wrap;}
.hstat{text-align:center;}
.hstat-val{font-size:1.6rem;font-weight:800;color:var(--cheese);}
.hstat-lbl{font-size:.75rem;color:var(--muted);margin-top:.1rem;}

/* ── DEALS SECTION ── */
.section{padding:3rem 1.8rem;}
.sec-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;}
.sec-title{font-size:1.4rem;font-weight:800;}
.sec-title span{color:var(--cheese);}
.sec-sub{font-size:.83rem;color:var(--muted);margin-top:.2rem;}
.see-all{font-size:.83rem;font-weight:700;color:var(--cheese);cursor:pointer;text-decoration:none;padding:.35rem .9rem;border:1px solid var(--border);border-radius:8px;transition:all .2s;}
.see-all:hover{background:var(--cheese);color:#fff;border-color:var(--cheese);}
.deals-scroll{display:flex;gap:1rem;overflow-x:auto;padding-bottom:.8rem;scroll-snap-type:x mandatory;}
.deals-scroll::-webkit-scrollbar{height:4px;}
.deals-scroll::-webkit-scrollbar-thumb{background:var(--cheese);border-radius:4px;}
.deal-card{
  flex-shrink:0;width:240px;scroll-snap-align:start;
  border-radius:18px;padding:1.3rem;
  border:1px solid var(--border);
  box-shadow:var(--cshadow);
  transition:transform .25s,box-shadow .25s;
  position:relative;overflow:hidden;cursor:pointer;
}
.deal-card:hover{transform:translateY(-5px);box-shadow:var(--cshadow-h);}
.deal-badge{position:absolute;top:.75rem;right:.75rem;background:var(--melt);color:#fff;font-size:.68rem;font-weight:800;padding:.2rem .5rem;border-radius:6px;}
.deal-em{font-size:3rem;margin-bottom:.7rem;display:block;}
.deal-rating{font-size:.72rem;color:var(--muted);margin-bottom:.3rem;}
.deal-name{font-size:.95rem;font-weight:800;margin-bottom:.25rem;}
.deal-desc{font-size:.78rem;color:var(--muted);margin-bottom:.8rem;line-height:1.4;}
.deal-price{display:flex;align-items:center;gap:.6rem;}
.d-price{font-size:1.1rem;font-weight:800;color:var(--cheese);}
.d-old{font-size:.78rem;color:var(--muted);text-decoration:line-through;}
.deal-add{margin-left:auto;width:32px;height:32px;border-radius:50%;background:var(--cheese);border:none;color:#fff;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;}
.deal-add:hover{background:var(--melt);transform:scale(1.1);}

/* ── MENU SECTION ── */
.menu-section{background:var(--menu-bg);padding:3rem 1.8rem;}
.menu-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:1rem;margin-top:1.2rem;}
.menu-card{
  background:var(--mc-bg);border:1px solid var(--border);border-radius:16px;
  padding:1.1rem;display:flex;flex-direction:column;align-items:center;text-align:center;
  transition:transform .2s,box-shadow .2s;cursor:default;
}
.menu-card:hover{transform:translateY(-4px);box-shadow:var(--cshadow-h);}
.mc-em{font-size:2.8rem;margin-bottom:.6rem;}
.mc-nm{font-size:.92rem;font-weight:800;margin-bottom:.25rem;}
.mc-ds{font-size:.76rem;color:var(--muted);line-height:1.4;margin-bottom:.8rem;flex:1;}
.mc-ft{display:flex;align-items:center;justify-content:space-between;width:100%;}
.mc-pr{font-size:1rem;font-weight:800;color:var(--cheese);}
.mc-ab{width:32px;height:32px;border-radius:50%;background:var(--cheese);border:none;color:#fff;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;font-weight:700;}
.mc-ab:hover{background:var(--melt);transform:scale(1.1);}
.mc-ab.on{background:#22c55e;}

/* ── CART DRAWER ── */
.cart-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:600;display:none;}
.cart-overlay.show{display:block;}
.cart-drawer{
  position:fixed;top:0;right:-420px;width:420px;max-width:95vw;
  height:100vh;background:var(--cart-bg);z-index:601;
  transition:right .3s cubic-bezier(.4,0,.2,1);
  display:flex;flex-direction:column;
  box-shadow:-8px 0 40px rgba(0,0,0,.15);
}
.cart-drawer.open{right:0;}
.cd-hdr{display:flex;justify-content:space-between;align-items:center;padding:1.2rem 1.5rem;border-bottom:1px solid var(--border);}
.cd-hdr h3{font-size:1.05rem;font-weight:800;}
.cd-close{background:none;border:none;font-size:1.4rem;cursor:pointer;color:var(--muted);width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:background .2s;}
.cd-close:hover{background:var(--surface2);}
.cd-items{flex:1;overflow-y:auto;padding:1rem 1.5rem;}
.cd-empty{text-align:center;padding:3rem 1rem;color:var(--muted);}
.cd-empty .em{font-size:3rem;display:block;margin-bottom:.6rem;}
.ci{display:flex;align-items:center;gap:.8rem;padding:.8rem 0;border-bottom:1px solid var(--border);}
.ci-em{font-size:1.5rem;width:36px;text-align:center;}
.ci-info{flex:1;}
.ci-name{font-size:.88rem;font-weight:700;}
.ci-price{font-size:.78rem;color:var(--muted);margin-top:.1rem;}
.ci-qty{display:flex;align-items:center;gap:.4rem;}
.qty-btn{width:26px;height:26px;border-radius:50%;border:1.5px solid var(--border);background:var(--surface);cursor:pointer;font-size:.9rem;display:flex;align-items:center;justify-content:center;color:var(--text);font-weight:700;transition:all .2s;}
.qty-btn:hover{background:var(--cheese);color:#fff;border-color:var(--cheese);}
.qty-num{font-weight:800;min-width:20px;text-align:center;font-size:.88rem;}
.ci-line{font-weight:800;color:var(--cheese);min-width:68px;text-align:right;font-size:.88rem;}
.cd-footer{padding:1.2rem 1.5rem;border-top:1px solid var(--border);}
.cd-row{display:flex;justify-content:space-between;font-size:.85rem;color:var(--muted);padding:.25rem 0;}
.cd-total{display:flex;justify-content:space-between;font-size:1.05rem;font-weight:800;color:var(--cheese);margin:.6rem 0 1rem;padding-top:.6rem;border-top:1px solid var(--border);}
.cd-checkout{display:block;width:100%;padding:.9rem;background:linear-gradient(135deg,var(--cheese),var(--melt));color:#fff;border:none;border-radius:12px;font-size:.95rem;font-weight:800;cursor:pointer;font-family:inherit;text-align:center;text-decoration:none;transition:all .2s;}
.cd-checkout:hover{opacity:.9;transform:translateY(-1px);}

/* ── MODALS ── */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:800;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
.modal.show{display:flex;}
.modal-box{background:var(--surface);border-radius:22px;padding:2rem;width:100%;max-width:440px;margin:1rem;border:1px solid var(--border);box-shadow:0 24px 60px rgba(0,0,0,.25);animation:mslide .25s ease;}
@keyframes mslide{from{transform:translateY(20px);opacity:0;}to{transform:none;opacity:1;}}
.modal-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;}
.modal-top h2{font-size:1.3rem;font-weight:800;}
.modal-top h2 span{color:var(--cheese);}
.m-close{background:none;border:none;font-size:1.3rem;cursor:pointer;color:var(--muted);padding:.2rem;}
.mfi{width:100%;padding:.75rem 1rem;border:1.5px solid var(--border);border-radius:10px;font-size:.93rem;font-family:inherit;background:var(--surface);color:var(--text);outline:none;transition:border .2s;margin-bottom:.85rem;}
.mfi:focus{border-color:var(--cheese);}
.mfi option{background:var(--surface);}
.mfrow{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;}
.btn-auth{width:100%;padding:.85rem;background:linear-gradient(135deg,var(--cheese),var(--melt));color:#fff;border:none;border-radius:12px;font-size:.95rem;font-weight:800;cursor:pointer;font-family:inherit;transition:all .2s;margin-top:.2rem;}
.btn-auth:hover{opacity:.9;transform:translateY(-1px);}
.auth-switch{text-align:center;margin-top:1rem;font-size:.85rem;color:var(--muted);}
.auth-switch a{color:var(--cheese);font-weight:700;cursor:pointer;}
.auth-err{background:#FFE0E0;color:#C00;border-radius:8px;padding:.55rem .9rem;font-size:.82rem;margin-bottom:.8rem;display:none;}
.auth-err.show{display:block;}

/* ── CHAT BUTTON ── */
.chat-fab{
  position:fixed;bottom:2rem;right:2rem;z-index:700;
  width:56px;height:56px;border-radius:50%;
  background:linear-gradient(135deg,var(--cheese),var(--melt));
  border:none;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  font-size:1.5rem;color:#fff;
  box-shadow:0 8px 24px rgba(244,168,0,.5);
  transition:all .3s;
}
.chat-fab:hover{transform:scale(1.1);box-shadow:0 12px 32px rgba(244,168,0,.6);}
.chat-fab .chat-tooltip{
  position:absolute;right:calc(100% + .6rem);top:50%;transform:translateY(-50%);
  background:#1A0A00;color:#FFE8B0;font-size:.78rem;font-weight:700;font-family:'Nunito',sans-serif;
  white-space:nowrap;padding:.35rem .75rem;border-radius:8px;
  opacity:0;pointer-events:none;transition:opacity .2s;
}
.chat-fab:hover .chat-tooltip{opacity:1;}

/* ── TOAST ── */
.toast{position:fixed;bottom:6rem;right:2rem;background:#1C0E00;color:#FFE8B0;padding:.75rem 1.3rem;border-radius:12px;font-weight:700;font-size:.85rem;z-index:900;transform:translateY(20px);opacity:0;transition:all .3s;pointer-events:none;box-shadow:0 8px 24px rgba(0,0,0,.3);}
.toast.show{transform:none;opacity:1;}

/* ── FOOTER ── */
.footer{text-align:center;padding:2.5rem;color:var(--muted);font-size:.82rem;border-top:1px solid var(--border);background:var(--surface);}

/* ── BANNER ── */
.banner{background:var(--sidebar-bg);padding:1.5rem 2rem;display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;}
.banner-txt h2{font-family:'Fredoka One',cursive;font-size:1.5rem;color:var(--cheese);}
.banner-txt p{font-size:.85rem;color:#A07840;margin-top:.2rem;}
.banner-btn{margin-left:auto;padding:.7rem 1.8rem;background:var(--cheese);color:#fff;border:none;border-radius:10px;font-weight:800;cursor:pointer;font-family:inherit;font-size:.9rem;transition:all .2s;text-decoration:none;display:inline-block;}
.banner-btn:hover{background:var(--melt);}
</style>
</head>
<body>

<!-- CUSTOM CURSOR -->
<div class="cursor" id="cursor"></div>
<div class="cursor-trail" id="trailEl"></div>

<!-- INTRO SCREEN -->
<div id="intro">
  <div class="drip-wrap">
    <div class="drip"></div><div class="drip"></div><div class="drip"></div>
    <div class="drip"></div><div class="drip"></div><div class="drip"></div>
    <div class="drip"></div><div class="drip"></div><div class="drip"></div>
    <div class="drip"></div>
  </div>
  <div class="intro-logo">
    <span class="iemoji">🍔</span>
    <h1>CHEESY BURGERS</h1>
    <p>Seriously, Dangerously Cheesy™</p>
  </div>
</div>

<!-- MOBILE OVERLAY -->
<div class="mob-overlay" id="mobOverlay" onclick="closeSb()"></div>

<!-- LOGIN MODAL -->
<div class="modal" id="loginModal">
  <div class="modal-box">
    <div class="modal-top">
      <h2>🔑 Login to <span>CheesyBurgers</span></h2>
      <button class="m-close" onclick="closeModal('loginModal')">✕</button>
    </div>
    <div class="auth-err" id="loginErr"></div>
    <input class="mfi" id="loginEmail" type="email" placeholder="Email address">
    <input class="mfi" id="loginPass"  type="password" placeholder="Password">
    <button class="btn-auth" onclick="doLogin()">Login →</button>
    <div class="auth-switch">No account? <a onclick="switchModal('loginModal','signupModal')">Sign Up Free</a></div>
  </div>
</div>

<!-- SIGNUP MODAL -->
<div class="modal" id="signupModal">
  <div class="modal-box">
    <div class="modal-top">
      <h2>🧀 Join <span>CheesyBurgers</span></h2>
      <button class="m-close" onclick="closeModal('signupModal')">✕</button>
    </div>
    <div class="auth-err" id="signupErr"></div>
    <div class="mfrow">
      <input class="mfi" id="suName"  type="text"  placeholder="Full Name *">
      <input class="mfi" id="suPhone" type="tel"   placeholder="Phone Number">
    </div>
    <input class="mfi" id="suEmail"   type="email"    placeholder="Email Address *">
    <input class="mfi" id="suPass"    type="password" placeholder="Password *">
    <input class="mfi" id="suAddress" type="text"     placeholder="Street Address">
    <select class="mfi" id="suCity">
      <option value="">Select City</option>
      <option value="Rawalpindi">Rawalpindi</option>
      <option value="Islamabad">Islamabad</option>
      <option value="Lahore">Lahore</option>
      <option value="Karachi">Karachi</option>
    </select>
    <button class="btn-auth" onclick="doSignup()">Create Account →</button>
    <div class="auth-switch">Already have account? <a onclick="switchModal('signupModal','loginModal')">Login</a></div>
  </div>
</div>

<!-- CART OVERLAY + DRAWER -->
<div class="cart-overlay" id="cartOverlay" onclick="closeCart()"></div>
<div class="cart-drawer" id="cartDrawer">
  <div class="cd-hdr">
    <h3>🛒 Your Cart <span id="cartCount" style="color:var(--muted);font-size:.82rem;font-weight:600"></span></h3>
    <button class="cd-close" onclick="closeCart()">✕</button>
  </div>
  <div class="cd-items" id="cdItems"><div class="cd-empty"><span class="em">🛒</span>Your cart is empty</div></div>
  <div class="cd-footer" id="cdFooter" style="display:none">
    <div class="cd-row"><span>Subtotal</span><span id="cdSubtotal">Rs.0</span></div>
    <div class="cd-row"><span>Delivery Fee</span><span>Rs.80</span></div>
    <div class="cd-total"><span>Total</span><span id="cdTotal">Rs.0</span></div>
    <a href="checkout.php" class="cd-checkout">Proceed to Checkout →</a>
  </div>
</div>

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
      <div class="sb-utag"><?= $isLoggedIn ? ($isAdmin ? '⚙️ Administrator' : '⭐ Gold Member') : 'Guest — Please Login' ?></div>
    </div>
  </div>
  <div class="sb-scroll">
    <div class="sb-label">Main Menu</div>
    <div class="sb-link active" id="lnk-home" onclick="navTo('home',this)"><span class="ico">🏠</span><span class="lbl">Home</span></div>
    <div class="sb-link" onclick="navTo('deals',this)"><span class="ico">🔥</span><span class="lbl">Hot Deals</span><span class="sb-badge">6</span></div>
    <div class="sb-link" onclick="navTo('menu-section',this)"><span class="ico">🍔</span><span class="lbl">Full Menu</span></div>
    <div class="sb-link" onclick="window.location='track.php'"><span class="ico">📍</span><span class="lbl">Track Order</span></div>
    <div class="sb-link" onclick="openCart()"><span class="ico">🛒</span><span class="lbl">Cart</span><span class="sb-badge" id="sbCartBadge"><?= $cartCount ?: '' ?></span></div>
    <?php if($isAdmin): ?>
    <div class="sb-link" onclick="window.location='admin.php'"><span class="ico">⚙️</span><span class="lbl">Admin Panel</span></div>
    <?php endif; ?>

    <div class="sb-label">Categories</div>
    <div class="cat-chips">
      <div class="cat-chip active" onclick="filterCat('all',this)"><div class="chip-dot" style="background:#F4A800"></div>All Items</div>
      <div class="cat-chip" onclick="filterCat('burger',this)"><div class="chip-dot" style="background:#C97D00"></div>🍔 Burgers</div>
      <div class="cat-chip" onclick="filterCat('pizza',this)"><div class="chip-dot" style="background:#FF6B00"></div>🍕 Pizzas</div>
      <div class="cat-chip" onclick="filterCat('fries',this)"><div class="chip-dot" style="background:#8B4513"></div>🍟 Fries & Sides</div>
      <div class="cat-chip" onclick="filterCat('wrap',this)"><div class="chip-dot" style="background:#C8844A"></div>🌯 Wraps</div>
      <div class="cat-chip" onclick="filterCat('dessert',this)"><div class="chip-dot" style="background:#D4870A"></div>🍨 Desserts</div>
      <div class="cat-chip" onclick="filterCat('drink',this)"><div class="chip-dot" style="background:#1A6B3C"></div>🥤 Drinks</div>
    </div>

    <div class="sb-label">Account</div>
    <div class="sb-link" onclick="<?= $isLoggedIn ? '' : "openModal('loginModal')" ?>"><span class="ico">👤</span><span class="lbl">Profile</span></div>
    <div class="sb-link"><span class="ico">🎁</span><span class="lbl">Rewards</span></div>
    <div class="sb-link"><span class="ico">⚙️</span><span class="lbl">Settings</span></div>
    <div class="sb-link"><span class="ico">🛟</span><span class="lbl">Help & Support</span></div>
    <div class="sb-link" onclick="window.location='agent.php'" style="cursor:pointer;display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:13px;font-weight:700;color:var(--cheese)">
      <span>🤖</span>
      <span>AI Agent</span>
      <span style="background:#22c55e;color:#fff;font-size:9px;padding:2px 7px;border-radius:8px;margin-left:auto">NEW</span>
    </div>
  </div>
  <div class="sb-bottom">
    <button class="dm-row" onclick="toggleDark()">
      <span class="dm-ico">🌙</span>
      <span class="dm-lbl">Dark Mode</span>
      <div class="dm-pill"></div>
    </button>
    <?php if($isLoggedIn): ?>
      <button class="sb-act logout" onclick="doLogout()"><span class="ico">🚪</span>Logout</button>
    <?php else: ?>
      <button class="sb-act login" onclick="openModal('loginModal')"><span class="ico">🔑</span>Login / Sign Up</button>
    <?php endif; ?>
  </div>
</aside>

<!-- MAIN WRAP -->
<div class="main-wrap">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="hamburger" onclick="openSb()">
      <span></span><span></span><span></span>
    </div>
    <div class="tb-search">
      <input type="text" id="searchInput" placeholder="Search burgers, pizzas..." oninput="searchMenu(this.value)">
    </div>
    <div class="tb-icons">
      <div class="tb-ico" onclick="toggleDark()" title="Toggle Dark Mode">🌙</div>
      <div class="tb-ico" onclick="window.location='track.php'" title="Track Order">📍</div>
      <?php if($isLoggedIn): ?>
        <div class="tb-user-pill" onclick="<?= $isAdmin ? "window.location='admin.php'" : '' ?>">
          <div class="tb-pill-av"><?= $userInitials ?></div>
          <span class="tb-pill-nm"><?= htmlspecialchars(explode(' ',$userName)[0]) ?></span>
        </div>
      <?php else: ?>
        <div class="tb-ico" onclick="openModal('loginModal')" title="Login">👤</div>
      <?php endif; ?>
      <div class="tb-cart-wrap">
        <div class="tb-ico" onclick="openCart()" title="Cart">🛒
          <span class="cart-badge" id="cartBadge"><?= $cartCount ?: '0' ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- HERO -->
  <section class="hero" id="home">
    <div class="hero-badge">🔥 Rawalpindi & Islamabad • Free Delivery over Rs.1500</div>
    <h1>Seriously, Dangerously<br><span>Cheesy™</span></h1>
    <p>Fresh burgers, loaded pizzas & crispy sides — delivered hot in 30 mins or it's free!</p>
    <div class="hero-btns">
      <a href="#menu-section" class="hbtn primary">🍔 Order Now</a>
      <a href="track.php" class="hbtn outline">📍 Track Order</a>
    </div>
    <div class="hero-stats">
      <div class="hstat"><div class="hstat-val">4.9⭐</div><div class="hstat-lbl">Avg Rating</div></div>
      <div class="hstat"><div class="hstat-val">30min</div><div class="hstat-lbl">Avg Delivery</div></div>
      <div class="hstat"><div class="hstat-val">5000+</div><div class="hstat-lbl">Happy Customers</div></div>
      <div class="hstat"><div class="hstat-val">17+</div><div class="hstat-lbl">Menu Items</div></div>
    </div>
  </section>

  <!-- DEALS -->
  <section class="section" id="deals">
    <div class="sec-hdr">
      <div>
        <div class="sec-title">🔥 <span>Hot</span> Deals</div>
        <div class="sec-sub">Limited time offers — grab them before they're gone!</div>
      </div>
      <a class="see-all" href="#menu-section">See All →</a>
    </div>
    <div class="deals-scroll">
      <?php foreach($deals as $d): ?>
      <div class="deal-card" style="background:<?= $d['bg'] ?>">
        <span class="deal-badge"><?= $d['badge'] ?></span>
        <span class="deal-em"><?= $d['e'] ?></span>
        <div class="deal-rating">⭐ <?= $d['r'] ?> Rating</div>
        <div class="deal-name"><?= htmlspecialchars($d['name']) ?></div>
        <div class="deal-desc"><?= htmlspecialchars($d['desc']) ?></div>
        <div class="deal-price">
          <span class="d-price">Rs.<?= number_format($d['price']) ?></span>
          <span class="d-old">Rs.<?= number_format($d['old']) ?></span>
          <button class="deal-add" onclick="addToCart(<?= $d['id'] ?>,'<?= addslashes($d['name']) ?>','<?= $d['e'] ?>',<?= $d['price'] ?>)">+</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- PROMO BANNER -->
  <div class="banner">
    <div>📱</div>
    <div class="banner-txt">
      <h2>Order & Track in Real Time</h2>
      <p>Place your order and watch it go from kitchen to your door — live!</p>
    </div>
    <a href="track.php" class="banner-btn">Track My Order →</a>
  </div>

  <!-- MENU SECTION -->
  <section class="menu-section" id="menu-section">
    <div class="sec-hdr">
      <div>
        <div class="sec-title">🍔 Full <span>Menu</span></div>
        <div class="sec-sub">Everything freshly made, always cheesy</div>
      </div>
    </div>
    <div class="menu-grid" id="menuGrid">
      <?php foreach($menu as $idx=>$m): ?>
      <div class="menu-card" data-cat="<?= $m['cat'] ?>" style="animation-delay:<?= $idx*0.04 ?>s">
        <div class="mc-em"><?= $m['emoji'] ?></div>
        <div class="mc-nm"><?= htmlspecialchars($m['name']) ?></div>
        <div class="mc-ds"><?= htmlspecialchars($m['desc']) ?></div>
        <div class="mc-ft">
          <span class="mc-pr">Rs.<?= number_format($m['price']) ?></span>
          <button class="mc-ab <?= isset($cart[$m['id']]) ? 'on' : '' ?>"
                  id="abtn-<?= $m['id'] ?>"
                  onclick="addToCart(<?= $m['id'] ?>,'<?= addslashes($m['name']) ?>','<?= $m['emoji'] ?>',<?= $m['price'] ?>)">
            <?= isset($cart[$m['id']]) ? '✓' : '+' ?>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <footer class="footer">
    🧀 CheesyBurgers — Rawalpindi & Islamabad &nbsp;|&nbsp; Seriously Cheesy™ &nbsp;|&nbsp; © <?= date('Y') ?>
  </footer>
</div><!-- /main-wrap -->

<!-- CHAT FAB -->
<a href="agent.php" class="chat-fab" title="AI Agent se order karo!">
  🧀
  <span class="chat-tooltip">🤖 AI Order Karo!</span>
</a>

<div class="toast" id="toast"></div>

<script>
// ── Cart state ───────────────────────────────────────────────
let cart = <?= json_encode($cart) ?>;

function addToCart(id, name, emoji, price) {
  <?php if(!$isLoggedIn): ?>
    openModal('loginModal');
    showToast('🔑 Please login to add items to cart');
    return;
  <?php endif; ?>
  const fd = new FormData();
  fd.append('action','add'); fd.append('id',id);
  fd.append('name',name); fd.append('emoji',emoji); fd.append('price',price);
  fetch('cart_action.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      if(d.success){
        cart=d.cart; renderCart();
        const b=document.getElementById('abtn-'+id);
        if(b){b.textContent='✓';b.classList.add('on');}
        showToast(emoji+' '+name+' added to cart!');
      }
    });
}

function changeQty(id,delta){
  const fd=new FormData();
  fd.append('action','update');fd.append('id',id);fd.append('delta',delta);
  fetch('cart_action.php',{method:'POST',body:fd})
    .then(r=>r.json()).then(d=>{
      if(d.success){
        cart=d.cart; renderCart();
        const b=document.getElementById('abtn-'+id);
        if(b){if(cart[id]){b.textContent='✓';b.classList.add('on');}else{b.textContent='+';b.classList.remove('on');}}
      }
    });
}

function renderCart(){
  const el=document.getElementById('cdItems');
  const foot=document.getElementById('cdFooter');
  const badge=document.getElementById('cartBadge');
  const sbBadge=document.getElementById('sbCartBadge');
  const countEl=document.getElementById('cartCount');
  const keys=Object.keys(cart);
  if(!keys.length){
    el.innerHTML='<div class="cd-empty"><span class="em">🛒</span>Your cart is empty</div>';
    foot.style.display='none'; badge.textContent='0'; sbBadge.textContent=''; countEl.textContent='';
    return;
  }
  let html='',sub=0,count=0;
  keys.forEach(id=>{
    const i=cart[id]; const line=i.price*i.qty; sub+=line; count+=i.qty;
    html+=`<div class="ci">
      <div class="ci-em">${i.e}</div>
      <div class="ci-info"><div class="ci-name">${i.name}</div><div class="ci-price">Rs.${i.price.toLocaleString()} each</div></div>
      <div class="ci-qty">
        <button class="qty-btn" onclick="changeQty(${id},-1)">−</button>
        <span class="qty-num">${i.qty}</span>
        <button class="qty-btn" onclick="changeQty(${id},1)">+</button>
      </div>
      <div class="ci-line">Rs.${line.toLocaleString()}</div>
    </div>`;
  });
  el.innerHTML=html;
  foot.style.display='block';
  document.getElementById('cdSubtotal').textContent='Rs.'+sub.toLocaleString();
  document.getElementById('cdTotal').textContent='Rs.'+(sub+80).toLocaleString();
  badge.textContent=count; sbBadge.textContent=count; countEl.textContent=`(${count})`;
}
renderCart();

function openCart(){document.getElementById('cartDrawer').classList.add('open');document.getElementById('cartOverlay').classList.add('show');}
function closeCart(){document.getElementById('cartDrawer').classList.remove('open');document.getElementById('cartOverlay').classList.remove('show');}

// ── Category filter ──────────────────────────────────────────
function filterCat(cat,el){
  document.querySelectorAll('.cat-chip').forEach(c=>c.classList.remove('active'));
  if(el)el.classList.add('active');
  document.querySelectorAll('.menu-card').forEach(c=>{
    c.style.display=(cat==='all'||c.dataset.cat===cat)?'':'none';
  });
  document.getElementById('searchInput').value='';
  navTo('menu-section');
}

// ── Search ───────────────────────────────────────────────────
function searchMenu(q){
  q=q.toLowerCase();
  document.querySelectorAll('.menu-card').forEach(c=>{
    const nm=c.querySelector('.mc-nm').textContent.toLowerCase();
    c.style.display=nm.includes(q)?'':'none';
  });
  if(q) document.getElementById('menu-section').scrollIntoView({behavior:'smooth'});
}

// ── Sidebar nav ──────────────────────────────────────────────
function navTo(id,el){
  if(el){document.querySelectorAll('.sb-link').forEach(l=>l.classList.remove('active'));el.classList.add('active');}
  const sec=document.getElementById(id);
  if(sec)sec.scrollIntoView({behavior:'smooth'});
  closeSb();
}
function openSb(){document.getElementById('sidebar').classList.add('open');document.getElementById('mobOverlay').classList.add('show');}
function closeSb(){document.getElementById('sidebar').classList.remove('open');document.getElementById('mobOverlay').classList.remove('show');}

// ── Dark mode ────────────────────────────────────────────────
function toggleDark(){
  const t=document.documentElement.getAttribute('data-theme')==='dark'?'light':'dark';
  document.documentElement.setAttribute('data-theme',t);
  localStorage.setItem('cb_theme',t);
}
(()=>{const t=localStorage.getItem('cb_theme');if(t)document.documentElement.setAttribute('data-theme',t);})();

// ── Auth modals ──────────────────────────────────────────────
function openModal(id){document.getElementById(id).classList.add('show');}
function closeModal(id){document.getElementById(id).classList.remove('show');}
function switchModal(h,s){closeModal(h);openModal(s);}
document.querySelectorAll('.modal').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('show');}));

function doLogin(){
  const e=document.getElementById('loginEmail').value.trim();
  const p=document.getElementById('loginPass').value;
  const err=document.getElementById('loginErr');
  if(!e||!p){showErr(err,'Fill all fields');return;}
  err.classList.remove('show');
  const fd=new FormData(); fd.append('action','login'); fd.append('email',e); fd.append('password',p);
  fetch('auth.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){
      if(d.is_admin) window.location='admin.php';
      else location.reload();
    } else showErr(err,d.error||'Login failed');
  });
}

function doSignup(){
  const err=document.getElementById('signupErr'); err.classList.remove('show');
  const fd=new FormData();
  fd.append('action','signup');
  fd.append('name',   document.getElementById('suName').value.trim());
  fd.append('email',  document.getElementById('suEmail').value.trim());
  fd.append('password',document.getElementById('suPass').value);
  fd.append('phone',  document.getElementById('suPhone').value.trim());
  fd.append('address',document.getElementById('suAddress').value.trim());
  fd.append('city',   document.getElementById('suCity').value);
  if(!fd.get('name')||!fd.get('email')||!fd.get('password')){showErr(err,'Name, email & password required');return;}
  fetch('auth.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success) location.reload();
    else showErr(err,d.error||'Signup failed');
  });
}

function doLogout(){
  const fd=new FormData(); fd.append('action','logout');
  fetch('auth.php',{method:'POST',body:fd}).then(()=>location.reload());
}

function showErr(el,msg){el.textContent=msg;el.classList.add('show');}

// ── Toast ────────────────────────────────────────────────────
function showToast(msg){
  const t=document.getElementById('toast'); t.textContent=msg; t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'),2800);
}

// ── Cursor ───────────────────────────────────────────────────
const cur=document.getElementById('cursor'); const trail=document.getElementById('trailEl');
let tx=0,ty=0,cx=0,cy=0;
document.addEventListener('mousemove',e=>{tx=e.clientX;ty=e.clientY;cur.style.left=tx+'px';cur.style.top=ty+'px';});
(function anim(){cx+=(tx-cx)*.15; cy+=(ty-cy)*.15;
  trail.style.left=cx+'px'; trail.style.top=cy+'px'; requestAnimationFrame(anim);})();

// ── Intro ────────────────────────────────────────────────────
setTimeout(()=>document.getElementById('intro').classList.add('gone'),2200);
</script>
</body>
</html>
