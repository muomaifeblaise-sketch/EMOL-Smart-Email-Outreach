<?php
require_once __DIR__ . '/handlers/auth.php';
Auth::requireAuth('signin.php');
$user = Auth::user(); $db = getDB(); $uid = $user['id'];
if(isset($_GET['logout'])){$a=new Auth();$a->logout();header('Location: index.html?from=logout');exit();}

$r=$db->prepare("SELECT COUNT(*) as c FROM campaigns WHERE user_id=?");$r->bind_param('i',$uid);$r->execute();$stat_campaigns=$r->get_result()->fetch_assoc()['c'];
$r=$db->prepare("SELECT COALESCE(SUM(emails_sent),0) as c FROM campaigns WHERE user_id=?");$r->bind_param('i',$uid);$r->execute();$stat_sent=$r->get_result()->fetch_assoc()['c'];
$r=$db->prepare("SELECT COALESCE(SUM(emails_opened),0) as c FROM campaigns WHERE user_id=?");$r->bind_param('i',$uid);$r->execute();$stat_opened=$r->get_result()->fetch_assoc()['c'];
$r=$db->prepare("SELECT COALESCE(SUM(replies),0) as c FROM campaigns WHERE user_id=?");$r->bind_param('i',$uid);$r->execute();$stat_replies=$r->get_result()->fetch_assoc()['c'];
$r=$db->prepare("SELECT COUNT(*) as c FROM contacts WHERE user_id=?");$r->bind_param('i',$uid);$r->execute();$stat_contacts=$r->get_result()->fetch_assoc()['c'];
$open_rate=$stat_sent>0?round(($stat_opened/$stat_sent)*100,1):0;
$reply_rate=$stat_sent>0?round(($stat_replies/$stat_sent)*100,1):0;
$r=$db->prepare("SELECT * FROM campaigns WHERE user_id=? ORDER BY created_at DESC LIMIT 4");$r->bind_param('i',$uid);$r->execute();$recent=$r->get_result()->fetch_all(MYSQLI_ASSOC);
$r=$db->prepare("SELECT * FROM contacts WHERE user_id=? ORDER BY created_at DESC LIMIT 4");$r->bind_param('i',$uid);$r->execute();$recent_contacts=$r->get_result()->fetch_all(MYSQLI_ASSOC);
$analytics=[];for($i=6;$i>=0;$i--){$date=date('Y-m-d',strtotime("-$i days"));$r=$db->prepare("SELECT COALESCE(SUM(emails_sent),0) as s,COALESCE(SUM(emails_opened),0) as o FROM campaigns WHERE user_id=? AND DATE(created_at)=?");$r->bind_param('is',$uid,$date);$r->execute();$row=$r->get_result()->fetch_assoc();$analytics[]=['day'=>date('D',strtotime($date)),'sent'=>(int)$row['s'],'opened'=>(int)$row['o']];}
$max_val=max(array_merge(array_column($analytics,'sent'),array_column($analytics,'opened'),[1]));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>EMOL — Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
<style>
:root{
  --bg:#0f0f0f;--surface:#181818;--surface2:#222;--surface3:#2a2a2a;
  --green:#00e5a0;--green-dim:rgba(0,229,160,.1);--green-glow:rgba(0,229,160,.18);
  --blue:#3b82f6;--accent3:#ff6b35;--accent4:#a855f7;
  --text:#efefef;--muted:#777;--muted2:#444;
  --border:rgba(255,255,255,.07);--border2:rgba(255,255,255,.12);
  --success:#22c55e;--danger:#ef4444;--warning:#f59e0b;
  --sb-w:224px;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:var(--bg);font-family:'DM Sans',sans-serif;color:var(--text);overflow:hidden;scrollbar-width:none;-ms-overflow-style:none}
html::-webkit-scrollbar,body::-webkit-scrollbar{display:none;width:0}
.layout{display:flex;height:100vh}@media(max-width:768px){html,body{overflow:auto!important;height:auto!important}.layout{height:auto;min-height:100vh;flex-direction:column}.main-wrap{overflow-y:visible!important;height:auto!important}.content{padding-bottom:80px!important}}

