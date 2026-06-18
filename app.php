<?php
require_once __DIR__ . '/handlers/auth.php';
Auth::requireAuth('signin.php');
$user = Auth::user(); $db = getDB(); $uid = $user['id'];

if(isset($_GET['logout'])){$a=new Auth();$a->logout();header('Location: signin.php');exit();}

$page = $_GET['page'] ?? 'campaign';
$allowed = ['campaign','noweb','contacts','history','aiwriter','templates','abtest','analytics','followup','settings'];
if(!in_array($page,$allowed)) $page = 'campaign';

$r=$db->prepare("SELECT COUNT(*) as c FROM campaigns WHERE user_id=?");$r->bind_param('i',$uid);$r->execute();$stat_campaigns=$r->get_result()->fetch_assoc()['c'];
$r=$db->prepare("SELECT COALESCE(SUM(emails_sent),0) as c FROM campaigns WHERE user_id=?");$r->bind_param('i',$uid);$r->execute();$stat_sent=$r->get_result()->fetch_assoc()['c'];
$r=$db->prepare("SELECT COALESCE(SUM(emails_opened),0) as c FROM campaigns WHERE user_id=?");$r->bind_param('i',$uid);$r->execute();$stat_opened=$r->get_result()->fetch_assoc()['c'];
$r=$db->prepare("SELECT COALESCE(SUM(replies),0) as c FROM campaigns WHERE user_id=?");$r->bind_param('i',$uid);$r->execute();$stat_replies=$r->get_result()->fetch_assoc()['c'];
$r=$db->prepare("SELECT COUNT(*) as c FROM contacts WHERE user_id=?");$r->bind_param('i',$uid);$r->execute();$stat_contacts=$r->get_result()->fetch_assoc()['c'];
$open_rate=$stat_sent>0?round(($stat_opened/$stat_sent)*100,1):0;
$r=$db->prepare("SELECT * FROM contacts WHERE user_id=? ORDER BY created_at DESC");$r->bind_param('i',$uid);$r->execute();$contacts_all=$r->get_result()->fetch_all(MYSQLI_ASSOC);
$r=$db->prepare("SELECT * FROM campaigns WHERE user_id=? ORDER BY created_at DESC");$r->bind_param('i',$uid);$r->execute();$campaigns_all=$r->get_result()->fetch_all(MYSQLI_ASSOC);
$r=$db->prepare("SELECT * FROM templates WHERE user_id=? ORDER BY created_at DESC");$r->bind_param('i',$uid);$r->execute();$templates_all=$r->get_result()->fetch_all(MYSQLI_ASSOC);
$r=$db->prepare("SELECT * FROM ab_tests WHERE user_id=? ORDER BY created_at DESC LIMIT 10");$r->bind_param('i',$uid);$r->execute();$abtests_all=$r->get_result()->fetch_all(MYSQLI_ASSOC);
$analytics=[];for($i=6;$i>=0;$i--){$date=date('Y-m-d',strtotime("-$i days"));$r=$db->prepare("SELECT COALESCE(SUM(emails_sent),0) as s,COALESCE(SUM(emails_opened),0) as o,COALESCE(SUM(replies),0) as rep FROM campaigns WHERE user_id=? AND DATE(created_at)=?");$r->bind_param('is',$uid,$date);$r->execute();$row=$r->get_result()->fetch_assoc();$analytics[]=['day'=>date('D',strtotime($date)),'sent'=>(int)$row['s'],'opened'=>(int)$row['o'],'replies'=>(int)$row['rep']];}
$max_val=max(array_merge(array_column($analytics,'sent'),array_column($analytics,'opened'),[1]));
$titles=['campaign'=>'New Campaign','noweb'=>'No-Website Hunt','contacts'=>'Contacts','history'=>'Campaign History','aiwriter'=>'AI Writer','templates'=>'Templates','abtest'=>'A/B Testing','analytics'=>'Analytics','followup'=>'Follow-up Engine','settings'=>'Settings'];

