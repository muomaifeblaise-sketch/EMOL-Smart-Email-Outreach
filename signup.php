<?php
require_once __DIR__ . '/handlers/auth.php';

if (Auth::check()) {
    header('Location: dashboard.php');
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth   = new Auth();
    $result = $auth->register(
        trim($_POST['full_name'] ?? ''),
        trim($_POST['username']  ?? ''),
        trim($_POST['email']     ?? ''),
        $_POST['password']       ?? ''
    );
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// OAuth error passed back via GET
if (empty($error) && !empty($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>EMOL — Create Account</title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>
<style>
:root{
  --bg:#0f0f0f;
  --surface:#181818;
  --surface2:#222;
  --surface3:#2a2a2a;
  --green:#00e5a0;
  --green-dim:rgba(0,229,160,.1);
  --green-glow:rgba(0,229,160,.18);
  --blue:#3b82f6;
  --text:#efefef;
  --muted:#777;
  --muted2:#444;
  --border:rgba(255,255,255,.07);
  --border2:rgba(255,255,255,.12);
  --danger:#ef4444;
  --success:#22c55e;
}
*{box-sizing:border-box;margin:0;padding:0}
body{
  min-height:100vh;
  background:var(--bg);
  font-family:'DM Sans',sans-serif;
  color:var(--text);
  display:flex;
  align-items:center;
  justify-content:center;
  padding:20px;
  position:relative;
  overflow-x:hidden;
  overflow-y:auto;
}
body::before{
  content:'';position:fixed;inset:0;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
  pointer-events:none;z-index:0;opacity:.5;
}
.glow{position:fixed;border-radius:50%;filter:blur(120px);pointer-events:none;z-index:0;}
.g1{width:500px;height:500px;background:rgba(59,130,246,.05);top:-150px;right:-100px;}
.g2{width:400px;height:400px;background:rgba(0,229,160,.06);bottom:-100px;left:-100px;}

.card{
  position:relative;z-index:1;
  width:100%;max-width:440px;
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:16px;
  padding:40px 36px;
  animation:fadeUp .5s ease;
  box-shadow:0 24px 64px rgba(0,0,0,.5);
}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.card::before{
  content:'';position:absolute;top:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent,rgba(0,229,160,.3),transparent);
  border-radius:16px 16px 0 0;
}
.logo-wrap{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:6px;}
.logo-icon{width:32px;height:32px;border-radius:9px;background:linear-gradient(135deg,var(--green),var(--blue));display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.logo{font-family:'Nunito',sans-serif;font-weight:900;font-size:1.8rem;letter-spacing:-1px;background:linear-gradient(135deg,var(--green),var(--blue));-webkit-background-clip:text;background-clip:text;color:transparent;}
.tagline{text-align:center;color:var(--muted);font-size:.75rem;margin-bottom:32px;letter-spacing:.04em;}
h2{font-size:1.1rem;font-weight:700;margin-bottom:3px;color:var(--text);}
.sub{color:var(--muted);font-size:.8rem;margin-bottom:20px;}

/* ── Google Button ── */
.google-btn{
  display:flex;align-items:center;justify-content:center;gap:10px;
  width:100%;padding:11px 16px;
  background:var(--surface2);
  border:1px solid var(--border2);
  border-radius:10px;
  color:var(--text);
  font-family:'DM Sans',sans-serif;
  font-size:.88rem;font-weight:600;
  cursor:pointer;
  text-decoration:none;
  transition:all .2s;
  margin-bottom:18px;
}
.google-btn:hover{
  background:var(--surface3);
  border-color:rgba(255,255,255,.22);
  transform:translateY(-1px);
  box-shadow:0 6px 20px rgba(0,0,0,.3);
}
.google-btn:active{transform:translateY(0);}
.google-icon{width:18px;height:18px;flex-shrink:0;}

/* ── Divider ── */
.or-divider{
  display:flex;align-items:center;gap:12px;
  margin-bottom:18px;
  color:var(--muted2);font-size:.72rem;letter-spacing:.04em;
}
.or-divider::before,.or-divider::after{content:'';flex:1;height:1px;background:var(--border);}

.field{margin-bottom:14px;}
label{display:block;font-size:.68rem;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;}
input{
  width:100%;padding:11px 14px;
  background:var(--surface2);border:1px solid var(--border2);
  border-radius:10px;color:var(--text);
  font-family:'DM Sans',sans-serif;font-size:.88rem;
  outline:none;transition:border-color .2s;
}
input:focus{border-color:var(--green);}
input::placeholder{color:var(--muted2);}
.hint{font-size:.72rem;color:var(--muted2);margin-top:5px;}

.btn{
  width:100%;padding:12px;margin-top:6px;
  background:var(--green);border:none;border-radius:10px;
  color:#000;font-weight:800;font-size:.88rem;
  cursor:pointer;transition:all .2s;
  font-family:'DM Sans',sans-serif;
}
.btn:hover{background:#00fbb5;transform:translateY(-1px);box-shadow:0 8px 24px rgba(0,229,160,.25);}

.alert{padding:10px 13px;border-radius:10px;font-size:.8rem;margin-bottom:14px;}
.alert-error{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#fca5a5;}
.alert-success{background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);color:#86efac;}

.divider{height:1px;background:var(--border);margin:22px 0;}
.foot{text-align:center;font-size:.8rem;color:var(--muted);}
.foot a{color:var(--green);text-decoration:none;font-weight:600;}
.foot a:hover{text-decoration:underline;}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:480px){.row{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="glow g1"></div>
<div class="glow g2"></div>

<div class="card">
  <div class="logo-wrap">
    <div class="logo-icon">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
    </div>
    <div class="logo">EMOL</div>
  </div>
  <div class="tagline">Smart Email Outreach Platform</div>

  <h2>Create your account</h2>
  <div class="sub">Start reaching more businesses today</div>

  <?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success">
      <?= htmlspecialchars($success) ?>
      <a href="signin.php" style="color:var(--green);font-weight:700;">Sign in now →</a>
    </div>
  <?php endif; ?>

  <!-- Google Sign-Up (same OAuth flow — creates account if new) -->
  <a href="handlers/google_auth.php?action=redirect" class="google-btn">
    <svg class="google-icon" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
      <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
      <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
      <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
      <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
      <path fill="none" d="M0 0h48v48H0z"/>
    </svg>
    Continue with Google
  </a>

  <div class="or-divider">or create account with email</div>

  <?php if (!$success): ?>
  <form method="POST">
    <div class="field">
      <label>Full Name</label>
      <input type="text" name="full_name" placeholder="e.g. Ahmed Ibrahim" required
             value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"/>
    </div>
    <div class="row">
      <div class="field">
        <label>Username</label>
        <input type="text" name="username" placeholder="e.g. ahmed123" required
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"/>
      </div>
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" placeholder="you@example.com" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
      </div>
    </div>
    <div class="field">
      <label>Password</label>
      <input type="password" name="password" placeholder="Min 8 chars, 1 uppercase, 1 number" required/>
      <div class="hint">At least 8 characters, one uppercase letter and one number</div>
    </div>
    <button type="submit" class="btn">Create Account</button>
  </form>
  <?php endif; ?>

  <div class="divider"></div>
  <div class="foot">Already have an account? <a href="signin.php">Sign in</a></div>
</div>
</body>
</html>