/* ── SIDEBAR ── */
.sidebar{width:var(--sb-w);min-width:var(--sb-w);background:var(--surface);display:flex;flex-direction:column;padding:18px 10px 14px;z-index:100;transition:transform .3s;border-right:1px solid var(--border);overflow:hidden}
.sb-logo{display:flex;align-items:center;gap:9px;padding:4px 8px 14px;border-bottom:1px solid var(--border);margin-bottom:10px}
.sb-logo-icon{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--green),var(--blue));display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sb-logo-name{font-family:'Nunito',sans-serif;font-weight:900;font-size:1.15rem;background:linear-gradient(135deg,var(--green),var(--blue));-webkit-background-clip:text;background-clip:text;color:transparent}
.sb-search{display:flex;align-items:center;gap:7px;background:var(--surface2);border-radius:9px;padding:8px 11px;margin-bottom:10px;border:1px solid var(--border)}
.sb-search svg{flex-shrink:0;opacity:.5}
.sb-search input{background:none;border:none;outline:none;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.78rem;width:100%}
.sb-search input::placeholder{color:var(--muted2)}
.sb-nav{flex:1;display:flex;flex-direction:column;gap:1px;overflow-y:auto;scrollbar-width:none;-ms-overflow-style:none}
.sb-nav::-webkit-scrollbar{display:none;width:0}
.sb-section{font-size:.58rem;font-weight:700;letter-spacing:1.2px;text-transform:uppercase;color:var(--muted2);padding:10px 8px 4px}
.sb-link{display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:9px;color:var(--muted);font-size:.8rem;font-weight:500;cursor:pointer;transition:all .15s;background:transparent;border:none;width:100%;text-align:left;position:relative;text-decoration:none}
.sb-link:hover{background:var(--surface2);color:var(--text)}
.sb-link.active{background:var(--green-dim);border:1px solid rgba(0,229,160,.18);color:var(--green);font-weight:700}
.sb-link.active::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:55%;background:var(--green);border-radius:0 3px 3px 0}
.sb-link svg{width:16px;height:16px;flex-shrink:0;opacity:.7}
.sb-link.active svg{opacity:1}
.sb-badge{margin-left:auto;font-size:.54rem;font-weight:700;padding:2px 6px;border-radius:20px;background:rgba(255,107,53,.15);color:var(--accent3)}
.sb-badge.green{background:var(--green-dim);color:var(--green)}
.sb-badge.blue{background:rgba(59,130,246,.15);color:var(--blue)}
.sb-bottom{border-top:1px solid var(--border);padding-top:10px;margin-top:6px}
.sb-user{display:flex;align-items:center;gap:8px;padding:9px 10px;border-radius:10px;background:var(--surface2);margin-bottom:7px;border:1px solid var(--border)}
.sb-avatar{width:34px;height:34px;border-radius:9px;flex-shrink:0;background:linear-gradient(135deg,var(--green),var(--blue));display:flex;align-items:center;justify-content:center;font-family:'Nunito',sans-serif;font-weight:900;font-size:.8rem;color:#000;box-shadow:0 0 0 3px var(--green-glow)}
.sb-user-name{font-size:.78rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sb-user-role{font-size:.62rem;color:#000;font-weight:700;background:var(--green);padding:1px 6px;border-radius:20px;display:inline-block;margin-top:2px}
.logout-btn{display:flex;align-items:center;gap:8px;width:100%;padding:9px 12px;border-radius:9px;background:transparent;border:1px solid rgba(255,255,255,.07);color:var(--muted);font-size:.78rem;font-weight:600;cursor:pointer;transition:all .18s;font-family:'DM Sans',sans-serif;text-decoration:none}
.logout-btn:hover{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.25);color:#ef4444;transform:translateY(-1px)}

/* ── MAIN ── */
.main-wrap{flex:1;display:flex;flex-direction:column;overflow-y:auto;min-width:0;scrollbar-width:none;-ms-overflow-style:none}
.main-wrap::-webkit-scrollbar{display:none;width:0}
.content{flex:1;overflow:visible;padding:14px 16px;display:flex;flex-direction:column;gap:12px}.topbar{height:58px;min-height:58px;display:flex;align-items:center;justify-content:space-between;padding:0 16px;border-bottom:1px solid var(--border);background:var(--surface);gap:8px}
.topbar-left{display:flex;align-items:center;gap:10px;min-width:0}
.hamburger{display:none;background:transparent;border:none;color:var(--text);font-size:1.2rem;cursor:pointer;flex-shrink:0}
.greeting{font-family:'Nunito',sans-serif;font-weight:800;font-size:.92rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.greeting span{color:var(--green)}
.topbar-center{display:flex;align-items:center;gap:6px;flex-shrink:0}
.tb-pill{display:flex;align-items:center;gap:5px;padding:6px 12px;border-radius:999px;background:var(--surface2);border:1px solid var(--border);color:var(--muted);font-size:.72rem;font-weight:600;cursor:pointer;transition:all .15s;text-decoration:none;white-space:nowrap}
.tb-pill:hover{border-color:var(--green);color:var(--green)}
.tb-pill.active{background:var(--green-dim);border-color:rgba(0,229,160,.3);color:var(--green)}
.topbar-right{display:flex;align-items:center;gap:6px;flex-shrink:0}
.tb-icon{width:32px;height:32px;border-radius:9px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--muted);cursor:pointer;transition:all .15s;text-decoration:none;position:relative;flex-shrink:0}
.tb-icon:hover{color:var(--green);border-color:rgba(0,229,160,.3)}
.notif-dot{position:absolute;top:6px;right:6px;width:6px;height:6px;border-radius:50%;background:var(--green);border:2px solid var(--surface)}
.tb-btn{display:flex;align-items:center;gap:5px;padding:7px 13px;border-radius:9px;background:var(--green);border:none;color:#000;font-weight:700;font-size:.75rem;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s;text-decoration:none;white-space:nowrap}
.tb-btn:hover{transform:translateY(-1px);box-shadow:0 5px 18px rgba(0,229,160,.25);color:#000}

/* ── CONTENT ── */


/* ── CARDS ── */
.card{background:var(--surface);border-radius:16px;border:1px solid var(--border);overflow:hidden}
.cp{padding:16px}
.card-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.card-title{font-family:'Nunito',sans-serif;font-weight:800;font-size:.86rem}
.card-sub{font-size:.68rem;color:var(--muted);margin-top:2px}

/* ── GRID ── */
.row1{display:grid;grid-template-columns:1fr 1.15fr 1.1fr;gap:12px}
.row2{display:grid;grid-template-columns:1.4fr 1fr 0.85fr;gap:12px}

/* Campaign progress */
.big-num{font-family:'Nunito',sans-serif;font-weight:900;font-size:2.6rem;line-height:1;color:var(--green)}
.big-num sup{font-size:1rem;vertical-align:top;margin-top:6px;display:inline-block}
.big-label{font-size:.66rem;color:var(--muted);margin-bottom:3px;text-transform:uppercase;letter-spacing:.5px}
.big-date{font-size:.68rem;color:var(--green);margin-top:3px}
.mini-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 13px;border-radius:999px;background:var(--surface2);border:1px solid var(--border);color:var(--text);font-size:.74rem;font-weight:600;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif;text-decoration:none}
.mini-btn:hover{background:var(--green-dim);border-color:rgba(0,229,160,.3);color:var(--green)}
.mini-btn.primary{background:var(--green);color:#000;border-color:var(--green)}
.mini-btn.primary:hover{box-shadow:0 4px 14px rgba(0,229,160,.3);transform:translateY(-1px);color:#000}
.active-camp{background:var(--surface2);border-radius:11px;padding:11px;margin-top:12px;border:1px solid var(--border)}
.ac-label{font-size:.6rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px}
.ac-name{font-weight:700;font-size:.84rem;margin-bottom:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ac-sub{font-size:.68rem;color:var(--muted)}
.prog-wrap{height:4px;background:var(--surface3);border-radius:99px;overflow:hidden;margin:7px 0 3px}
.prog-bar{height:100%;background:linear-gradient(90deg,var(--green),var(--blue));border-radius:99px;transition:width .8s ease}

/* Perf card */
.big-perfs{display:flex;gap:14px;margin-bottom:12px;align-items:flex-end;flex-wrap:wrap}
.perf-num{font-family:'Nunito',sans-serif;font-weight:900;font-size:1.7rem;color:var(--muted2);line-height:1}
.perf-num.highlight{font-size:2rem;color:var(--text);background:var(--surface2);padding:5px 11px;border-radius:10px;border:2px solid var(--green)}
.perf-num sup{font-size:.75rem}
.chart-area{position:relative;height:70px}
svg.lc{width:100%;height:100%}

/* Stat minis */
.stats-grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.stat-mini{background:var(--surface);border:1px solid var(--border);border-radius:13px;padding:13px;transition:border-color .2s,transform .2s;cursor:default}
.stat-mini:hover{border-color:rgba(0,229,160,.2);transform:translateY(-2px)}
.sm-label{font-size:.63rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.sm-val{font-family:'Nunito',sans-serif;font-weight:900;font-size:1.6rem;color:var(--green);line-height:1}
.sm-val.blue{color:var(--blue)}.sm-val.orange{color:var(--accent3)}.sm-val.white{color:var(--text)}
.sm-delta{font-size:.63rem;color:var(--muted);margin-top:3px}

/* Contacts */
.contact-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)}
.contact-row:last-child{border-bottom:none}
.contact-av{width:28px;height:28px;border-radius:7px;background:var(--surface3);display:flex;align-items:center;justify-content:center;font-size:.66rem;font-weight:700;color:var(--muted);flex-shrink:0;border:1px solid var(--border)}
.sp{font-size:.6rem;font-weight:700;padding:3px 7px;border-radius:999px}
.sp-green{background:var(--green-dim);color:var(--green)}
.sp-blue{background:rgba(59,130,246,.1);color:var(--blue)}
.sp-gray{background:var(--surface3);color:var(--muted)}
.bdg{display:inline-flex;align-items:center;padding:2px 7px;border-radius:20px;font-size:.64rem;font-weight:700}
.bdg-s{background:rgba(34,197,94,.1);color:#4ade80}
.bdg-w{background:rgba(245,158,11,.1);color:#fbbf24}
.bdg-g{background:rgba(148,163,184,.1);color:#94a3b8}
.bdg-i{background:rgba(59,130,246,.1);color:#60a5fa}
.empty-st{text-align:center;padding:28px 16px;color:var(--muted2)}
.empty-st svg{margin-bottom:8px;opacity:.3}
.empty-st p{font-size:.76rem;margin-bottom:10px}

/* ── MOBILE ── */
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:99}
.sb-overlay.show{display:block}

/* ── LOGOUT MODAL ── */
.logout-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);z-index:10000;display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s ease}
.logout-modal-overlay.show{opacity:1;pointer-events:auto}
.logout-modal{background:var(--surface);border:1px solid var(--border2);border-radius:16px;padding:28px 28px 24px;width:100%;max-width:340px;position:relative;transform:translateY(16px) scale(.97);transition:transform .25s cubic-bezier(.34,1.56,.64,1),opacity .2s ease;opacity:0;box-shadow:0 32px 80px rgba(0,0,0,.6)}
.logout-modal-overlay.show .logout-modal{transform:translateY(0) scale(1);opacity:1}
.logout-modal::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(239,68,68,.4),transparent);border-radius:16px 16px 0 0}
.lm-icon{width:44px;height:44px;border-radius:12px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);display:flex;align-items:center;justify-content:center;margin-bottom:14px}
.lm-title{font-family:'Nunito',sans-serif;font-weight:800;font-size:1rem;color:var(--text);margin-bottom:6px}
.lm-desc{font-size:.78rem;color:var(--muted);line-height:1.55;margin-bottom:18px}
.lm-busy{display:none;align-items:center;gap:9px;background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.2);border-radius:10px;padding:10px 12px;margin-bottom:16px;font-size:.75rem;color:#fbbf24}
.lm-busy.show{display:flex}
.lm-spinner{width:14px;height:14px;border:2px solid rgba(245,158,11,.3);border-top-color:#f59e0b;border-radius:50%;animation:lmspin .7s linear infinite;flex-shrink:0}
@keyframes lmspin{to{transform:rotate(360deg)}}
.lm-actions{display:flex;gap:9px}
.lm-cancel{flex:1;padding:10px;border-radius:10px;background:var(--surface2);border:1px solid var(--border2);color:var(--muted);font-size:.8rem;font-weight:600;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif}
.lm-cancel:hover{color:var(--text);border-color:var(--border2)}
.lm-confirm{flex:1;padding:10px;border-radius:10px;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#f87171;font-size:.8rem;font-weight:700;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif}
.lm-confirm:hover{background:rgba(239,68,68,.2);border-color:rgba(239,68,68,.5);color:#fca5a5}
.lm-confirm:disabled{opacity:.5;cursor:not-allowed}

@media(max-width:1024px){
  .row1{grid-template-columns:1fr 1fr}
  .row2{grid-template-columns:1fr 1fr}
}
@media(max-width:768px){
  .hamburger{display:block}
  .sidebar{position:fixed;top:0;left:0;height:100%;transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .topbar-center{display:none}
  .row1,.row2{grid-template-columns:1fr}
  .content{padding:12px}
  .big-num{font-size:2rem}
  .perf-num.highlight{font-size:1.6rem}
  .stats-grid2{grid-template-columns:1fr 1fr}
}
@media(max-width:480px){
  .greeting{font-size:.82rem}
  .tb-pill{display:none}
  .stats-grid2{grid-template-columns:1fr 1fr}
  .content{padding:10px}
  .cp{padding:12px}
}

@keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.fade-in{animation:fadeUp .4s ease}
</style>
</head>
<body>
<div class="sb-overlay" id="overlay" onclick="closeSb()"></div>

<!-- ══ LOGOUT MODAL ══ -->
<div class="logout-modal-overlay" id="logoutOverlay" onclick="closeLogoutModal(event)">
  <div class="logout-modal" id="logoutModal">
    <div class="lm-icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </div>
    <div class="lm-title">Sign out of EMOL?</div>
    <div class="lm-desc" id="lmDesc">You'll be returned to the sign in page. Any unsaved changes will be lost.</div>
    <div class="lm-busy" id="lmBusy">
      <div class="lm-spinner"></div>
      <span id="lmBusyText">Something is running in the background.</span>
    </div>
    <div class="lm-actions">
      <button class="lm-cancel" onclick="closeLogoutModal()">Cancel</button>
      <button class="lm-confirm" id="lmConfirmBtn" onclick="doLogout()">Sign Out</button>
    </div>
  </div>
</div>
<div class="layout">

<!-- SIDEBAR -->
<nav class="sidebar" id="sidebar">
  <div class="sb-logo">
    <div class="sb-logo-icon">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </div>
    <div class="sb-logo-name">EMOL</div>
  </div>

  <!-- Search — no microphone -->
  <div class="sb-search">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" placeholder="Search..." oninput="sbSearch(this.value)"/>
  </div>

  <div class="sb-nav">
    <a class="sb-link active" href="dashboard.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Home
    </a>
    <a class="sb-link" href="app.php?page=campaign">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>Campaigns
    </a>
    <a class="sb-link" href="app.php?page=analytics">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>Analytics
    </a>
    <a class="sb-link" href="app.php?page=contacts">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Contacts
    </a>
    <a class="sb-link" href="app.php?page=aiwriter">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--green)"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>AI Writer<span class="sb-badge green">AI</span>
    </a>

    <div class="sb-section">Tools</div>
    <a class="sb-link" href="app.php?page=noweb">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent3)"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>No-Website Hunt<span class="sb-badge">HOT</span>
    </a>
    <a class="sb-link" href="app.php?page=abtest">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>A/B Testing<span class="sb-badge blue">NEW</span>
    </a>
    <a class="sb-link" href="app.php?page=templates">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>Templates
    </a>
    <a class="sb-link" href="app.php?page=followup">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>Follow-up
    </a>

    <div class="sb-section">Account</div>
    <a class="sb-link" href="app.php?page=history">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>History
    </a>
    <a class="sb-link" href="app.php?page=settings">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Settings
    </a>
  </div>

  <div class="sb-bottom">
    <div class="sb-user">
      <div class="sb-avatar"><?= htmlspecialchars($user['initials']??'U') ?></div>
      <div style="flex:1;min-width:0">
        <div class="sb-user-name"><?= htmlspecialchars($user['firstname']) ?></div>
        <span class="sb-user-role"><?= ucfirst($user['role']) ?></span>
      </div>
      <a href="app.php?page=settings" style="color:var(--muted);padding:4px;text-decoration:none;transition:color .15s;flex-shrink:0">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      </a>
    </div>
    <button class="logout-btn" onclick="openLogoutModal()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Sign Out
    </button>
  </div>
</nav>

<!-- MAIN -->
<div class="main-wrap">
  <div class="topbar">
    <div class="topbar-left">
      <button class="hamburger" onclick="toggleSb()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="greeting" id="greetingEl">&nbsp;<span><?= htmlspecialchars($user['firstname']) ?></span></div>
    </div>
    <div class="topbar-center">
      <a href="app.php?page=campaign" class="tb-pill active">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>Campaign
      </a>
      <a href="app.php?page=noweb" class="tb-pill">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>Hunt Leads
      </a>
      <a href="app.php?page=aiwriter" class="tb-pill">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>AI Writer
      </a>
    </div>
    <div class="topbar-right">
      <a href="app.php?page=analytics" class="tb-icon" title="Analytics">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      </a>
      <a href="app.php?page=contacts" class="tb-icon" title="Contacts">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </a>
      <div class="tb-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><span class="notif-dot"></span></div>
      <a href="app.php?page=campaign" class="tb-btn">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Campaign
      </a>
    </div>
  </div>

  <div class="content fade-in">
    <!-- ROW 1 -->
    <div class="row1">
      <!-- Campaign Progress -->
      <div class="card cp">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px">
          <div>
            <span style="font-family:'Nunito',sans-serif;font-weight:800;font-size:.92rem">Campaign Progress</span>
            <span style="font-size:.7rem;color:var(--muted);background:var(--surface2);padding:2px 7px;border-radius:6px;margin-left:6px"><?= $stat_campaigns ?></span>
          </div>
          <a href="app.php?page=history" style="font-size:.68rem;color:var(--muted);text-decoration:none;background:var(--surface2);padding:5px 8px;border-radius:8px;border:1px solid var(--border);display:flex;align-items:center;gap:4px">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>All
          </a>
        </div>
        <div style="margin-bottom:12px">
          <div class="big-label">Open Rate</div>
          <div class="big-num"><?= $open_rate ?><sup>%</sup></div>
          <div class="big-date"><?= date('d M Y') ?></div>
        </div>
        <div style="display:flex;gap:7px;margin-top:12px">
          <a href="app.php?page=campaign" class="mini-btn primary">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>New Campaign
          </a>
          <a href="app.php?page=contacts" class="mini-btn">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </a>
        </div>
        <?php if(!empty($recent)): $c=$recent[0]; $prog=min(100,$stat_sent>0?round(($c['emails_sent']/$stat_sent)*100):0); ?>
        <div class="active-camp">
          <div class="ac-label">Latest Campaign</div>
          <div class="ac-name"><?= htmlspecialchars($c['name']) ?></div>
          <div class="ac-sub"><?= htmlspecialchars($c['category']??'—') ?> · <?= $c['emails_sent'] ?> sent</div>
          <div class="prog-wrap"><div class="prog-bar" style="width:<?= $prog ?>%"></div></div>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:.68rem;color:var(--muted)"><?= $c['emails_sent'] ?>/<?= $stat_sent ?> emails</span>
            <span class="bdg <?= ['sent'=>'bdg-s','draft'=>'bdg-g','partial'=>'bdg-w'][$c['status']]??'bdg-g' ?>"><?= ucfirst($c['status']) ?></span>
          </div>
        </div>
        <?php else: ?>
        <div class="active-camp" style="text-align:center">
          <div style="font-size:.74rem;color:var(--muted)">No campaigns yet</div>
          <a href="app.php?page=campaign" style="font-size:.7rem;color:var(--green);text-decoration:none;margin-top:4px;display:block">Create your first →</a>
        </div>
        <?php endif; ?>
      </div>

      <!-- Recent Campaigns -->
      <div class="card cp">
        <div class="card-hd">
          <div><div class="card-title">Recent Campaigns</div><div class="card-sub">Your latest outreach</div></div>
          <a href="app.php?page=history" style="width:26px;height:26px;border-radius:7px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--muted);text-decoration:none">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7"/><path d="M7 7h10v10"/></svg>
          </a>
        </div>
        <?php if(empty($recent)): ?>
        <div class="empty-st">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          <p>No campaigns yet</p><a href="app.php?page=campaign" class="mini-btn primary" style="margin:0 auto">Start one</a>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:6px">
          <?php foreach($recent as $c):
            $cls=['sent'=>'bdg-s','draft'=>'bdg-g','partial'=>'bdg-w','scheduled'=>'bdg-i'][$c['status']]??'bdg-g';
          ?>
          <div style="display:flex;align-items:center;gap:9px;padding:8px;background:var(--surface2);border-radius:10px;border:1px solid var(--border)">
            <div style="width:28px;height:28px;border-radius:7px;background:var(--green-dim);display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </div>
            <div style="flex:1;min-width:0">
              <div style="font-size:.78rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($c['name']) ?></div>
              <div style="font-size:.64rem;color:var(--muted)"><?= htmlspecialchars($c['category']??'—') ?> · <?= $c['emails_sent'] ?> sent</div>
            </div>
            <span class="bdg <?= $cls ?>"><?= ucfirst($c['status']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <a href="app.php?page=history" class="mini-btn" style="margin-top:9px;width:100%;justify-content:center">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>View All History
        </a>
        <?php endif; ?>
      </div>

      <!-- Performance Chart -->
      <div class="card cp">
        <div class="card-hd">
          <div><div class="card-title">Performance</div><div class="card-sub">Last 7 days</div></div>
          <span style="font-size:.68rem;color:var(--green);background:var(--green-dim);padding:3px 8px;border-radius:6px;font-weight:700">Weekly</span>
        </div>
        <div class="big-perfs">
          <div><div style="font-size:.6rem;color:var(--muted);margin-bottom:3px">SENT</div><div class="perf-num"><?= $stat_sent ?></div></div>
          <div><div style="font-size:.6rem;color:var(--muted);margin-bottom:3px">OPEN RATE</div><div class="perf-num highlight"><?= $open_rate ?><sup>%</sup></div></div>
          <div><div style="font-size:.6rem;color:var(--muted);margin-bottom:3px">REPLIES</div><div class="perf-num"><?= $reply_rate ?>%</div></div>
        </div>
        <div class="chart-area">
          <svg class="lc" viewBox="0 0 260 70" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <linearGradient id="g1" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#00e5a0" stop-opacity=".25"/><stop offset="100%" stop-color="#00e5a0" stop-opacity="0"/></linearGradient>
              <linearGradient id="g2" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#3b82f6" stop-opacity=".18"/><stop offset="100%" stop-color="#3b82f6" stop-opacity="0"/></linearGradient>
            </defs>
            <?php
            $n=count($analytics);$step=260/max($n-1,1);
            $ps='';$po='';$as='';$ao='';
            foreach($analytics as $i=>$d){
              $x=round($i*$step);
              $ys=round(65-($d['sent']/$max_val)*55);
              $yo=round(65-($d['opened']/$max_val)*55);
              $ps.="$x,$ys ";$po.="$x,$yo ";
              if($i===0){$as="M$x,$ys";$ao="M$x,$yo";}
              else{$as.=" L$x,$ys";$ao.=" L$x,$yo";}
            }
            $lx=round(($n-1)*$step);
            $as.=" L{$lx},70 L0,70 Z";$ao.=" L{$lx},70 L0,70 Z";
            ?>
            <path d="<?= $as ?>" fill="url(#g1)"/>
            <path d="<?= $ao ?>" fill="url(#g2)"/>
            <polyline points="<?= $ps ?>" fill="none" stroke="#00e5a0" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <polyline points="<?= $po ?>" fill="none" stroke="#3b82f6" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="4 3"/>
          </svg>
        </div>
        <div style="display:flex;gap:12px;margin-top:7px">
          <span style="font-size:.64rem;color:var(--muted);display:flex;align-items:center;gap:4px"><span style="width:7px;height:7px;border-radius:50%;background:var(--green);display:inline-block"></span>Sent</span>
          <span style="font-size:.64rem;color:var(--muted);display:flex;align-items:center;gap:4px"><span style="width:7px;height:7px;border-radius:50%;background:var(--blue);display:inline-block"></span>Opened</span>
        </div>
      </div>
    </div>

    <!-- ROW 2 -->
    <div class="row2">
      <!-- Contacts -->
      <div class="card cp">
        <div class="card-hd">
          <div><div class="card-title">Recent Contacts</div><div class="card-sub"><?= $stat_contacts ?> total contacts</div></div>
          <div style="display:flex;gap:6px">
            <a href="app.php?page=contacts" style="font-size:.68rem;color:var(--muted);text-decoration:none;background:var(--surface2);padding:5px 8px;border-radius:8px;border:1px solid var(--border)">View all</a>
            <a href="app.php?page=contacts" style="font-size:.68rem;color:#000;text-decoration:none;background:var(--green);padding:5px 8px;border-radius:8px;font-weight:700">+ Add</a>
          </div>
        </div>
        <?php if(empty($recent_contacts)): ?>
        <div class="empty-st">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <p>No contacts yet</p>
        </div>
        <?php else: ?>
        <?php foreach($recent_contacts as $c):
          $init=strtoupper(substr($c['contact_name']??$c['business_name']??'?',0,1));
        ?>
        <div class="contact-row">
          <div style="display:flex;align-items:center;gap:9px">
            <div class="contact-av"><?= $init ?></div>
            <div>
              <div style="font-size:.78rem;font-weight:600"><?= htmlspecialchars($c['business_name']??$c['contact_name']??'—') ?></div>
              <div style="font-size:.66rem;color:var(--muted)"><?= htmlspecialchars($c['category']??'—') ?> · <?= htmlspecialchars($c['location']??'—') ?></div>
            </div>
          </div>
          <span class="sp sp-blue"><?= htmlspecialchars(substr($c['category']??'Contact',0,10)) ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Quick Stats -->
      <div class="stats-grid2">
        <div class="stat-mini"><div class="sm-label">Emails Sent</div><div class="sm-val"><?= number_format($stat_sent) ?></div><div class="sm-delta">Total delivered</div></div>
        <div class="stat-mini"><div class="sm-label">Contacts</div><div class="sm-val blue"><?= number_format($stat_contacts) ?></div><div class="sm-delta">In database</div></div>
        <div class="stat-mini"><div class="sm-label">Replies</div><div class="sm-val white"><?= number_format($stat_replies) ?></div><div class="sm-delta"><?= $reply_rate ?>% reply rate</div></div>
        <div class="stat-mini" style="cursor:pointer" onclick="location.href='app.php?page=noweb'"><div class="sm-label">No-Web Hunt</div><div class="sm-val orange">Hunt</div><div class="sm-delta" style="color:var(--accent3)">→ Find leads</div></div>
      </div>

      <!-- Progress Ring -->
      <div class="card cp" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center">
        <div style="font-size:.64rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">Overall Progress</div>
        <svg width="100" height="100" viewBox="0 0 105 105">
          <circle cx="52.5" cy="52.5" r="44" fill="none" stroke="var(--surface3)" stroke-width="9"/>
          <?php $circ=2*pi()*44;$dash=min($open_rate,100)/100*$circ; ?>
          <circle cx="52.5" cy="52.5" r="44" fill="none" stroke="url(#rg)" stroke-width="9" stroke-linecap="round" stroke-dasharray="<?= round($dash,1) ?> <?= round($circ,1) ?>" transform="rotate(-90 52.5 52.5)"/>
          <defs><linearGradient id="rg" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" stop-color="#00e5a0"/><stop offset="100%" stop-color="#3b82f6"/></linearGradient></defs>
          <text x="52.5" y="48" text-anchor="middle" font-family="Nunito" font-weight="900" font-size="17" fill="#efefef"><?= $open_rate ?>%</text>
          <text x="52.5" y="62" text-anchor="middle" font-family="DM Sans" font-size="8" fill="#777">open rate</text>
        </svg>
        <div style="font-size:.68rem;color:var(--muted);margin-top:8px"><?= $stat_campaigns ?> campaign<?= $stat_campaigns!=1?'s':'' ?></div>
        <div style="font-size:.66rem;color:var(--muted2);margin-top:2px"><?= $stat_sent ?> emails sent</div>
        <a href="app.php?page=analytics" class="mini-btn" style="margin-top:10px;width:100%;justify-content:center">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>Full Analytics
        </a>
      </div>
    </div>
  </div>
</div>
</div>

<script>
function toggleSb(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('overlay').classList.toggle('show');}
function closeSb(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
function sbSearch(v){document.querySelectorAll('.sb-link').forEach(el=>{el.style.display=v?el.textContent.toLowerCase().includes(v.toLowerCase())?'':'none':''});}

// ── LOGOUT MODAL ───────────────────────────────────────────
// Dashboard has no background async tasks, but flag is kept
// extensible in case future features add them
// ── SET GREETING FROM USER'S LOCAL TIME ────────────────────
(function(){
  const h = new Date().getHours();
  const word = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
  const el = document.getElementById('greetingEl');
  if(el){
    const name = el.querySelector('span');
    el.childNodes[0].textContent = word + ', ';
    // re-attach the span in case browser split the text node
    if(name) el.appendChild(name);
  }
})();

function isBusy(){ return false; }

function openLogoutModal(){
  const overlay=document.getElementById('logoutOverlay');
  const busyEl=document.getElementById('lmBusy');
  const btn=document.getElementById('lmConfirmBtn');
  const desc=document.getElementById('lmDesc');
  busyEl.classList.remove('show');
  btn.disabled=false;
  btn.textContent='Sign Out';
  desc.textContent="You'll be taken back to the home page. Any unsaved changes will be lost.";
  overlay.classList.add('show');
}

function closeLogoutModal(e){
  if(e&&e.target!==document.getElementById('logoutOverlay'))return;
  document.getElementById('logoutOverlay').classList.remove('show');
}

function doLogout(){
  window.location.href='dashboard.php?logout=1';
}
</script>
</body>
</html>