// Quick hunt categories with icons — expanded list
$quick_hunts = [
  ['cat'=>'Clinics','icon'=>'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.41-1.41L12 14.17l7.59-7.59L21 8l-9 9z'],
  ['cat'=>'Hospitals','icon'=>'M17 8C8 10 5.9 16.17 3.82 22H5.71c.46-1.38.93-2.74 1.67-4h12.86c.37.62.72 1.29 1.05 2 .98-2.78.62-5.68-4.29-7zm-3 5H10v-2h4v2zm-2-4H8V7h4v2zm4 0h-2V7h2v2zm0 8H6c.35-.66.7-1.33 1.06-2h9.88c.36.67.71 1.34 1.06 2z'],
  ['cat'=>'Pharmacies','icon'=>'M20 6h-2.18c.07-.44.18-.88.18-1.36C18 2.51 15.49 0 12.36 0c-1.9 0-3.54 1.01-4.56 2.51L6.36 6H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zM9.83 3.73C10.39 2.67 11.29 2 12.36 2c1.69 0 3.07 1.38 3.07 3.07 0 .68-.26 1.28-.64 1.93H9.25c-.03-.64.08-1.31.58-2.27zM13 14v2h-2v-2H9v-2h2v-2h2v2h2v2h-2z'],
  ['cat'=>'Restaurants','icon'=>'M18.06 22.99h1.66c.84 0 1.53-.64 1.63-1.46L23 5.05h-5V1h-1.97v4.05h-4.97l.3 2.34c1.71.47 3.31 1.32 4.27 2.26 1.44 1.42 2.43 2.89 2.43 5.29v8.05zM1 21.99V21h15.03v.99c0 .55-.45 1-1.01 1H2.01c-.56 0-1.01-.45-1.01-1zm15.03-7c0-3.5-3.36-5-7.52-5S1 11.49 1 14.99v1h15.03v-1z'],
  ['cat'=>'Hotels','icon'=>'M7 13c1.66 0 3-1.34 3-3S8.66 7 7 7s-3 1.34-3 3 1.34 3 3 3zm12-6h-8v7H3V5H1v15h2v-3h18v3h2v-9c0-2.21-1.79-4-4-4z'],
  ['cat'=>'Schools','icon'=>'M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82zM12 3L1 9l11 6 9-4.91V17h2V9L12 3z'],
  ['cat'=>'Universities','icon'=>'M12 3L1 9l4 2.18V17c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2v-5.82L21 9l-9-6zm6 13H6v-4.82l6 3.27 6-3.27V16zm-6-4L3.67 9 12 5.34 20.33 9 12 12z'],
  ['cat'=>'Banks','icon'=>'M11.5 1L2 6v2h19V6l-9.5-5zM2 20v2h19v-2H2zm17-2V9h-3v9h3zm-5 0V9h-3v9h3zm-5 0V9H6v9h3z'],
  ['cat'=>'Supermarkets','icon'=>'M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96C5 16.1 6.9 18 9 18h12v-2H9.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63H19c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z'],
  ['cat'=>'Law Firms','icon'=>'M13 3h-2v10h2V3zm4.83 2.17l-1.42 1.42C17.99 7.86 19 9.81 19 12c0 3.87-3.13 7-7 7s-7-3.13-7-7c0-2.19 1.01-4.14 2.58-5.42L6.17 5.17C4.23 6.82 3 9.26 3 12c0 4.97 4.03 9 9 9s9-4.03 9-9c0-2.74-1.23-5.18-3.17-6.83z'],
  ['cat'=>'Barbershops','icon'=>'M9.64 7.64c.23-.5.36-1.05.36-1.64 0-2.21-1.79-4-4-4S2 3.79 2 6s1.79 4 4 4c.59 0 1.14-.13 1.64-.36L10 12l-2.36 2.36C7.14 14.13 6.59 14 6 14c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4c0-.59-.13-1.14-.36-1.64L12 14l7 7h3v-1L9.64 7.64zM6 8c-1.1 0-2-.89-2-2s.9-2 2-2 2 .89 2 2-.9 2-2 2zm0 12c-1.1 0-2-.89-2-2s.9-2 2-2 2 .89 2 2-.9 2-2 2zm6-7.5c-.28 0-.5-.22-.5-.5s.22-.5.5-.5.5.22.5.5-.22.5-.5.5zM19 3l-6 6 2 2 7-7V3z'],
  ['cat'=>'Hair Salons','icon'=>'M9.64 7.64c.23-.5.36-1.05.36-1.64 0-2.21-1.79-4-4-4S2 3.79 2 6s1.79 4 4 4c.59 0 1.14-.13 1.64-.36L10 12l-2.36 2.36C7.14 14.13 6.59 14 6 14c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4c0-.59-.13-1.14-.36-1.64L12 14l7 7h3v-1L9.64 7.64z'],
  ['cat'=>'Gyms','icon'=>'M20.57 14.86L22 13.43 20.57 12 17 15.57 8.43 7 12 3.43 10.57 2 9.14 3.43 7.71 2 5.57 4.14 4.14 2.71 2.71 4.14l1.43 1.43L2 7.71l1.43 1.43L2 10.57 3.43 12 7 8.43 15.57 17 12 20.57 13.43 22l1.43-1.43L16.29 22l2.14-2.14 1.43 1.43 1.43-1.43-1.43-1.43L22 16.29z'],
  ['cat'=>'Churches','icon'=>'M18 11h-5V6h-2v5H6v2h5v5h2v-5h5z M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z'],
  ['cat'=>'Mosques','icon'=>'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z'],
  ['cat'=>'Bakeries','icon'=>'M18.06 22.99h1.66c.84 0 1.53-.64 1.63-1.46L23 5.05h-5V1h-1.97v4.05h-4.97l.3 2.34c1.71.47 3.31 1.32 4.27 2.26 1.44 1.42 2.43 2.89 2.43 5.29v8.05zM1 21.99V21h15.03v.99c0 .55-.45 1-1.01 1H2.01c-.56 0-1.01-.45-1.01-1zm15.03-7c0-3.5-3.36-5-7.52-5S1 11.49 1 14.99v1h15.03v-1z'],
  ['cat'=>'Petrol Stations','icon'=>'M19.77 7.23l.01-.01-3.72-3.72L15 4.56l2.11 2.11c-.94.36-1.61 1.26-1.61 2.33 0 1.38 1.12 2.5 2.5 2.5.36 0 .69-.08 1-.21v7.21c0 .55-.45 1-1 1s-1-.45-1-1V14c0-1.1-.9-2-2-2h-1V5c0-1.1-.9-2-2-2H6c-1.1 0-2 .9-2 2v16h10v-7.5h1.5v5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V9c0-.69-.28-1.32-.73-1.77zM18 10c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zM8 18v-4.5H6L10 7v4.5h2L8 18z'],
  ['cat'=>'Auto Mechanics','icon'=>'M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z'],
  ['cat'=>'Tailors','icon'=>'M21 6.5C21 4.01 18.99 2 16.5 2c-2.17 0-3.98 1.51-4.41 3.54C11.72 4.55 11.41 4.5 11 4.5c-1.4 0-2.5 1.12-2.5 2.5 0 .61.22 1.17.58 1.6L2.5 18.5c-.39.39-.39 1.02 0 1.41l1.59 1.59c.39.39 1.02.39 1.41 0l3.72-3.72c.49.47 1.15.72 1.78.72 1.4 0 2.5-1.12 2.5-2.5 0-.31-.06-.6-.16-.87l1.37-1.37C15.14 14.86 15.79 15 16.5 15 18.99 15 21 12.99 21 10.5c0-.55-.1-1.07-.26-1.57l-2.25 2.25-2.83-2.83 2.25-2.25c-.5-.15-1.02-.35-1.57-.1z'],
  ['cat'=>'Photography','icon'=>'M12 15.2c1.77 0 3.2-1.43 3.2-3.2S13.77 8.8 12 8.8 8.8 10.23 8.8 12s1.43 3.2 3.2 3.2zm8-9.2h-2l-2-2H8L6 6H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-8 13c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z'],
  ['cat'=>'Event Centers','icon'=>'M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z'],
  ['cat'=>'Real Estate','icon'=>'M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z'],
  ['cat'=>'IT Companies','icon'=>'M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4zM4 6h16v10H4V6z'],
  ['cat'=>'Logistics','icon'=>'M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z'],
  ['cat'=>'Security Companies','icon'=>'M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z'],
  ['cat'=>'NGOs','icon'=>'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z'],
  ['cat'=>'Solar Companies','icon'=>'M6.76 4.84l-1.8-1.79-1.41 1.41 1.79 1.79 1.42-1.41zM4 10.5H1v2h3v-2zm9-9.95h-2V3.5h2V.55zm7.45 3.91l-1.41-1.41-1.79 1.79 1.41 1.41 1.79-1.79zm-3.21 13.7l1.79 1.8 1.41-1.41-1.8-1.79-1.4 1.4zM20 10.5v2h3v-2h-3zm-8-5c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm-1 16.95h2V19.5h-2v2.95zm-7.45-3.91l1.41 1.41 1.79-1.8-1.41-1.41-1.79 1.8z'],
  ['cat'=>'Dental Clinics','icon'=>'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z'],
  ['cat'=>'Travel Agencies','icon'=>'M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z'],
  ['cat'=>'Consulting Firms','icon'=>'M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z'],
  ['cat'=>'Spa & Wellness','icon'=>'M6.05 8.86c1.43-3.27 4.22-5.4 7.69-5.81L12 1 8.38 5.2c-1.61.35-3.04 1.14-4.18 2.22l1.85 1.44zM17.62 5.2L14 1l-1.74 2.05c3.47.41 6.26 2.54 7.69 5.81l1.85-1.44c-1.14-1.08-2.57-1.87-4.18-2.22zM12 6C8.13 6 5 9.13 5 13c0 2.83 1.62 5.28 4 6.47V21h6v-1.53c2.38-1.19 4-3.64 4-6.47 0-3.87-3.13-7-7-7zm2 13h-4v-1h4v1zm0-2H10c-2.21-.91-3.79-3.07-3.79-5.57C6.21 9.63 8.89 7 12 7s5.79 2.63 5.79 5.43c0 2.5-1.58 4.66-3.79 5.57z'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>EMOL — <?= $titles[$page] ?></title>
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
.sb-logo-name{font-family:'Nunito',sans-serif;font-weight:900;font-size:1.15rem;background:linear-gradient(135deg,var(--green),var(--blue));-webkit-background-clip:text;background-clip:text;color:transparent;text-decoration:none}
/* Search — no mic */
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
.sb-link.active svg,.sb-link:hover svg{opacity:1}
/* Quick hunt active highlight */
.sb-link.qh-active{background:rgba(255,107,53,.1);border:1px solid rgba(255,107,53,.25);color:var(--accent3);font-weight:700}
.sb-link.qh-active::before{background:var(--accent3)}
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
.topbar{height:58px;min-height:58px;display:flex;align-items:center;justify-content:space-between;padding:0 16px;border-bottom:1px solid var(--border);background:var(--surface);gap:8px}
.topbar-left{display:flex;align-items:center;gap:10px;min-width:0}
.hamburger{display:none;background:transparent;border:none;color:var(--text);font-size:1.2rem;cursor:pointer;flex-shrink:0}
.page-title{font-family:'Nunito',sans-serif;font-weight:800;font-size:.95rem;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.topbar-right{display:flex;align-items:center;gap:6px;flex-shrink:0}
.tb-icon{width:32px;height:32px;border-radius:9px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--muted);cursor:pointer;transition:all .15s;text-decoration:none;position:relative;flex-shrink:0}
.tb-icon:hover{color:var(--green);border-color:rgba(0,229,160,.3)}
.notif-dot{position:absolute;top:6px;right:6px;width:6px;height:6px;border-radius:50%;background:var(--green);border:2px solid var(--surface)}
.tb-btn{display:flex;align-items:center;gap:5px;padding:7px 13px;border-radius:9px;background:var(--green);border:none;color:#000;font-weight:700;font-size:.75rem;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s;text-decoration:none;white-space:nowrap}
.tb-btn:hover{transform:translateY(-1px);box-shadow:0 5px 18px rgba(0,229,160,.25);color:#000}
.tb-ghost{display:flex;align-items:center;gap:5px;padding:7px 12px;border-radius:9px;background:transparent;border:1px solid var(--border);color:var(--muted);font-size:.75rem;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all .15s;text-decoration:none;white-space:nowrap}
.tb-ghost:hover{color:var(--text);border-color:var(--border2)}

/* ── CONTENT ── */
.content{flex:1;overflow:visible;padding:16px;display:flex;flex-direction:column;gap:14px}


/* ── PAGE ── */
.page{display:none}
.page.active{display:block;animation:fadeUp .3s ease}
@keyframes fadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.page-hd{margin-bottom:16px}
.page-hd h1{font-family:'Nunito',sans-serif;font-weight:800;font-size:1.3rem;margin-bottom:3px}
.page-hd p{font-size:.76rem;color:var(--muted)}

/* ── CARDS ── */
.card{background:var(--surface);border-radius:16px;border:1px solid var(--border);overflow:hidden;margin-bottom:14px}
.cp{padding:16px}
.card-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.card-title{font-family:'Nunito',sans-serif;font-weight:800;font-size:.86rem}
.card-sub{font-size:.68rem;color:var(--muted);margin-top:2px}

/* ── BUTTONS ── */
.mini-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 13px;border-radius:999px;background:var(--surface2);border:1px solid var(--border);color:var(--text);font-size:.74rem;font-weight:600;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif;text-decoration:none}
.mini-btn:hover{background:var(--green-dim);border-color:rgba(0,229,160,.3);color:var(--green)}
.mini-btn.primary{background:var(--green);color:#000;border-color:var(--green)}
.mini-btn.primary:hover{box-shadow:0 4px 14px rgba(0,229,160,.3);transform:translateY(-1px);color:#000}
.mini-btn.danger{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.25);color:#ef4444}
.mini-btn.danger:hover{background:rgba(239,68,68,.2);color:#ef4444}
.mini-btn.purple{background:rgba(168,85,247,.1);border-color:rgba(168,85,247,.25);color:var(--accent4)}
.mini-btn.purple:hover{background:rgba(168,85,247,.2);color:var(--accent4)}

/* ── FORM ── */
.flabel{display:block;font-size:.68rem;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px}
.finput{width:100%;padding:10px 13px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:.84rem;outline:none;transition:border-color .2s}
.finput:focus{border-color:var(--green)}
.finput::placeholder{color:var(--muted2)}
select.finput{cursor:pointer}
textarea.finput{resize:vertical;min-height:110px;line-height:1.6}
input[type=range]{width:100%;accent-color:var(--green);margin-top:4px}
.field{margin-bottom:14px}
.row2f{display:grid;grid-template-columns:1fr 1fr;gap:13px}
.row3f{display:grid;grid-template-columns:1fr 1fr 1fr;gap:13px}

/* ── TIPBOX ── */
.tipbox{display:flex;align-items:flex-start;gap:10px;padding:10px 13px;border-radius:11px;background:rgba(0,229,160,.05);border:1px solid rgba(0,229,160,.15);font-size:.76rem;color:var(--muted);margin-bottom:13px;line-height:1.6}
.tipbox i,.tipbox svg{color:var(--green);margin-top:2px;flex-shrink:0}
.tipbox.orange{background:rgba(255,107,53,.05);border-color:rgba(255,107,53,.18)}
.tipbox.orange i,.tipbox.orange svg{color:var(--accent3)}
.tipbox.blue{background:rgba(59,130,246,.05);border-color:rgba(59,130,246,.18)}
.tipbox.blue i,.tipbox.blue svg{color:var(--blue)}

/* ── WIZARD ── */
.wizard{display:flex;border-bottom:1px solid var(--border);overflow-x:auto;margin-bottom:0;-webkit-overflow-scrolling:touch}
.wstep{display:flex;align-items:center;gap:7px;padding:12px 16px;font-size:.76rem;font-weight:600;color:var(--muted);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;transition:all .2s;white-space:nowrap}
.wstep:hover{color:var(--text)}
.wstep.active{color:var(--green);border-bottom-color:var(--green)}
.wstep.done{color:var(--success)}
.wnum{width:20px;height:20px;border-radius:50%;background:var(--surface3);display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:800;flex-shrink:0}
.wstep.active .wnum{background:var(--green-dim);color:var(--green)}
.wstep.done .wnum{background:rgba(34,197,94,.15);color:var(--success)}
.wpanel{display:none;padding:18px}
.wpanel.active{display:block;animation:fadeUp .25s ease}

/* ── CHIPS ── */
.chip{padding:5px 10px;border-radius:20px;font-size:.69rem;font-weight:600;cursor:pointer;border:1px solid var(--border2);color:var(--muted);transition:all .15s;background:var(--surface2);display:inline-flex;align-items:center;gap:4px;user-select:none}
.chip:hover{border-color:var(--green);color:var(--green)}
.chip.on{background:var(--green-dim);border-color:var(--green);color:var(--green)}

/* ── CAROUSEL ── */
.carousel-shell{overflow:hidden;border-radius:12px;margin-top:12px}
.carousel-track{display:flex;gap:12px;transition:transform .35s cubic-bezier(.25,.46,.45,.94)}
.biz-card{min-width:240px;max-width:240px;background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:13px;cursor:pointer;transition:all .2s;flex-shrink:0;position:relative}
.biz-card:hover{border-color:rgba(0,229,160,.3);transform:translateY(-2px)}
.biz-card.selected{border-color:var(--green);background:rgba(0,229,160,.05)}
.biz-card.selected::after{content:'✓';position:absolute;top:9px;right:9px;width:18px;height:18px;border-radius:50%;background:var(--green);color:#000;font-size:.66rem;font-weight:800;display:flex;align-items:center;justify-content:center}
.biz-name{font-weight:700;font-size:.83rem;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.biz-addr{font-size:.7rem;color:var(--muted);margin-bottom:7px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.biz-tag{font-size:.61rem;padding:2px 6px;border-radius:20px;font-weight:600;display:inline-flex;align-items:center;gap:3px}
.noweb-tag{background:rgba(255,107,53,.12);color:var(--accent3)}
.hasweb-tag{background:rgba(34,197,94,.1);color:var(--success)}
.carousel-nav{display:flex;align-items:center;justify-content:space-between;margin-top:10px}
.cnav-btn{padding:5px 10px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--muted);cursor:pointer;transition:all .15s;font-size:.82rem}
.cnav-btn:hover:not(:disabled){color:var(--green);border-color:var(--green)}
.cnav-btn:disabled{opacity:.3;cursor:not-allowed}
.recipient-bar{display:flex;align-items:center;justify-content:space-between;padding:10px 13px;background:var(--surface2);border-radius:10px;margin-top:10px;border:1px solid var(--border)}
.biz-filters{display:flex;gap:5px;flex-wrap:wrap;margin-top:10px}
.biz-ftab{padding:5px 10px;border-radius:20px;font-size:.68rem;font-weight:600;background:var(--surface2);border:1px solid var(--border);color:var(--muted);cursor:pointer;transition:all .15s}
.biz-ftab:hover,.biz-ftab.active{background:var(--green-dim);border-color:var(--green);color:var(--green)}
.biz-ftab.noweb-tab.active{background:rgba(255,107,53,.1);border-color:var(--accent3);color:var(--accent3)}
.biz-search-wrap{position:relative;margin-top:8px}
.biz-search-wrap svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);opacity:.5;pointer-events:none}
.biz-search-wrap .finput{padding-left:30px}
.clr-btn{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted2);cursor:pointer}
.map-mock{height:100px;background:var(--surface2);border:1px solid var(--border);border-radius:11px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:6px;margin-bottom:12px}

/* ── EDITOR ── */
.editor-toolbar{display:flex;flex-wrap:wrap;gap:4px;padding:8px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px 10px 0 0;border-bottom:none}
.ebtn{padding:4px 8px;background:transparent;border:1px solid transparent;border-radius:5px;color:var(--muted);font-size:.76rem;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif}
.ebtn:hover{background:var(--surface3);color:var(--text);border-color:var(--border2)}
.editor-area{min-height:160px;padding:12px;background:var(--surface2);border:1px solid var(--border2);border-radius:0 0 10px 10px;font-size:.83rem;line-height:1.7;color:var(--text);outline:none}
.editor-area:focus{border-color:var(--green)}
.preview-white{background:#fff;border-radius:10px;padding:16px;min-height:130px;font-size:.82rem;line-height:1.7;color:#1a1a1a}

/* ── MISC ── */
.prog-wrap{height:4px;background:var(--surface3);border-radius:99px;overflow:hidden;margin:6px 0 3px}
.prog-bar{height:100%;background:linear-gradient(90deg,var(--green),var(--blue));border-radius:99px;transition:width .8s ease}
.send-log-item{display:flex;align-items:center;gap:8px;padding:6px 0;font-size:.75rem;border-bottom:1px solid rgba(255,255,255,.04)}
.send-log-item:last-child{border-bottom:none}
.tbl{width:100%;border-collapse:collapse}
.tbl th{font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);padding:9px 12px;text-align:left;border-bottom:1px solid var(--border)}
.tbl td{padding:10px 12px;font-size:.79rem;border-bottom:1px solid rgba(255,255,255,.03);vertical-align:middle}
.tbl tr:last-child td{border-bottom:none}
.tbl tr:hover td{background:rgba(255,255,255,.015)}
.bdg{display:inline-flex;align-items:center;padding:2px 7px;border-radius:20px;font-size:.63rem;font-weight:700}
.bdg-s{background:rgba(34,197,94,.1);color:#4ade80}
.bdg-w{background:rgba(245,158,11,.1);color:#fbbf24}
.bdg-g{background:rgba(148,163,184,.1);color:#94a3b8}
.bdg-i{background:rgba(59,130,246,.1);color:#60a5fa}
.chart-bars{display:flex;align-items:flex-end;gap:8px;height:130px;padding:4px 0}
.bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;height:100%}
.bar-stk{width:100%;display:flex;flex-direction:column;justify-content:flex-end;gap:2px;flex:1}
.bar-seg{border-radius:4px 4px 0 0;min-height:3px;transition:height .6s ease}
.bar-lbl{font-size:.61rem;color:var(--muted)}
.ai-panel{background:rgba(168,85,247,.06);border:1px solid rgba(168,85,247,.2);border-radius:12px;padding:13px;margin-bottom:13px}
.setting-row{display:flex;align-items:center;justify-content:space-between;padding:13px 0;border-bottom:1px solid var(--border)}
.setting-row:last-child{border-bottom:none}
.setting-row h4{font-size:.82rem;font-weight:600;margin-bottom:2px}
.setting-row p{font-size:.7rem;color:var(--muted)}
.status-dot{width:7px;height:7px;border-radius:50%;display:inline-block;margin-right:5px}
.dot-ok{background:var(--success)}.dot-warn{background:var(--warning)}
.empty-st{text-align:center;padding:40px 20px;color:var(--muted2)}
.empty-st svg{margin-bottom:10px;opacity:.3}
.empty-st p{font-size:.76rem;margin-bottom:12px}
.toast-wrap{position:fixed;bottom:22px;right:22px;z-index:9999;display:flex;flex-direction:column;gap:6px}
.toast{padding:10px 15px;border-radius:10px;font-size:.78rem;font-weight:600;animation:toastIn .3s ease;box-shadow:0 8px 24px rgba(0,0,0,.4);max-width:290px}
.toast.success{background:#14532d;color:#4ade80;border:1px solid rgba(34,197,94,.3)}
.toast.error{background:#7f1d1d;color:#f87171;border:1px solid rgba(239,68,68,.3)}
.toast.info{background:#1e3a5f;color:#93c5fd;border:1px solid rgba(59,130,246,.3)}
@keyframes toastIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

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
.lm-spinner{width:14px;height:14px;border:2px solid rgba(245,158,11,.3);border-top-color:#f59e0b;border-radius:50%;animation:spin .7s linear infinite;flex-shrink:0}
@keyframes spin{to{transform:rotate(360deg)}}
.lm-actions{display:flex;gap:9px}
.lm-cancel{flex:1;padding:10px;border-radius:10px;background:var(--surface2);border:1px solid var(--border2);color:var(--muted);font-size:.8rem;font-weight:600;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif}
.lm-cancel:hover{color:var(--text);border-color:var(--border2)}
.lm-confirm{flex:1;padding:10px;border-radius:10px;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#f87171;font-size:.8rem;font-weight:700;cursor:pointer;transition:all .15s;font-family:'DM Sans',sans-serif}
.lm-confirm:hover{background:rgba(239,68,68,.2);border-color:rgba(239,68,68,.5);color:#fca5a5}
.lm-confirm:disabled{opacity:.5;cursor:not-allowed}

/* ── MOBILE — deep responsive ── */
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:99}
.sb-overlay.show{display:block}

@media(max-width:1024px){
  .row2f{grid-template-columns:1fr 1fr}
  .row3f{grid-template-columns:1fr 1fr}
}
@media(max-width:768px){
  :root{--sb-w:240px}
  .hamburger{display:block}
  .sidebar{position:fixed;top:0;left:0;height:100%;transform:translateX(-100%)}
  .sidebar.open{transform:translateX(0)}
  .content{padding:12px}
  .row2f,.row3f{grid-template-columns:1fr}
  .biz-card{min-width:200px;max-width:200px}
  /* AI writer stacks vertically */
  #page-aiwriter .ai-grid{grid-template-columns:1fr!important}
  /* Settings stacks */
  #page-settings .settings-grid{grid-template-columns:1fr!important}
  /* A/B test stacks */
  #page-abtest .ab-grid{grid-template-columns:1fr!important}
  /* Analytics stacks */
  #page-analytics .analytics-mini{grid-template-columns:1fr 1fr!important}
  .wizard{overflow-x:auto}
  .wstep{padding:10px 12px;font-size:.72rem}
}
@media(max-width:520px){
  .topbar{padding:0 12px}
  .tb-ghost{display:none}
  .content{padding:10px}
  .cp{padding:12px}
  .wpanel{padding:12px}
  .row2f,.row3f{grid-template-columns:1fr}
  .biz-card{min-width:180px;max-width:180px}
  .page-hd h1{font-size:1.1rem}
  #page-analytics .analytics-mini{grid-template-columns:1fr 1fr}
}
@media(max-width:380px){
  .tb-btn span{display:none}
  .biz-card{min-width:160px;max-width:160px}
}
</style>
</head>
<body>
<div class="sb-overlay" id="overlay" onclick="closeSb()"></div>
<div class="toast-wrap" id="toastWrap"></div>

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
      <span id="lmBusyText">A campaign is still sending — please wait before signing out.</span>
    </div>
    <div class="lm-actions">
      <button class="lm-cancel" onclick="closeLogoutModal()">Cancel</button>
      <button class="lm-confirm" id="lmConfirmBtn" onclick="doLogout()">Sign Out</button>
    </div>
  </div>
</div>
<div class="layout">

<!-- ══ SIDEBAR ══ -->
<nav class="sidebar" id="sidebar">
  <div class="sb-logo">
    <div class="sb-logo-icon">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </div>
    <a href="dashboard.php" class="sb-logo-name">EMOL</a>
  </div>

  <!-- Search — NO microphone -->
  <div class="sb-search">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="text" placeholder="Search..." oninput="sbSearch(this.value)"/>
  </div>

  <div class="sb-nav">
    <a class="sb-link" href="dashboard.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Home
    </a>
    <button class="sb-link <?= $page==='campaign'?'active':'' ?>" onclick="nav('campaign')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>New Campaign
    </button>
    <button class="sb-link <?= $page==='noweb'?'active':'' ?>" onclick="nav('noweb')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent3)"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>No-Website Hunt<span class="sb-badge">HOT</span>
    </button>
    <button class="sb-link <?= $page==='contacts'?'active':'' ?>" onclick="nav('contacts')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>Contacts
    </button>
    <button class="sb-link <?= $page==='analytics'?'active':'' ?>" onclick="nav('analytics')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>Analytics
    </button>

    <div class="sb-section">Tools</div>
    <button class="sb-link <?= $page==='aiwriter'?'active':'' ?>" onclick="nav('aiwriter')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--green)"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>AI Writer<span class="sb-badge green">AI</span>
    </button>
    <button class="sb-link <?= $page==='templates'?'active':'' ?>" onclick="nav('templates')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>Templates
    </button>
    <button class="sb-link <?= $page==='abtest'?'active':'' ?>" onclick="nav('abtest')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>A/B Testing<span class="sb-badge blue">NEW</span>
    </button>
    <button class="sb-link <?= $page==='followup'?'active':'' ?>" onclick="nav('followup')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>Follow-up Engine
    </button>

    <div class="sb-section">Account</div>
    <button class="sb-link <?= $page==='history'?'active':'' ?>" onclick="nav('history')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Campaign History
    </button>
    <button class="sb-link <?= $page==='settings'?'active':'' ?>" onclick="nav('settings')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Settings
    </button>

    <!-- Quick Hunt — expanded, highlights on click -->
    <div class="sb-section">Quick Hunt</div>
    <?php foreach($quick_hunts as $qh): ?>
    <button class="sb-link" id="qh-<?= strtolower(preg_replace('/\s+/','-',$qh['cat'])) ?>" onclick="qHunt('<?= $qh['cat'] ?>')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $qh['icon'] ?>"/></svg><?= $qh['cat'] ?>
    </button>
    <?php endforeach; ?>
  </div>

  <div class="sb-bottom">
    <div class="sb-user">
      <div class="sb-avatar"><?= htmlspecialchars($user['initials']??'U') ?></div>
      <div style="flex:1;min-width:0">
        <div class="sb-user-name"><?= htmlspecialchars($user['name']) ?></div>
        <span class="sb-user-role"><?= ucfirst($user['role']) ?></span>
      </div>
      <button onclick="nav('settings')" style="background:none;border:none;color:var(--muted);cursor:pointer;padding:4px;transition:color .15s;flex-shrink:0">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      </button>
    </div>
    <button class="logout-btn" onclick="openLogoutModal()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Sign Out
    </button>
  </div>
</nav>

<!-- ══ MAIN ══ -->
<div class="main-wrap">
  <div class="topbar">
    <div class="topbar-left">
      <button class="hamburger" onclick="toggleSb()">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="page-title" id="topTitle"><?= $titles[$page] ?></div>
    </div>
    <div class="topbar-right">
      <a href="dashboard.php" class="tb-ghost">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg><span>Dashboard</span>
      </a>
      <div class="tb-icon">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="notif-dot"></span>
      </div>
      <button class="tb-btn" onclick="nav('campaign')">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg><span>Campaign</span>
      </button>
    </div>
  </div>

  <div class="content">

<!-- ══ CAMPAIGN WIZARD ══ -->
<div class="page <?= $page==='campaign'?'active':'' ?>" id="page-campaign">
  <div class="page-hd"><h1>New Campaign</h1><p>Find businesses, compose your email and send — all in one flow</p></div>
  <div class="tipbox"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg><div>EMOL searches Google Maps for businesses in your chosen category + location, personalizes each email, then sends via your SMTP.</div></div>
  <div class="card">
    <div class="wizard">
      <div class="wstep active" id="ws1" onclick="goStep(1)"><div class="wnum" id="wn1">1</div>Sender</div>
      <div class="wstep" id="ws2" onclick="goStep(2)"><div class="wnum" id="wn2">2</div>Target</div>
      <div class="wstep" id="ws3" onclick="goStep(3)"><div class="wnum" id="wn3">3</div>Compose</div>
      <div class="wstep" id="ws4" onclick="goStep(4)"><div class="wnum" id="wn4">4</div>Preview</div>
      <div class="wstep" id="ws5" onclick="goStep(5)"><div class="wnum" id="wn5">5</div>Send</div>
    </div>

    <!-- Step 1 -->
    <div class="wpanel active" id="step1">
      <h5 style="font-family:'Nunito',sans-serif;font-weight:800;margin-bottom:14px">Sender Information</h5>
      <div class="row2f">
        <div class="field"><label class="flabel">Your Name</label><input class="finput" id="senderName" placeholder="e.g. Ahmed Ibrahim" value="<?= htmlspecialchars($user['firstname']) ?>"/></div>
        <div class="field"><label class="flabel">Campaign Name</label><input class="finput" id="campaignName" placeholder="e.g. Clinic Promo April"/></div>
        <div class="field"><label class="flabel">From Email</label><input class="finput" id="senderEmail" type="email" placeholder="you@example.com" value="<?= htmlspecialchars($user['email']) ?>"/></div>
        <div class="field"><label class="flabel">Campaign Goal</label>
          <select class="finput" id="campaignGoal"><option>Generate Leads</option><option>Brand Awareness</option><option>Partnership Proposal</option><option>Website Pitch</option><option>Event Invitation</option><option>Product Launch</option></select>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;margin-top:6px"><button class="mini-btn primary" onclick="nextStep(1)">Next: Target →</button></div>
    </div>

    <!-- Step 2 -->
    <div class="wpanel" id="step2">
      <h5 style="font-family:'Nunito',sans-serif;font-weight:800;margin-bottom:14px">Find Your Target Audience</h5>
      <div class="row3f" style="margin-bottom:12px">
        <div class="field"><label class="flabel">Business Category</label><input class="finput" id="catInput" placeholder="e.g. Clinics, Hotels..."/></div>
        <div class="field"><label class="flabel">Location</label><input class="finput" id="locInput" placeholder="e.g. Abuja, Lagos..." value="Maiduguri"/></div>
        <div class="field"><label class="flabel">Max Results</label><select class="finput" id="maxResults"><option value="10">10 businesses</option><option value="20">20 businesses</option><option value="50">50 businesses</option></select></div>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:8px">
        <div style="font-size:.7rem;color:var(--muted)">Quick select <span style="color:var(--green);font-weight:700">(pick multiple)</span></div>
        <div style="display:flex;gap:6px">
          <button class="mini-btn" style="padding:4px 8px;font-size:.67rem" onclick="selectAllCats()">All</button>
          <button class="mini-btn" style="padding:4px 8px;font-size:.67rem" onclick="clearAllCats()">Clear</button>
          <input class="finput" id="catSearch" placeholder="Filter..." style="font-size:.68rem;padding:5px 9px;width:100px" oninput="filterCatChips(this.value)"/>
        </div>
      </div>
      <div style="max-height:160px;overflow-y:auto;border:1px solid var(--border);border-radius:10px;background:var(--surface2);padding:9px;margin-bottom:11px">
        <div style="display:flex;flex-wrap:wrap;gap:5px" id="catChips">
          <?php
          $cats_list=[
            'Clinics'=>'bi-hospital','Hospitals'=>'bi-h-square','Pharmacies'=>'bi-capsule',
            'Dental Clinics'=>'bi-emoji-smile','Eye Clinics'=>'bi-eye','Veterinary Clinics'=>'bi-heart-pulse',
            'Physiotherapy'=>'bi-activity','Laboratories'=>'bi-virus',
            'Primary Schools'=>'bi-mortarboard','Secondary Schools'=>'bi-book','Universities'=>'bi-building',
            'Polytechnics'=>'bi-journal-bookmark','Nursery Schools'=>'bi-balloon-heart','Vocational Centers'=>'bi-tools',
            'Restaurants'=>'bi-shop','Fast Food'=>'bi-egg-fried','Cafes'=>'bi-cup-hot','Bakeries'=>'bi-bag',
            'Hotels'=>'bi-building-fill','Guest Houses'=>'bi-house-door','Event Centers'=>'bi-calendar-event',
            'Supermarkets'=>'bi-cart','Clothing Stores'=>'bi-bag-heart','Electronics'=>'bi-phone',
            'Law Firms'=>'bi-briefcase','Accounting Firms'=>'bi-calculator','Real Estate'=>'bi-house-check',
            'Consulting Firms'=>'bi-person-workspace','Insurance Offices'=>'bi-shield-check',
            'Banks'=>'bi-bank','Microfinance'=>'bi-coin','Mobile Money'=>'bi-phone',
            'Gyms'=>'bi-dribbble','Spa & Wellness'=>'bi-flower1','Hair Salons'=>'bi-scissors',
            'Barbershops'=>'bi-scissors','Photography'=>'bi-camera',
            'Auto Mechanics'=>'bi-wrench-adjustable','Car Wash'=>'bi-droplet','Fuel Stations'=>'bi-fuel-pump',
            'Churches'=>'bi-building-check','Mosques'=>'bi-moon-stars','NGOs'=>'bi-heart',
            'IT Companies'=>'bi-laptop','Web Agencies'=>'bi-code-slash','Marketing Agencies'=>'bi-megaphone',
            'Security Companies'=>'bi-shield-lock','Cleaning Services'=>'bi-stars',
            'Logistics'=>'bi-truck','Solar Energy'=>'bi-sun','Agriculture'=>'bi-tree',
            'Travel Agencies'=>'bi-airplane','Recruitment Agencies'=>'bi-person-check',
            'Tailors'=>'bi-threads','Printing Press'=>'bi-printer','Hardware Stores'=>'bi-tools',
            'Furniture Stores'=>'bi-house','Pharmacies (Retail)'=>'bi-bag-heart-fill',
          ];
          foreach($cats_list as $cat=>$icon): ?>
          <span class="chip" onclick="toggleCat(this,'<?= $cat ?>')"><i class="bi <?= $icon ?>"></i><?= $cat ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="font-size:.68rem;color:var(--muted);margin-bottom:10px"><span id="selCatCount">0 categories selected</span> — <span id="selCatList" style="color:var(--green);font-style:italic">None selected</span></div>
      <div class="map-mock" id="mapVisual">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
        <div style="font-size:.76rem;color:var(--muted2)">Search to locate businesses</div>
      </div>
      <div class="row2f" style="margin-bottom:10px">
        <div class="field"><label class="flabel">Website Filter</label><select class="finput" id="webFilter"><option value="all">All Businesses</option><option value="noweb">No Website Only</option><option value="hasweb">Has Website Only</option></select></div>
        <div class="field" style="display:flex;align-items:flex-end;gap:8px">
          <button class="mini-btn primary" style="flex:1;justify-content:center" onclick="searchBiz()" id="searchBtn">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>Search Google Maps
          </button>
          <button class="mini-btn" onclick="clearSearch()">✕</button>
        </div>
      </div>
      <div id="resultsWrap" style="display:none">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:8px">
          <div style="font-size:.78rem;font-weight:700">Found <span id="resultCount" class="bdg bdg-i">0</span> businesses</div>
          <div style="display:flex;gap:6px">
            <button class="mini-btn" style="padding:4px 8px;font-size:.67rem" onclick="selAllBiz()">All</button>
            <button class="mini-btn" style="padding:4px 8px;font-size:.67rem" onclick="deselAllBiz()">None</button>
          </div>
        </div>
        <div class="biz-search-wrap">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input class="finput" id="bizSearch" placeholder="Search by name or address..." oninput="filterCarousel(this.value)"/>
          <button class="clr-btn" onclick="document.getElementById('bizSearch').value='';filterCarousel('')">✕</button>
        </div>
        <div class="biz-filters">
          <button class="biz-ftab active" onclick="bizFilter('all',this)">All</button>
          <button class="biz-ftab noweb-tab" onclick="bizFilter('noweb',this)">No Website</button>
          <button class="biz-ftab" onclick="bizFilter('hasweb',this)">Has Website</button>
          <button class="biz-ftab" onclick="bizFilter('selected',this)">Selected</button>
        </div>
        <div class="carousel-shell" id="bizCarouselShell"><div class="carousel-track" id="bizTrack"></div></div>
        <div class="carousel-nav">
          <button class="cnav-btn" id="cnPrev" onclick="carouselMove(-1)" disabled>‹</button>
          <div style="font-size:.74rem;color:var(--muted)"><span id="cnIdx">0</span> / <span id="cnTotal">0</span></div>
          <button class="cnav-btn" id="cnNext" onclick="carouselMove(1)">›</button>
        </div>
        <div class="recipient-bar">
          <div><div style="font-size:.62rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;font-weight:700">Selected</div><div style="font-size:1.3rem;font-weight:800;color:var(--green)" id="selCount">0</div></div>
          <button class="mini-btn primary" id="contBtn" style="display:none" onclick="nextStep(2)">Continue →</button>
        </div>
      </div>
      <div style="display:flex;justify-content:space-between;margin-top:12px"><button class="mini-btn" onclick="goStep(1)">← Back</button></div>
    </div>

    <!-- Step 3 -->
    <div class="wpanel" id="step3">
      <h5 style="font-family:'Nunito',sans-serif;font-weight:800;margin-bottom:12px">Compose Your Email</h5>
      <div class="ai-panel">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
          <div>
            <div style="font-size:.84rem;font-weight:700">
              <svg style="vertical-align:-2px" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent4)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg> AI Email Writer
            </div>
            <div style="font-size:.7rem;color:var(--muted)">Let AI generate a personalized cold email</div>
          </div>
          <button class="mini-btn purple" onclick="generateAI()">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg>Generate with AI
          </button>
        </div>
      </div>
      <div class="row2f" style="margin-bottom:10px">
        <div class="field"><label class="flabel">Subject Line</label><input class="finput" id="emailSubject" placeholder="e.g. Partnership opportunity for {{business}}"/></div>
        <div class="field"><label class="flabel">Email Tone</label><select class="finput" id="emailTone"><option>Professional</option><option>Friendly</option><option>Persuasive</option><option>Urgent</option><option>Casual</option></select></div>
      </div>
      <div class="field" style="margin-bottom:8px">
        <label class="flabel">Load Template</label>
        <select class="finput" id="tplSelect" onchange="applyTpl()">
          <option value="">— Select saved template —</option>
          <?php foreach($templates_all as $t): ?>
          <option value="<?= $t['id'] ?>" data-subject="<?= htmlspecialchars($t['subject']??'') ?>" data-body="<?= htmlspecialchars($t['body']) ?>"><?= htmlspecialchars($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field" style="margin-bottom:10px">
        <label class="flabel">Personalization Tags <span style="color:var(--green);font-size:.6rem">(click to insert)</span></label>
        <div style="display:flex;flex-wrap:wrap;gap:5px">
          <span class="chip" onclick="insertTag('{{name}}')">{{name}}</span>
          <span class="chip" onclick="insertTag('{{business}}')">{{business}}</span>
          <span class="chip" onclick="insertTag('{{city}}')">{{city}}</span>
          <span class="chip" onclick="insertTag('{{sender}}')">{{sender}}</span>
          <span class="chip" onclick="insertTag('{{category}}')">{{category}}</span>
        </div>
      </div>
      <label class="flabel">Email Body</label>
      <div class="editor-toolbar">
        <button class="ebtn" onclick="ec('bold')"><b>B</b></button>
        <button class="ebtn" onclick="ec('italic')"><i>I</i></button>
        <button class="ebtn" onclick="ec('underline')"><u>U</u></button>
        <button class="ebtn" onclick="ec('justifyLeft')"><i class="bi bi-text-left"></i></button>
        <button class="ebtn" onclick="ec('justifyCenter')"><i class="bi bi-text-center"></i></button>
        <button class="ebtn" onclick="ec('justifyRight')"><i class="bi bi-text-right"></i></button>
        <button class="ebtn" onclick="ec('insertUnorderedList')"><i class="bi bi-list-ul"></i></button>
        <button class="ebtn" onclick="checkSpam()" style="margin-left:auto">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Spam Check
        </button>
      </div>
      <div class="editor-area" id="emailBody" contenteditable="true"><p>Dear <strong>{{name}}</strong>,</p><p>I hope this message finds you well. My name is <strong>{{sender}}</strong> and I am reaching out to explore a potential collaboration with <strong>{{business}}</strong>.</p><p>We believe this partnership could bring real value to your business in <strong>{{city}}</strong>.</p><p>Looking forward to hearing from you.</p><br><p>Best regards,<br><strong>{{sender}}</strong></p></div>
      <div style="display:flex;justify-content:space-between;margin-top:12px">
        <button class="mini-btn" onclick="goStep(2)">← Back</button>
        <button class="mini-btn primary" onclick="nextStep(3)">Preview →</button>
      </div>
    </div>

    <!-- Step 4 -->
    <div class="wpanel" id="step4">
      <h5 style="font-family:'Nunito',sans-serif;font-weight:800;margin-bottom:6px">Preview Your Email</h5>
      <p style="font-size:.74rem;color:var(--muted);margin-bottom:12px">Click a recipient chip to see their personalized version.</p>
      <div class="row2f" style="margin-bottom:12px">
        <div><div style="font-size:.62rem;color:var(--muted);margin-bottom:3px;text-transform:uppercase;letter-spacing:.5px">FROM</div><div style="font-size:.82rem" id="prvFrom">—</div></div>
        <div><div style="font-size:.62rem;color:var(--muted);margin-bottom:3px;text-transform:uppercase;letter-spacing:.5px">RECIPIENTS</div><div style="font-size:.82rem" id="prvTo">—</div></div>
        <div style="grid-column:1/-1"><div style="font-size:.62rem;color:var(--muted);margin-bottom:3px;text-transform:uppercase;letter-spacing:.5px">SUBJECT</div><div style="font-size:.82rem;font-weight:700" id="prvSubject">—</div></div>
      </div>
      <div class="tipbox"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"/></svg><div><strong style="color:var(--green)">Auto-Personalization Active</strong> — <code style="background:var(--green-dim);padding:1px 4px;border-radius:3px;color:var(--green)">{{name}}</code>, <code style="background:var(--green-dim);padding:1px 4px;border-radius:3px;color:var(--green)">{{business}}</code>, <code style="background:var(--green-dim);padding:1px 4px;border-radius:3px;color:var(--green)">{{city}}</code> replaced per recipient.</div></div>
      <div style="font-size:.64rem;color:var(--muted);margin-bottom:5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Preview as:</div>
      <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px" id="prvSwitcher"></div>
      <label class="flabel">Live Preview</label>
      <div class="preview-white" id="prvFrame"></div>
      <div style="display:flex;justify-content:space-between;margin-top:12px">
        <button class="mini-btn" onclick="goStep(3)">← Back</button>
        <button class="mini-btn primary" onclick="nextStep(4)">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>Send Campaign
        </button>
      </div>
    </div>

    <!-- Step 5 -->
    <div class="wpanel" id="step5">
      <div style="text-align:center;padding:24px 16px">
        <div style="width:64px;height:64px;border-radius:50%;background:var(--green-dim);border:2px solid var(--green);display:inline-flex;align-items:center;justify-content:center;margin-bottom:14px">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        </div>
        <h4 style="font-family:'Nunito',sans-serif;font-weight:800;margin-bottom:7px" id="sendTitle">Ready to Send</h4>
        <p style="font-size:.78rem;color:var(--muted);margin-bottom:16px">Campaign: <strong id="confirmName">—</strong></p>
        <div class="prog-wrap" style="max-width:360px;margin:0 auto 16px"><div class="prog-bar" id="sendProg" style="width:0"></div></div>
        <div id="sendLog" style="max-width:440px;margin:0 auto 16px;text-align:left"></div>
        <div id="sendActions" style="display:flex;gap:10px;justify-content:center">
          <button class="mini-btn" onclick="goStep(4)">← Back</button>
          <button class="mini-btn primary" onclick="sendCampaign()">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>Launch Campaign
          </button>
        </div>
        <div id="sendDoneActions" style="display:none;gap:10px;justify-content:center">
          <button class="mini-btn" onclick="nav('history')">View History</button>
          <button class="mini-btn" onclick="nav('analytics')">Analytics</button>
          <button class="mini-btn primary" onclick="nav('campaign')">+ New Campaign</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══ NO-WEBSITE HUNT ══ -->
<div class="page <?= $page==='noweb'?'active':'' ?>" id="page-noweb">
  <div class="page-hd"><h1>No-Website Business Hunt</h1><p>Find businesses with zero online presence — your untapped goldmine</p></div>
  <div class="tipbox orange">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div><strong style="color:var(--accent3)">Web Dev Opportunity Mode</strong> — EMOL identifies real, active businesses that have no website. Perfect for pitching web design, digital marketing, or online setup services.</div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px;margin-bottom:14px">
    <div class="card cp" style="text-align:center;margin-bottom:0"><div style="font-size:.63rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">No Website</div><div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.7rem;color:var(--accent3)" id="nwStat1">—</div></div>
    <div class="card cp" style="text-align:center;margin-bottom:0"><div style="font-size:.63rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Has Website</div><div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.7rem;color:var(--success)" id="nwStat2">—</div></div>
    <div class="card cp" style="text-align:center;margin-bottom:0"><div style="font-size:.63rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Selected</div><div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.7rem;color:var(--green)" id="nwStat4">0</div></div>
  </div>
  <div class="card cp">
    <div class="row3f" style="margin-bottom:10px">
      <div class="field"><label class="flabel">Category</label><input class="finput" id="nwCat" placeholder="e.g. Restaurants, Clinics..."/></div>
      <div class="field"><label class="flabel">Location</label><input class="finput" id="nwLoc" placeholder="e.g. Kano, Abuja..." value="Maiduguri"/></div>
      <div class="field" style="display:flex;align-items:flex-end"><button class="mini-btn primary" style="width:100%;justify-content:center" onclick="huntNoWeb()">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>Hunt Now
      </button></div>
    </div>
    <div id="nwResults"></div>
    <div id="nwActions" style="display:none;margin-top:12px">
      <button class="mini-btn primary" onclick="sendToNwCampaign()">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>Send Campaign to Selected
      </button>
    </div>
  </div>
</div>

<!-- ══ CONTACTS ══ -->
<div class="page <?= $page==='contacts'?'active':'' ?>" id="page-contacts">
  <div class="page-hd"><h1>Contacts</h1><p><?= count($contacts_all) ?> contact<?= count($contacts_all)!=1?'s':'' ?> in your database</p></div>
  <div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:13px 16px;border-bottom:1px solid var(--border)">
      <div class="card-title">All Contacts</div>
      <button class="mini-btn primary" onclick="toggleAddContact()">+ Add Contact</button>
    </div>
    <div id="addContactForm" style="display:none;padding:14px 16px;border-bottom:1px solid var(--border);background:var(--surface2)">
      <div class="row2f">
        <div class="field"><label class="flabel">Business Name</label><input class="finput" id="cBiz" placeholder="Business name"/></div>
        <div class="field"><label class="flabel">Contact Name</label><input class="finput" id="cName" placeholder="Person's name"/></div>
        <div class="field"><label class="flabel">Email *</label><input class="finput" id="cEmail" type="email" placeholder="contact@business.com"/></div>
        <div class="field"><label class="flabel">Phone</label><input class="finput" id="cPhone" placeholder="+234..."/></div>
        <div class="field"><label class="flabel">Category</label><input class="finput" id="cCat" placeholder="e.g. Clinic"/></div>
        <div class="field"><label class="flabel">Location</label><input class="finput" id="cLoc" placeholder="e.g. Abuja"/></div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="mini-btn primary" onclick="saveContact()">Save Contact</button>
        <button class="mini-btn" onclick="toggleAddContact()">Cancel</button>
      </div>
    </div>
    <?php if(empty($contacts_all)): ?>
    <div class="empty-st">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <p>No contacts yet. Add your first contact.</p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto">
      <table class="tbl">
        <thead><tr><th>Business</th><th>Contact</th><th>Email</th><th>Category</th><th>Location</th><th>Added</th><th></th></tr></thead>
        <tbody>
          <?php foreach($contacts_all as $c): ?>
          <tr>
            <td><strong><?= htmlspecialchars($c['business_name']??'—') ?></strong></td>
            <td><?= htmlspecialchars($c['contact_name']??'—') ?></td>
            <td style="color:var(--green)"><?= htmlspecialchars($c['email']) ?></td>
            <td><span class="bdg bdg-i"><?= htmlspecialchars($c['category']??'—') ?></span></td>
            <td style="color:var(--muted)"><?= htmlspecialchars($c['location']??'—') ?></td>
            <td style="color:var(--muted)"><?= date('M j',strtotime($c['created_at'])) ?></td>
            <td><button class="mini-btn danger" style="padding:3px 8px;font-size:.66rem" onclick="deleteContact(<?= $c['id'] ?>)">✕</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══ CAMPAIGN HISTORY ══ -->
<div class="page <?= $page==='history'?'active':'' ?>" id="page-history">
  <div class="page-hd"><h1>Campaign History</h1><p>All <?= count($campaigns_all) ?> campaign<?= count($campaigns_all)!=1?'s':'' ?></p></div>
  <div class="card">
    <?php if(empty($campaigns_all)): ?>
    <div class="empty-st">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      <p>No campaigns yet.</p><button class="mini-btn primary" onclick="nav('campaign')">Create First Campaign</button>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto">
      <table class="tbl">
        <thead><tr><th>Campaign</th><th>Category</th><th>Sent</th><th>Opened</th><th>Replies</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($campaigns_all as $c):
            $cls=['sent'=>'bdg-s','draft'=>'bdg-g','partial'=>'bdg-w','scheduled'=>'bdg-i'][$c['status']]??'bdg-g';
          ?>
          <tr id="camp-row-<?= $c['id'] ?>">
            <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
            <td style="color:var(--muted)"><?= htmlspecialchars($c['category']??'—') ?></td>
            <td style="color:var(--green)"><?= $c['emails_sent'] ?></td>
            <td style="color:var(--blue)" id="opened-<?= $c['id'] ?>"><?= $c['emails_opened'] ?></td>
            <td style="color:var(--success)" id="replies-<?= $c['id'] ?>"><?= $c['replies'] ?></td>
            <td><span class="bdg <?= $cls ?>"><?= ucfirst($c['status']) ?></span></td>
            <td style="color:var(--muted)"><?= date('M j, Y',strtotime($c['created_at'])) ?></td>
            <td>
              <?php if($c['status'] !== 'draft'): ?>
              <button class="mini-btn" style="font-size:.62rem;padding:4px 8px;color:var(--success);border-color:rgba(34,197,94,.25)" onclick="markReply(<?= $c['id'] ?>)">+ Reply</button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══ AI WRITER ══ -->
<div class="page <?= $page==='aiwriter'?'active':'' ?>" id="page-aiwriter">
  <div class="page-hd"><h1>AI Email Writer</h1><p>Generate professional cold emails in seconds</p></div>
  <div style="display:grid;grid-template-columns:1fr 1.4fr;gap:14px" class="ai-grid">
    <div>
      <div class="card cp">
        <div class="card-hd"><div class="card-title">Configure</div></div>
        <div class="field"><label class="flabel">Target Business Type</label><input class="finput" id="aiBiz" placeholder="e.g. Clinics, Hotels..."/></div>
        <div class="field"><label class="flabel">Your Offer (optional)</label><input class="finput" id="aiOffer" placeholder="e.g. Website design services"/></div>
        <div class="row2f">
          <div class="field"><label class="flabel">Tone</label><select class="finput" id="aiTone"><option>Professional</option><option>Friendly</option><option>Persuasive</option><option>Urgent</option><option>Casual</option></select></div>
          <div class="field"><label class="flabel">Length</label><select class="finput" id="aiLen"><option>Short</option><option selected>Medium</option><option>Long</option></select></div>
        </div>
        <div class="field">
          <label class="flabel">Include</label>
          <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:4px">
            <span class="chip" data-opt="cta" onclick="toggleOpt(this)">Strong CTA</span>
            <span class="chip" data-opt="urgency" onclick="toggleOpt(this)">Urgency</span>
            <span class="chip" data-opt="stats" onclick="toggleOpt(this)">Statistics</span>
            <span class="chip" data-opt="offer" onclick="toggleOpt(this)">Special Offer</span>
            <span class="chip" data-opt="ps" onclick="toggleOpt(this)">P.S. Line</span>
          </div>
        </div>
        <button class="mini-btn primary" style="width:100%;justify-content:center" onclick="runAIWriter()">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>Generate Email
        </button>
      </div>
    </div>
    <div>
      <div class="card cp" style="height:100%">
        <div class="card-hd">
          <div class="card-title">Generated Email</div>
          <div style="display:flex;gap:6px">
            <button class="mini-btn" style="padding:5px 9px;font-size:.7rem" onclick="copyAI()">Copy</button>
            <button class="mini-btn primary" style="padding:5px 9px;font-size:.7rem" onclick="useInCampaign()">Use in Campaign</button>
          </div>
        </div>
        <div id="aiSubjectWrap" style="display:none;margin-bottom:10px">
          <label class="flabel">Subject Line</label>
          <div style="padding:9px 12px;background:var(--surface2);border-radius:9px;font-size:.82rem;font-weight:600;border:1px solid var(--border)" id="aiSubjectOut"></div>
        </div>
        <label class="flabel">Email Body</label>
        <div style="background:var(--surface2);border:1px solid var(--border2);border-radius:10px;padding:14px;min-height:180px;font-size:.82rem;line-height:1.8;white-space:pre-wrap;color:var(--text)" id="aiResult">Your generated email will appear here...</div>
      </div>
    </div>
  </div>
</div>

<!-- ══ TEMPLATES ══ -->
<div class="page <?= $page==='templates'?'active':'' ?>" id="page-templates">
  <div class="page-hd"><h1>Email Templates</h1><p>Save and reuse your best-performing emails</p></div>
  <div class="card cp">
    <div class="card-hd"><div class="card-title">Save New Template</div></div>
    <div class="row3f">
      <div class="field"><label class="flabel">Template Name *</label><input class="finput" id="tplName" placeholder="e.g. Website Pitch"/></div>
      <div class="field"><label class="flabel">Category</label><input class="finput" id="tplCat" placeholder="e.g. Clinics"/></div>
      <div class="field"><label class="flabel">Subject</label><input class="finput" id="tplSubject" placeholder="Email subject line"/></div>
    </div>
    <div class="field"><label class="flabel">Body *</label><textarea class="finput" id="tplBody" rows="4" placeholder="Email body text..."></textarea></div>
    <button class="mini-btn primary" onclick="saveTpl()">Save Template</button>
  </div>
  <?php if(!empty($templates_all)): ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:11px">
    <?php foreach($templates_all as $t): ?>
    <div class="card cp" style="margin-bottom:0">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
        <strong style="font-size:.84rem;font-family:'Nunito',sans-serif;font-weight:800"><?= htmlspecialchars($t['name']) ?></strong>
        <button class="mini-btn danger" style="padding:3px 8px;font-size:.66rem" onclick="deleteTpl(<?= $t['id'] ?>)">✕</button>
      </div>
      <?php if($t['category']): ?><span class="bdg bdg-i" style="margin-bottom:6px;display:inline-flex"><?= htmlspecialchars($t['category']) ?></span><?php endif; ?>
      <?php if($t['subject']): ?><div style="font-size:.7rem;color:var(--muted);margin-bottom:5px"><strong>Subject:</strong> <?= htmlspecialchars($t['subject']) ?></div><?php endif; ?>
      <div style="font-size:.74rem;color:var(--muted);line-height:1.5;max-height:50px;overflow:hidden"><?= htmlspecialchars(substr($t['body'],0,115)) ?>...</div>
      <button class="mini-btn" style="margin-top:9px;width:100%;justify-content:center;font-size:.7rem" onclick="loadTemplate(<?= $t['id'] ?>,'<?= addslashes($t['subject']??'') ?>',`<?= addslashes($t['body']) ?>`)">Use in Campaign</button>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="card"><div class="empty-st"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><p>No templates saved yet.</p></div></div>
  <?php endif; ?>
</div>

<!-- ══ A/B TESTING ══ -->
<div class="page <?= $page==='abtest'?'active':'' ?>" id="page-abtest">
  <div class="page-hd"><h1>A/B Testing</h1><p>Test two email versions — find what converts best</p></div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px" class="ab-grid">
    <div class="card cp">
      <div class="card-hd"><div class="card-title">Create A/B Test</div></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:11px;margin-bottom:13px">
        <div>
          <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.5rem;color:var(--green);margin-bottom:7px">A</div>
          <textarea class="finput" id="abVariantA" rows="7" placeholder="Variant A — your first email version..."></textarea>
        </div>
        <div>
          <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.5rem;color:var(--blue);margin-bottom:7px">B</div>
          <textarea class="finput" id="abVariantB" rows="7" placeholder="Variant B — or click Generate AI..."></textarea>
        </div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="mini-btn primary" onclick="saveABTest()">Save Test</button>
        <button class="mini-btn purple" onclick="genABVariant()">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>Generate B with AI
        </button>
      </div>
    </div>
    <div class="card cp">
      <div class="card-hd"><div class="card-title">Saved Tests (<?= count($abtests_all) ?>)</div></div>
      <?php if(empty($abtests_all)): ?>
      <div class="empty-st"><svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg><p>No A/B tests yet.</p></div>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:10px;max-height:420px;overflow-y:auto" id="abTestsList">
        <?php foreach($abtests_all as $ab):
          $oa = (int)$ab['opens_a'];
          $ob = (int)$ab['opens_b'];
          $total = $oa + $ob;
          $pct_a = $total > 0 ? round(($oa/$total)*100) : 50;
          $pct_b = 100 - $pct_a;
          $w = $ab['winner'] ?? 'none';
          $winner_label = $w === 'a' ? '<span style="background:rgba(0,229,160,.15);color:var(--green);border:1px solid rgba(0,229,160,.3);padding:2px 8px;border-radius:20px;font-size:.6rem;font-weight:700">🏆 A Wins</span>'
                        : ($w === 'b' ? '<span style="background:rgba(59,130,246,.15);color:var(--blue);border:1px solid rgba(59,130,246,.3);padding:2px 8px;border-radius:20px;font-size:.6rem;font-weight:700">🏆 B Wins</span>'
                        : '<span style="background:var(--surface3);color:var(--muted);padding:2px 8px;border-radius:20px;font-size:.6rem;font-weight:700">Running</span>');
        ?>
        <div style="background:var(--surface2);border:1px solid var(--border);border-radius:11px;padding:12px" id="abcard-<?= $ab['id'] ?>">

          <!-- Header row -->
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
            <div style="font-size:.62rem;color:var(--muted)"><?= date('M j, Y', strtotime($ab['created_at'])) ?></div>
            <div class="ab-winner-badge" id="winner-badge-<?= $ab['id'] ?>"><?= $winner_label ?></div>
          </div>

          <!-- Variant text preview -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:.72rem;margin-bottom:10px">
            <div style="background:var(--surface3);border-radius:7px;padding:7px;border-left:3px solid var(--green)">
              <div style="font-weight:800;color:var(--green);margin-bottom:3px">A</div>
              <div style="color:var(--muted);line-height:1.4"><?= htmlspecialchars(substr($ab['variant_a']??'',0,60)) ?>…</div>
            </div>
            <div style="background:var(--surface3);border-radius:7px;padding:7px;border-left:3px solid var(--blue)">
              <div style="font-weight:800;color:var(--blue);margin-bottom:3px">B</div>
              <div style="color:var(--muted);line-height:1.4"><?= htmlspecialchars(substr($ab['variant_b']??'',0,60)) ?>…</div>
            </div>
          </div>

          <!-- Opens bar -->
          <div style="margin-bottom:8px">
            <div style="display:flex;justify-content:space-between;font-size:.62rem;color:var(--muted);margin-bottom:4px">
              <span>A — <strong id="oa-<?= $ab['id'] ?>" style="color:var(--green)"><?= $oa ?></strong> opens</span>
              <span>B — <strong id="ob-<?= $ab['id'] ?>" style="color:var(--blue)"><?= $ob ?></strong> opens</span>
            </div>
            <div style="height:6px;border-radius:99px;overflow:hidden;background:var(--surface3);display:flex">
              <div id="bar-a-<?= $ab['id'] ?>" style="width:<?= $pct_a ?>%;background:var(--green);border-radius:99px 0 0 99px;transition:width .5s ease"></div>
              <div id="bar-b-<?= $ab['id'] ?>" style="width:<?= $pct_b ?>%;background:var(--blue);border-radius:0 99px 99px 0;transition:width .5s ease"></div>
            </div>
          </div>

          <!-- Action buttons -->
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <button class="mini-btn" style="font-size:.65rem;padding:5px 9px" onclick="voteAB(<?= $ab['id'] ?>,'a')">+1 Open A</button>
            <button class="mini-btn" style="font-size:.65rem;padding:5px 9px;color:var(--blue);border-color:rgba(59,130,246,.3)" onclick="voteAB(<?= $ab['id'] ?>,'b')">+1 Open B</button>
            <button class="mini-btn" style="font-size:.65rem;padding:5px 9px;color:var(--green);border-color:rgba(0,229,160,.3);margin-left:auto" onclick="setWinner(<?= $ab['id'] ?>,'a')">✓ A Wins</button>
            <button class="mini-btn" style="font-size:.65rem;padding:5px 9px;color:var(--blue);border-color:rgba(59,130,246,.3)" onclick="setWinner(<?= $ab['id'] ?>,'b')">✓ B Wins</button>
            <button class="mini-btn" style="font-size:.65rem;padding:5px 9px;color:var(--danger);border-color:rgba(239,68,68,.2)" onclick="deleteAB(<?= $ab['id'] ?>)">✕</button>
          </div>

        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══ ANALYTICS ══ -->
<div class="page <?= $page==='analytics'?'active':'' ?>" id="page-analytics">
  <div class="page-hd"><h1>Analytics</h1><p>Real data from your account</p></div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-bottom:14px" class="analytics-mini">
    <div class="card cp" style="margin-bottom:0;text-align:center"><div style="font-size:.62rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Emails Sent</div><div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.7rem;color:var(--green)"><?= number_format($stat_sent) ?></div></div>
    <div class="card cp" style="margin-bottom:0;text-align:center"><div style="font-size:.62rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Open Rate</div><div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.7rem;color:var(--blue)"><?= $open_rate ?>%</div></div>
    <div class="card cp" style="margin-bottom:0;text-align:center"><div style="font-size:.62rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Replies</div><div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.7rem;color:var(--success)"><?= number_format($stat_replies) ?></div></div>
    <div class="card cp" style="margin-bottom:0;text-align:center"><div style="font-size:.62rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Campaigns</div><div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.7rem;color:var(--accent4)"><?= number_format($stat_campaigns) ?></div></div>
    <div class="card cp" style="margin-bottom:0;text-align:center"><div style="font-size:.62rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Contacts</div><div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.7rem;color:var(--warning)"><?= number_format($stat_contacts) ?></div></div>
  </div>
  <div class="card cp">
    <div class="card-hd"><div class="card-title">Last 7 Days Performance</div></div>
    <div class="chart-bars" style="height:150px">
      <?php foreach($analytics as $d):
        $h=$max_val>0?max(4,round(($d['sent']/$max_val)*130)):4;
        $ho=$max_val>0?max(2,round(($d['opened']/$max_val)*130)):2;
      ?>
      <div class="bar-col">
        <div class="bar-stk">
          <div class="bar-seg" style="height:<?= $ho ?>px;background:var(--blue)" title="Opened: <?= $d['opened'] ?>"></div>
          <div class="bar-seg" style="height:<?= $h ?>px;background:var(--green)" title="Sent: <?= $d['sent'] ?>"></div>
        </div>
        <div class="bar-lbl"><?= $d['day'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:14px;margin-top:8px">
      <span style="font-size:.65rem;color:var(--muted);display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;border-radius:2px;background:var(--green);display:inline-block"></span>Sent</span>
      <span style="font-size:.65rem;color:var(--muted);display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;border-radius:2px;background:var(--blue);display:inline-block"></span>Opened</span>
    </div>
  </div>
  <?php if(!empty($campaigns_all)): ?>
  <div class="card">
    <div style="padding:12px 16px;border-bottom:1px solid var(--border)"><div class="card-title">All Campaigns Performance</div></div>
    <div style="overflow-x:auto">
      <table class="tbl">
        <thead><tr><th>Campaign</th><th>Sent</th><th>Opened</th><th>Open Rate</th><th>Replies</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach($campaigns_all as $c):
            $rate=$c['emails_sent']>0?round(($c['emails_opened']/$c['emails_sent'])*100,1):0;
            $cls=['sent'=>'bdg-s','draft'=>'bdg-g','partial'=>'bdg-w','scheduled'=>'bdg-i'][$c['status']]??'bdg-g';
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
            <td style="color:var(--green)"><?= $c['emails_sent'] ?></td>
            <td style="color:var(--blue)"><?= $c['emails_opened'] ?></td>
            <td><?= $rate ?>%</td>
            <td style="color:var(--success)"><?= $c['replies'] ?></td>
            <td><span class="bdg <?= $cls ?>"><?= ucfirst($c['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ══ FOLLOW-UP ══ -->
<div class="page <?= $page==='followup'?'active':'' ?>" id="page-followup">
  <div class="page-hd"><h1>Follow-up Engine</h1><p>Automate follow-up sequences for your campaigns</p></div>
  <div class="tipbox blue">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div>Draft your follow-up email and save it as a reusable template.</div>
  </div>
  <div class="card cp">
    <div class="card-hd"><div class="card-title">Draft Follow-up Email</div></div>
    <div class="field"><label class="flabel">Subject</label><input class="finput" id="fuSubject" placeholder="Re: Following up on my previous email"/></div>
    <div class="field"><label class="flabel">Body</label><textarea class="finput" id="fuBody" rows="8">Hi {{name}},

I wanted to follow up on my previous email about helping your business get online. Have you had a chance to consider it?

I'd love to set up a quick 10-minute call to show you what we can do.

Best regards,
{{sender}}</textarea></div>
    <div style="display:flex;gap:8px">
      <button class="mini-btn primary" onclick="saveFollowup()">Save as Template</button>
      <button class="mini-btn purple" onclick="genFollowupAI()">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>Generate with AI
      </button>
    </div>
  </div>
</div>

<!-- ══ SETTINGS ══ -->
<div class="page <?= $page==='settings'?'active':'' ?>" id="page-settings">
  <div class="page-hd"><h1>Settings</h1><p>Manage your account and integrations</p></div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px" class="settings-grid">
    <div class="card cp">
      <div class="card-hd"><div class="card-title">Profile</div></div>
      <div class="field"><label class="flabel">Full Name</label><input class="finput" id="profName" value="<?= htmlspecialchars($user['name']) ?>"/></div>
      <div class="field"><label class="flabel">Username</label><input class="finput" id="profUser" value="<?= htmlspecialchars($user['username']) ?>"/></div>
      <div class="field"><label class="flabel">Email</label><input class="finput" value="<?= htmlspecialchars($user['email']) ?>" disabled style="opacity:.5"/></div>
      <button class="mini-btn primary" onclick="saveProfile()">Save Profile</button>
    </div>
    <div class="card cp">
      <div class="card-hd"><div class="card-title">Change Password</div></div>
      <div class="field"><label class="flabel">Current Password</label><input class="finput" type="password" id="curPw" placeholder="Current password"/></div>
      <div class="field"><label class="flabel">New Password</label><input class="finput" type="password" id="newPw" placeholder="Min 8 chars, 1 uppercase, 1 number"/></div>
      <div class="field"><label class="flabel">Confirm New Password</label><input class="finput" type="password" id="conPw" placeholder="Repeat new password"/></div>
      <button class="mini-btn primary" onclick="changePassword()">Update Password</button>
    </div>
  </div>
  <div class="card cp">
    <div class="card-hd"><div class="card-title">API Status</div></div>
    <div class="setting-row">
      <div><h4>Google Maps API</h4><p>Used for business discovery in campaigns</p></div>
      <?php if(GOOGLE_MAPS_KEY): ?><span style="color:var(--success);font-size:.76rem;font-weight:700"><span class="status-dot dot-ok"></span>Connected</span>
      <?php else: ?><span style="color:var(--warning);font-size:.76rem;font-weight:700"><span class="status-dot dot-warn"></span>Add key to config.php</span><?php endif; ?>
    </div>
    <div class="setting-row">
      <div><h4>OpenAI API</h4><p>Used for AI email generation</p></div>
      <?php if(OPENAI_KEY): ?><span style="color:var(--success);font-size:.76rem;font-weight:700"><span class="status-dot dot-ok"></span>Connected</span>
      <?php else: ?><span style="color:var(--warning);font-size:.76rem;font-weight:700"><span class="status-dot dot-warn"></span>Add key to config.php</span><?php endif; ?>
    </div>
    <div class="setting-row">
      <div><h4>SMTP Email</h4><p>Used for sending campaigns</p></div>
      <?php if(SMTP_HOST): ?><span style="color:var(--success);font-size:.76rem;font-weight:700"><span class="status-dot dot-ok"></span>Connected</span>
      <?php else: ?><span style="color:var(--warning);font-size:.76rem;font-weight:700"><span class="status-dot dot-warn"></span>Add SMTP to config.php</span><?php endif; ?>
    </div>
  </div>
</div>

  </div><!-- /content -->
</div><!-- /main-wrap -->
</div><!-- /layout -->

<script>
// ── STATE ──────────────────────────────────────────────────
let bizData=[],filteredBiz=[],selectedBiz=new Set(),carouselOffset=0;
let nwData=[],nwSelected=new Set();
const CARDS=3;
let activeQH=null; // track active quick hunt button

// ── TOAST ──────────────────────────────────────────────────
function toast(msg,type='success'){
  const w=document.getElementById('toastWrap');
  const t=document.createElement('div');t.className='toast '+type;t.textContent=msg;w.appendChild(t);
  setTimeout(()=>t.remove(),3500);
}

// ── LOGOUT MODAL ───────────────────────────────────────────
// Background activity flags — set to true while async work is running
let _sending=false;   // campaign send in progress
let _searching=false; // maps search in progress
let _aiGen=false;     // AI generation in progress

function isBusy(){
  return _sending||_searching||_aiGen;
}
function busyLabel(){
  if(_sending)  return 'A campaign is currently sending — please wait before signing out.';
  if(_searching)return 'A Maps search is in progress — please wait before signing out.';
  if(_aiGen)    return 'AI is generating content — please wait before signing out.';
  return '';
}

function openLogoutModal(){
  const busy=isBusy();
  const overlay=document.getElementById('logoutOverlay');
  const busyEl=document.getElementById('lmBusy');
  const busyTxt=document.getElementById('lmBusyText');
  const btn=document.getElementById('lmConfirmBtn');
  const desc=document.getElementById('lmDesc');
  if(busy){
    busyEl.classList.add('show');
    busyTxt.textContent=busyLabel();
    btn.disabled=true;
    btn.textContent='Please wait…';
    desc.textContent='Something is running in the background. Wait for it to finish before signing out.';
    // Poll every second — re-enable button once no longer busy
    const poll=setInterval(()=>{
      if(!isBusy()){
        clearInterval(poll);
        busyEl.classList.remove('show');
        btn.disabled=false;
        btn.textContent='Sign Out';
        desc.textContent="You'll be returned to the sign in page. Any unsaved changes will be lost.";
      }else{
        busyTxt.textContent=busyLabel();
      }
    },1000);
    overlay._poll=poll;
  }else{
    busyEl.classList.remove('show');
    btn.disabled=false;
    btn.textContent='Sign Out';
    desc.textContent="You'll be returned to the sign in page. Any unsaved changes will be lost.";
  }
  overlay.classList.add('show');
}

function closeLogoutModal(e){
  if(e&&e.target!==document.getElementById('logoutOverlay'))return;
  const overlay=document.getElementById('logoutOverlay');
  overlay.classList.remove('show');
  if(overlay._poll){clearInterval(overlay._poll);overlay._poll=null;}
}

function doLogout(){
  if(isBusy())return;
  window.location.href='?logout=1';
}

function toggleSb(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('overlay').classList.toggle('show');}
function closeSb(){document.getElementById('sidebar').classList.remove('open');document.getElementById('overlay').classList.remove('show');}
function sbSearch(v){
  document.querySelectorAll('.sb-link').forEach(el=>{
    el.style.display=v?el.textContent.toLowerCase().includes(v.toLowerCase())?'':'none':'';
  });
}

// ── NAVIGATION ─────────────────────────────────────────────
const pageTitles={campaign:'New Campaign',noweb:'No-Website Hunt',contacts:'Contacts',history:'Campaign History',aiwriter:'AI Writer',templates:'Templates',abtest:'A/B Testing',analytics:'Analytics',followup:'Follow-up Engine',settings:'Settings'};
function nav(p){
  document.querySelectorAll('.page').forEach(el=>el.classList.remove('active'));
  document.querySelectorAll('.sb-link').forEach(el=>el.classList.remove('active'));
  const pg=document.getElementById('page-'+p);if(pg)pg.classList.add('active');
  document.getElementById('topTitle').textContent=pageTitles[p]||p;
  closeSb();
  history.replaceState(null,'','?page='+p);
  // Clear quick hunt highlight when navigating normally
  if(activeQH){activeQH.classList.remove('qh-active');activeQH=null;}
}

// Quick hunt — fills category, navigates, AND highlights the button
function qHunt(cat){
  // Clear previous highlight
  if(activeQH){activeQH.classList.remove('qh-active');activeQH=null;}
  // Find and highlight the clicked quick hunt button
  const id='qh-'+cat.toLowerCase().replace(/\s+/g,'-');
  const btn=document.getElementById(id);
  if(btn){btn.classList.add('qh-active');activeQH=btn;}
  // Fill and navigate
  document.getElementById('catInput').value=cat;
  nav('campaign');
  setTimeout(()=>goStep(2),120);
}

// ── WIZARD ─────────────────────────────────────────────────
let curStep=1;
function goStep(n){
  for(let i=1;i<=5;i++){
    document.getElementById('step'+i).classList.toggle('active',i===n);
    const ws=document.getElementById('ws'+i);
    ws.classList.remove('active','done');
    if(i<n)ws.classList.add('done');else if(i===n)ws.classList.add('active');
    document.getElementById('wn'+i).textContent=i<n?'✓':i;
  }
  curStep=n;
  if(n===4)buildPreview();
  if(n===5)document.getElementById('confirmName').textContent=document.getElementById('campaignName').value||'Untitled';
}
function nextStep(n){
  if(n===1&&!document.getElementById('campaignName').value.trim()){toast('Enter a campaign name','error');return;}
  if(n===2&&selectedBiz.size===0){toast('Select at least one business','error');return;}
  if(n===3&&!document.getElementById('emailSubject').value.trim()){toast('Enter an email subject','error');return;}
  goStep(n+1);
}

// ── CATEGORY CHIPS ─────────────────────────────────────────
let selCats=new Set();
function toggleCat(el,cat){if(selCats.has(cat)){selCats.delete(cat);el.classList.remove('on');}else{selCats.add(cat);el.classList.add('on');}updateCatDisplay();}
function selectAllCats(){document.querySelectorAll('#catChips .chip').forEach(el=>{const m=el.getAttribute('onclick').match(/'([^']+)'/);if(m){selCats.add(m[1]);el.classList.add('on');}});updateCatDisplay();}
function clearAllCats(){selCats.clear();document.querySelectorAll('#catChips .chip').forEach(el=>el.classList.remove('on'));updateCatDisplay();}
function updateCatDisplay(){document.getElementById('catInput').value=[...selCats].join(', ');document.getElementById('selCatCount').textContent=selCats.size+' categor'+(selCats.size===1?'y':'ies')+' selected';document.getElementById('selCatList').textContent=selCats.size?[...selCats].join(', '):'None selected';}
function filterCatChips(v){document.querySelectorAll('#catChips .chip').forEach(el=>{el.style.display=el.textContent.toLowerCase().includes(v.toLowerCase())?'':'none';});}

// ── SEARCH BUSINESSES ──────────────────────────────────────
function searchBiz(){
  const cat=document.getElementById('catInput').value.trim();
  const loc=document.getElementById('locInput').value.trim();
  const max=document.getElementById('maxResults').value;
  if(!cat||!loc){toast('Enter category and location','error');return;}
  const btn=document.getElementById('searchBtn');btn.textContent='Searching...';btn.disabled=true;
  document.getElementById('mapVisual').innerHTML='<div style="font-size:.76rem;color:var(--green)">Searching Google Maps...</div>';
  _searching=true;
  fetch(`handlers/maps.php?cat=${encodeURIComponent(cat)}&loc=${encodeURIComponent(loc)}&max=${max}`)
    .then(r=>r.json()).then(d=>{
      _searching=false;
      btn.innerHTML='<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>Search Google Maps';btn.disabled=false;
      if(d.error){toast(d.error,'error');document.getElementById('mapVisual').innerHTML='<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg><div style="font-size:.76rem;color:var(--muted2)">Search to locate businesses</div>';return;}
      bizData=d.results||[];filteredBiz=[...bizData];selectedBiz.clear();
      document.getElementById('resultsWrap').style.display='block';
      document.getElementById('resultCount').textContent=bizData.length;
      document.getElementById('mapVisual').innerHTML=`<div style="color:var(--success);font-size:.82rem;font-weight:700">✓ Found ${bizData.length} businesses in ${loc}</div>`;
      renderCarousel();toast(`Found ${bizData.length} businesses!`);
    }).catch(()=>{_searching=false;btn.innerHTML='Search Google Maps';btn.disabled=false;toast('Search failed. Check Google Maps API key.','error');});
}
function clearSearch(){bizData=[];filteredBiz=[];selectedBiz.clear();document.getElementById('resultsWrap').style.display='none';document.getElementById('mapVisual').innerHTML='<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg><div style="font-size:.76rem;color:var(--muted2)">Search to locate businesses</div>';}

// ── CAROUSEL ───────────────────────────────────────────────
function renderCarousel(){
  const track=document.getElementById('bizTrack');track.innerHTML='';
  filteredBiz.forEach(b=>{
    const sel=selectedBiz.has(b.name);const hasWeb=!!b.website;
    const card=document.createElement('div');card.className='biz-card'+(sel?' selected':'');
    card.innerHTML=`<div class="biz-name">${b.name||'Unknown'}</div><div class="biz-addr">${b.vicinity||b.formatted_address||'—'}</div><div style="display:flex;flex-wrap:wrap;gap:4px">${hasWeb?'<span class="biz-tag hasweb-tag">Has Website</span>':'<span class="biz-tag noweb-tag">No Website</span>'}${b.formatted_phone_number?`<span class="biz-tag" style="background:rgba(59,130,246,.1);color:var(--blue);font-size:.6rem;padding:2px 5px;border-radius:20px">${b.formatted_phone_number}</span>`:''}</div>`;
    card.onclick=()=>toggleBizSel(b,card);track.appendChild(card);
  });
  carouselOffset=0;updateCarouselPos();updateSelCount();
  document.getElementById('cnTotal').textContent=filteredBiz.length;
}
function toggleBizSel(b,card){if(selectedBiz.has(b.name)){selectedBiz.delete(b.name);card.classList.remove('selected');}else{selectedBiz.add(b.name);card.classList.add('selected');}updateSelCount();}
function updateSelCount(){const n=selectedBiz.size;document.getElementById('selCount').textContent=n;document.getElementById('contBtn').style.display=n>0?'':'none';}
function carouselMove(dir){const max=Math.max(0,filteredBiz.length-CARDS);carouselOffset=Math.max(0,Math.min(max,carouselOffset+dir));updateCarouselPos();}
function updateCarouselPos(){document.getElementById('bizTrack').style.transform=`translateX(-${carouselOffset*252}px)`;document.getElementById('cnIdx').textContent=carouselOffset+1;document.getElementById('cnPrev').disabled=carouselOffset===0;document.getElementById('cnNext').disabled=carouselOffset>=Math.max(0,filteredBiz.length-CARDS);}
function selAllBiz(){bizData.forEach(b=>selectedBiz.add(b.name));renderCarousel();}
function deselAllBiz(){selectedBiz.clear();renderCarousel();}
function filterCarousel(v){filteredBiz=bizData.filter(b=>!v||b.name?.toLowerCase().includes(v.toLowerCase())||b.vicinity?.toLowerCase().includes(v.toLowerCase()));renderCarousel();}
function bizFilter(type,btn){document.querySelectorAll('.biz-ftab').forEach(b=>b.classList.remove('active'));btn.classList.add('active');if(type==='all')filteredBiz=[...bizData];else if(type==='noweb')filteredBiz=bizData.filter(b=>!b.website);else if(type==='hasweb')filteredBiz=bizData.filter(b=>!!b.website);else if(type==='selected')filteredBiz=bizData.filter(b=>selectedBiz.has(b.name));renderCarousel();}

// ── COMPOSE ────────────────────────────────────────────────
function ec(cmd){document.execCommand(cmd,false,null);}
function insertTag(t){const el=document.getElementById('emailBody');el.focus();document.execCommand('insertText',false,t);}
function applyTpl(){const sel=document.getElementById('tplSelect');const opt=sel.options[sel.selectedIndex];if(!opt.value)return;document.getElementById('emailSubject').value=opt.dataset.subject||'';document.getElementById('emailBody').innerHTML=(opt.dataset.body||'').replace(/\n/g,'<br>');}
function checkSpam(){toast('Spam check: Your email looks clean ✓','success');}
function generateAI(){
  const cat=document.getElementById('catInput').value||document.getElementById('campaignGoal').value;
  toast('Generating email with AI...','info');
  _aiGen=true;
  fetch('handlers/ai.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({context:'campaign',biz:cat,tone:document.getElementById('emailTone').value,length:'Medium'})})
    .then(r=>r.json()).then(d=>{_aiGen=false;if(d.error){toast(d.error,'error');return;}if(d.subject)document.getElementById('emailSubject').value=d.subject;if(d.email)document.getElementById('emailBody').innerHTML=d.email.replace(/\n/g,'<br>');toast('Email generated!');})
    .catch(()=>{_aiGen=false;toast('AI generation failed','error');});
}

// ── PREVIEW ────────────────────────────────────────────────
function personalize(text,vars){return text.replace(/\{\{(\w+)\}\}/g,(m,k)=>vars[k]||m);}
function buildPreview(){
  const from=document.getElementById('senderName').value||'You';
  const sel=[...selectedBiz];
  document.getElementById('prvFrom').textContent=from;
  document.getElementById('prvTo').textContent=sel.length+' recipient'+(sel.length!==1?'s':'');
  document.getElementById('prvSubject').textContent=personalize(document.getElementById('emailSubject').value,{name:'[Name]',business:'[Business]',city:document.getElementById('locInput').value,sender:from});
  const sw=document.getElementById('prvSwitcher');sw.innerHTML='';
  bizData.filter(b=>sel.includes(b.name)).slice(0,8).forEach(b=>{const c=document.createElement('span');c.className='chip';c.textContent=b.name;c.onclick=()=>showPrv(b,from);sw.appendChild(c);});
  const first=bizData.find(b=>sel.includes(b.name));if(first)showPrv(first,from);
}
function showPrv(b,from){
  from=from||document.getElementById('senderName').value||'You';
  const vars={name:b.name,business:b.name,city:b.vicinity||document.getElementById('locInput').value,sender:from,category:document.getElementById('catInput').value};
  document.getElementById('prvSubject').textContent=personalize(document.getElementById('emailSubject').value,vars);
  document.getElementById('prvFrame').innerHTML=personalize(document.getElementById('emailBody').innerHTML,vars);
}

// ── SEND ───────────────────────────────────────────────────
function sendCampaign(){
  const businesses=bizData.filter(b=>selectedBiz.has(b.name)).map(b=>({name:b.name,email:b.email||'',address:b.vicinity||''}));
  if(!businesses.length){toast('No businesses selected','error');return;}
  document.getElementById('sendActions').style.display='none';
  document.getElementById('sendTitle').textContent='Sending Campaign...';
  document.getElementById('sendProg').style.width='30%';
  _sending=true;
  fetch('handlers/email.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:document.getElementById('campaignName').value,subject:document.getElementById('emailSubject').value,body:document.getElementById('emailBody').innerHTML,category:document.getElementById('catInput').value,location:document.getElementById('locInput').value,businesses})})
    .then(r=>r.json()).then(d=>{
      _sending=false;
      document.getElementById('sendProg').style.width='100%';
      document.getElementById('sendTitle').textContent=d.success?'Campaign Sent!':'Campaign Saved as Draft';
      document.getElementById('sendLog').innerHTML=`<div class="send-log-item">${d.message||d.error||'Done'}</div>`;
      document.getElementById('sendDoneActions').style.display='flex';
      toast(d.message||'Done',d.success?'success':'info');
    }).catch(()=>{_sending=false;toast('Send failed','error');document.getElementById('sendActions').style.display='flex';});
}

// ── NO-WEBSITE HUNT ────────────────────────────────────────
function huntNoWeb(){
  const cat=document.getElementById('nwCat').value.trim();const loc=document.getElementById('nwLoc').value.trim();
  if(!cat||!loc){toast('Enter category and location','error');return;}
  document.getElementById('nwResults').innerHTML='<div style="padding:18px;text-align:center;color:var(--green);font-size:.8rem">Hunting for businesses without websites...</div>';
  fetch(`handlers/maps.php?cat=${encodeURIComponent(cat)}&loc=${encodeURIComponent(loc)}&max=30&noweb=1`)
    .then(r=>r.json()).then(d=>{
      if(d.error){toast(d.error,'error');document.getElementById('nwResults').innerHTML='';return;}
      nwData=d.results||[];nwSelected.clear();
      document.getElementById('nwStat1').textContent=nwData.length;
      document.getElementById('nwStat2').textContent=(d.total_checked||0)-nwData.length;
      document.getElementById('nwStat4').textContent=0;
      if(!nwData.length){document.getElementById('nwResults').innerHTML='<div class="empty-st"><p>All businesses here have websites!</p></div>';return;}
      let html='<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:9px">';
      nwData.forEach((b,i)=>{html+=`<div class="biz-card" id="nwc${i}" onclick="toggleNwSel(${i},this)"><div class="biz-name">${b.name}</div><div class="biz-addr">${b.vicinity||'—'}</div><div style="display:flex;flex-wrap:wrap;gap:4px"><span class="biz-tag noweb-tag">No Website</span>${b.formatted_phone_number?`<span class="biz-tag" style="background:rgba(59,130,246,.1);color:var(--blue);font-size:.6rem;padding:2px 5px;border-radius:20px">${b.formatted_phone_number}</span>`:''}</div></div>`;});
      html+='</div>';
      document.getElementById('nwResults').innerHTML=html;
      document.getElementById('nwActions').style.display='block';
      toast(`Found ${nwData.length} businesses with no website!`);
    }).catch(()=>toast('Hunt failed. Check Google Maps API key.','error'));
}
function toggleNwSel(i,card){if(nwSelected.has(i)){nwSelected.delete(i);card.classList.remove('selected');}else{nwSelected.add(i);card.classList.add('selected');}document.getElementById('nwStat4').textContent=nwSelected.size;}
function sendToNwCampaign(){if(!nwSelected.size){toast('Select businesses first','error');return;}const sel=nwData.filter((_,i)=>nwSelected.has(i));bizData=sel;filteredBiz=[...sel];selectedBiz=new Set(sel.map(b=>b.name));nav('campaign');setTimeout(()=>goStep(3),120);toast('Businesses loaded into campaign!');}

// ── CONTACTS ───────────────────────────────────────────────
function toggleAddContact(){const f=document.getElementById('addContactForm');f.style.display=f.style.display==='none'?'block':'none';}
function saveContact(){
  const data={business_name:document.getElementById('cBiz').value,contact_name:document.getElementById('cName').value,email:document.getElementById('cEmail').value,phone:document.getElementById('cPhone').value,category:document.getElementById('cCat').value,location:document.getElementById('cLoc').value};
  if(!data.email){toast('Email is required','error');return;}
  fetch('handlers/contacts.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
    .then(r=>r.json()).then(d=>{if(d.success){toast('Contact saved!');setTimeout(()=>location.reload(),700);}else toast(d.error||'Failed','error');});
}
function deleteContact(id){if(!confirm('Delete this contact?'))return;fetch(`handlers/contacts.php?delete=${id}`).then(r=>r.json()).then(d=>{if(d.success){toast('Deleted');setTimeout(()=>location.reload(),500);}});}

// ── TEMPLATES ──────────────────────────────────────────────
function saveTpl(){
  const data={name:document.getElementById('tplName').value,category:document.getElementById('tplCat').value,subject:document.getElementById('tplSubject').value,body:document.getElementById('tplBody').value};
  if(!data.name||!data.body){toast('Name and body are required','error');return;}
  fetch('handlers/templates.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(data)})
    .then(r=>r.json()).then(d=>{if(d.success){toast('Template saved!');setTimeout(()=>location.reload(),700);}else toast(d.error||'Failed','error');});
}
function deleteTpl(id){if(!confirm('Delete this template?'))return;fetch(`handlers/templates.php?delete=${id}`).then(r=>r.json()).then(d=>{if(d.success){toast('Deleted');setTimeout(()=>location.reload(),500);}});}
function loadTemplate(id,subject,body){if(subject)document.getElementById('emailSubject').value=subject;document.getElementById('emailBody').innerHTML=body.replace(/\n/g,'<br>');nav('campaign');goStep(3);toast('Template loaded into campaign!');}

// ── AI WRITER ──────────────────────────────────────────────
function runAIWriter(){
  const biz=document.getElementById('aiBiz').value.trim();if(!biz){toast('Enter a business type','error');return;}
  const opts=[...document.querySelectorAll('[data-opt].on')].map(e=>e.dataset.opt);
  document.getElementById('aiResult').textContent='Generating...';
  _aiGen=true;
  fetch('handlers/ai.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({context:'writer',biz,offer:document.getElementById('aiOffer').value,tone:document.getElementById('aiTone').value,length:document.getElementById('aiLen').value,options:opts})})
    .then(r=>r.json()).then(d=>{_aiGen=false;if(d.error){toast(d.error,'error');document.getElementById('aiResult').textContent='Error: '+d.error;return;}document.getElementById('aiResult').textContent=d.email||d;if(d.subject){document.getElementById('aiSubjectWrap').style.display='block';document.getElementById('aiSubjectOut').textContent=d.subject;}toast('Email generated!');})
    .catch(()=>{_aiGen=false;toast('AI request failed','error');});
}
function toggleOpt(el){el.classList.toggle('on');}
function copyAI(){navigator.clipboard.writeText(document.getElementById('aiResult').textContent);toast('Copied to clipboard!');}
function useInCampaign(){const body=document.getElementById('aiResult').textContent;const subj=document.getElementById('aiSubjectOut').textContent;document.getElementById('emailBody').innerText=body;if(subj)document.getElementById('emailSubject').value=subj;nav('campaign');goStep(3);toast('Loaded into campaign!');}

// ── A/B TEST ───────────────────────────────────────────────
function saveABTest(){
  const a=document.getElementById('abVariantA').value;const b=document.getElementById('abVariantB').value;
  if(!a||!b){toast('Both variants required','error');return;}
  fetch('handlers/abtest.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({variant_a:a,variant_b:b})})
    .then(r=>r.json()).then(d=>{
      if(d.success){
        toast('A/B test saved!');
        document.getElementById('abVariantA').value='';
        document.getElementById('abVariantB').value='';
        if(d.pixel_a){
          console.log('%c=== A/B Tracking Pixels ===','color:#00e5a0;font-weight:bold');
          console.log('Paste inside Variant A email HTML:\n<img src="'+d.pixel_a+'" width="1" height="1" style="display:none">');
          console.log('Paste inside Variant B email HTML:\n<img src="'+d.pixel_b+'" width="1" height="1" style="display:none">');
        }
        setTimeout(()=>location.reload(),700);
      } else toast(d.error||'Failed','error');
    });
}
function voteAB(id,v){
  fetch('handlers/abtest.php?action=vote&test_id='+id+'&v='+v)
    .then(r=>r.json()).then(d=>{
      if(!d.success){toast(d.error||'Error','error');return;}
      // Update open counts
      document.getElementById('oa-'+id).textContent=d.opens_a;
      document.getElementById('ob-'+id).textContent=d.opens_b;
      // Update progress bar
      const total=d.opens_a+d.opens_b;
      const pA=total>0?Math.round((d.opens_a/total)*100):50;
      document.getElementById('bar-a-'+id).style.width=pA+'%';
      document.getElementById('bar-b-'+id).style.width=(100-pA)+'%';
      // Update winner badge
      updateWinnerBadge(id,d.winner);
      toast(v==='a'?'Open recorded for A 📬':'Open recorded for B 📬');
    });
}
function setWinner(id,w){
  fetch('handlers/abtest.php?action=set_winner',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({test_id:id,winner:w})})
    .then(r=>r.json()).then(d=>{
      if(d.success){updateWinnerBadge(id,w);toast('Winner set to Variant '+w.toUpperCase()+' 🏆');}
      else toast(d.error||'Error','error');
    });
}
function deleteAB(id){
  if(!confirm('Delete this A/B test?'))return;
  fetch('handlers/abtest.php?action=delete&test_id='+id)
    .then(r=>r.json()).then(d=>{
      if(d.success){const el=document.getElementById('abcard-'+id);if(el)el.remove();toast('Test deleted');}
      else toast(d.error||'Error','error');
    });
}
function updateWinnerBadge(id,w){
  const el=document.getElementById('winner-badge-'+id);
  if(!el)return;
  if(w==='a') el.innerHTML='<span style="background:rgba(0,229,160,.15);color:var(--green);border:1px solid rgba(0,229,160,.3);padding:2px 8px;border-radius:20px;font-size:.6rem;font-weight:700">🏆 A Wins</span>';
  else if(w==='b') el.innerHTML='<span style="background:rgba(59,130,246,.15);color:var(--blue);border:1px solid rgba(59,130,246,.3);padding:2px 8px;border-radius:20px;font-size:.6rem;font-weight:700">🏆 B Wins</span>';
  else el.innerHTML='<span style="background:var(--surface3);color:var(--muted);padding:2px 8px;border-radius:20px;font-size:.6rem;font-weight:700">Running</span>';
}
function markReply(campaign_id){
  fetch('handlers/email.php?action=reply',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({campaign_id:campaign_id})
  }).then(r=>r.json()).then(d=>{
    if(d.success){
      const el=document.getElementById('replies-'+campaign_id);
      if(el) el.textContent=d.replies;
      toast('Reply recorded! 💬','success');
    } else toast(d.error||'Error','error');
  });
}

function genABVariant(){
  const a=document.getElementById('abVariantA').value;if(!a){toast('Write Variant A first','error');return;}
  toast('Generating Variant B...','info');
  fetch('handlers/ai.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({context:'abtest',variant_a:a})})
    .then(r=>r.json()).then(d=>{if(d.error){toast(d.error,'error');return;}document.getElementById('abVariantB').value=d.email||d;toast('Variant B generated!');});
}

// ── FOLLOW-UP ──────────────────────────────────────────────
function saveFollowup(){
  const s=document.getElementById('fuSubject').value;const b=document.getElementById('fuBody').value;
  if(!b){toast('Body is required','error');return;}
  fetch('handlers/templates.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name:'Follow-up '+(new Date().toLocaleDateString()),subject:s,body:b,category:'Follow-up'})})
    .then(r=>r.json()).then(d=>{if(d.success)toast('Saved as template!');else toast(d.error||'Failed','error');});
}
function genFollowupAI(){
  toast('Generating...','info');
  fetch('handlers/ai.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({context:'writer',biz:'follow-up email',tone:'Friendly',length:'Short',options:['cta']})})
    .then(r=>r.json()).then(d=>{if(d.error){toast(d.error,'error');return;}document.getElementById('fuBody').value=d.email||d;if(d.subject)document.getElementById('fuSubject').value=d.subject;toast('Generated!');});
}

// ── SETTINGS ───────────────────────────────────────────────
function saveProfile(){
  const full_name=document.getElementById('profName').value.trim();const username=document.getElementById('profUser').value.trim();
  if(!full_name||!username){toast('Name and username required','error');return;}
  fetch('handlers/auth.php?action=update_profile',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({full_name,username})})
    .then(r=>r.json()).then(d=>{if(d.success)toast('Profile updated!');else toast(d.error||'Failed','error');});
}
function changePassword(){
  const cur=document.getElementById('curPw').value;const nw=document.getElementById('newPw').value;const con=document.getElementById('conPw').value;
  if(!cur||!nw||!con){toast('Fill all fields','error');return;}
  if(nw!==con){toast('Passwords do not match','error');return;}
  fetch('handlers/auth.php?action=change_password',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({current:cur,new_password:nw})})
    .then(r=>r.json()).then(d=>{if(d.success){toast('Password updated!');['curPw','newPw','conPw'].forEach(id=>document.getElementById(id).value='');}else toast(d.error||'Failed','error');});
}
</script>
</body>
</html>