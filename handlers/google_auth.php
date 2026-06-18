<?php
// ============================================================
//  EMOL - Google OAuth 2.0 Handler
//  Handles both: redirect to Google + callback from Google
// ============================================================
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

const GOOGLE_AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
const GOOGLE_USER_URL  = 'https://www.googleapis.com/oauth2/v2/userinfo';

// ── STEP 1: Redirect user to Google ──────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'redirect') {

    if (!GOOGLE_CLIENT_ID || !GOOGLE_CLIENT_SECRET) {
        header('Location: ../signin.php?error=' . urlencode('Google sign-in is not configured yet.'));
        exit();
    }

    // CSRF state token
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ]);

    header('Location: ' . GOOGLE_AUTH_URL . '?' . $params);
    exit();
}

// ── STEP 2: Google redirects back here with ?code= ───────
if (isset($_GET['code'])) {

    // Verify CSRF state
    $incoming_state = $_GET['state'] ?? '';
    if (!$incoming_state || $incoming_state !== ($_SESSION['oauth_state'] ?? '')) {
        header('Location: ../signin.php?error=' . urlencode('Security check failed. Please try again.'));
        exit();
    }
    unset($_SESSION['oauth_state']);

    // Exchange code for access token
    $token_data = exchangeCodeForToken($_GET['code']);
    if (empty($token_data['access_token'])) {
        header('Location: ../signin.php?error=' . urlencode('Could not connect to Google. Please try again.'));
        exit();
    }

    // Get user profile from Google
    $google_user = getGoogleUserInfo($token_data['access_token']);
    if (empty($google_user['email'])) {
        header('Location: ../signin.php?error=' . urlencode('Could not retrieve your Google account info.'));
        exit();
    }

    // Login or create account
    $result = loginOrRegisterGoogle($google_user);
    if ($result['success']) {
        header('Location: ../dashboard.php');
    } else {
        header('Location: ../signin.php?error=' . urlencode($result['message']));
    }
    exit();
}

// ── Google returned an error (user cancelled, etc.) ──────
if (isset($_GET['error'])) {
    $msg = $_GET['error'] === 'access_denied'
        ? 'Google sign-in was cancelled.'
        : urlencode($_GET['error_description'] ?? 'Google sign-in failed.');
    header('Location: ../signin.php?error=' . urlencode($msg));
    exit();
}

// ── Fallback ──────────────────────────────────────────────
header('Location: ../signin.php');
exit();


// ═══════════════════════════════════════════════════════════
//  HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════

function exchangeCodeForToken(string $code): array {
    $payload = http_build_query([
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]);

    $ch = curl_init(GOOGLE_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response ?: '{}', true) ?? [];
}

function getGoogleUserInfo(string $access_token): array {
    $ch = curl_init(GOOGLE_USER_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $access_token],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response ?: '{}', true) ?? [];
}

function loginOrRegisterGoogle(array $g): array {
    $db         = getDB();
    $google_id  = $g['id'];
    $email      = strtolower(trim($g['email']));
    $full_name  = trim($g['name'] ?? $email);
    $avatar_url = $g['picture'] ?? '';

    // Build initials
    $parts    = explode(' ', $full_name);
    $initials = strtoupper(
        substr($parts[0], 0, 1) .
        (isset($parts[1]) ? substr($parts[1], 0, 1) : '')
    );

    // ── 1. Already linked via google_id ──────────────────
    $stmt = $db->prepare("SELECT * FROM users WHERE google_id = ? LIMIT 1");
    $stmt->bind_param('s', $google_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Refresh avatar in case it changed
        $stmt = $db->prepare("UPDATE users SET avatar_url = ?, last_login = NOW() WHERE id = ?");
        $stmt->bind_param('si', $avatar_url, $user['id']);
        $stmt->execute();
        $stmt->close();
        $user['avatar_url'] = $avatar_url;
        startSession($user, $db);
        return ['success' => true];
    }

    // ── 2. Email exists — link Google to existing account ─
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $stmt = $db->prepare(
            "UPDATE users SET google_id = ?, avatar_url = ?, last_login = NOW() WHERE id = ?"
        );
        $stmt->bind_param('ssi', $google_id, $avatar_url, $user['id']);
        $stmt->execute();
        $stmt->close();
        $user['google_id']  = $google_id;
        $user['avatar_url'] = $avatar_url;
        startSession($user, $db);
        return ['success' => true];
    }

    // ── 3. Brand new user — create account ───────────────
    $username = makeUniqueUsername($parts[0], $db);

    $stmt = $db->prepare(
        "INSERT INTO users (full_name, username, email, password, google_id, avatar_url, avatar_initials)
         VALUES (?, ?, ?, NULL, ?, ?, ?)"
    );
    $stmt->bind_param('ssssss', $full_name, $username, $email, $google_id, $avatar_url, $initials);

    if (!$stmt->execute()) {
        $stmt->close();
        return ['success' => false, 'message' => 'Account creation failed. Please try again.'];
    }
    $new_id = $db->insert_id;
    $stmt->close();

    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $new_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    startSession($user, $db);
    return ['success' => true];
}

function makeUniqueUsername(string $first_name, $db): string {
    // Strip non-alphanumeric, lowercase, fallback to 'user'
    $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $first_name)) ?: 'user';
    $username = $base;
    $suffix   = 1;

    while (true) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();
        $taken = $stmt->num_rows > 0;
        $stmt->close();
        if (!$taken) break;
        $username = $base . $suffix++;
    }
    return $username;
}

function startSession(array $user, $db): void {
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '';
    $agent   = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt = $db->prepare(
        "INSERT INTO sessions (user_id, token, ip_address, user_agent, expires_at)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('issss', $user['id'], $token, $ip, $agent, $expires);
    $stmt->execute();
    $stmt->close();

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['full_name']  = $user['full_name'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['email']      = $user['email'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['initials']   = $user['avatar_initials'];
    $_SESSION['token']      = $token;
    $_SESSION['avatar_url'] = $user['avatar_url'] ?? '';

    // Audit log
    $action = 'google_login';
    $detail = 'Google OAuth: ' . $user['email'];
    $stmt = $db->prepare(
        "INSERT INTO audit_logs (user_id, action, detail, ip_address) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param('isss', $user['id'], $action, $detail, $ip);
    $stmt->execute();
    $stmt->close();
}
