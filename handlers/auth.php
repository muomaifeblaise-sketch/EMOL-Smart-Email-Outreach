<?php
// ============================================================
//  EMOL - Authentication Handler
// ============================================================

require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Auth {

    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    // ── REGISTER ──────────────────────────────────────────
    public function register($full_name, $username, $email, $password) {

        // Validate
        if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'All fields are required.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address.'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return ['success' => false, 'message' => 'Password must contain at least one uppercase letter.'];
        }

        if (!preg_match('/[0-9]/', $password)) {
            return ['success' => false, 'message' => 'Password must contain at least one number.'];
        }

        // Check if email or username already exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->bind_param('ss', $email, $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            return ['success' => false, 'message' => 'Email or username already registered.'];
        }
        $stmt->close();

        // Hash password
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Avatar initials
        $parts    = explode(' ', trim($full_name));
        $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

        // Insert user
        $stmt = $this->db->prepare(
            "INSERT INTO users (full_name, username, email, password, avatar_initials) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('sssss', $full_name, $username, $email, $hashed, $initials);

        if ($stmt->execute()) {
            $this->logAction(null, 'register', "New user: $email", $_SERVER['REMOTE_ADDR'] ?? '');
            return ['success' => true, 'message' => 'Account created! You can now sign in.'];
        }

        return ['success' => false, 'message' => 'Registration failed. Please try again.'];
    }

    // ── LOGIN ─────────────────────────────────────────────
    public function login($identifier, $password) {

        if (empty($identifier) || empty($password)) {
            return ['success' => false, 'message' => 'Please fill in all fields.'];
        }

        // Rate limiting — max 5 attempts per 15 minutes
        if ($this->isRateLimited($identifier)) {
            return ['success' => false, 'message' => 'Too many attempts. Please wait 15 minutes.'];
        }

        // Find user by email or username
        $stmt = $this->db->prepare(
            "SELECT id, full_name, username, email, password, role, avatar_initials FROM users 
             WHERE email = ? OR username = ?"
        );
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password'])) {
            $this->recordAttempt($identifier);
            return ['success' => false, 'message' => 'Incorrect email/username or password.'];
        }

        // Create session
        $this->createSession($user);

        // Update last login
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $stmt->close();

        $this->logAction($user['id'], 'login', 'User logged in', $_SERVER['REMOTE_ADDR'] ?? '');

        return ['success' => true, 'message' => 'Login successful.'];
    }

    // ── LOGOUT ────────────────────────────────────────────
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            // Remove session from DB
            $token = $_SESSION['token'] ?? '';
            if ($token) {
                $stmt = $this->db->prepare("DELETE FROM sessions WHERE token = ?");
                $stmt->bind_param('s', $token);
                $stmt->execute();
                $stmt->close();
            }
            $this->logAction($_SESSION['user_id'], 'logout', 'User logged out', $_SERVER['REMOTE_ADDR'] ?? '');
        }

        $_SESSION = [];
        session_destroy();
    }

    // ── CHECK AUTH ────────────────────────────────────────
    public static function check() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return isset($_SESSION['user_id']) && isset($_SESSION['token']);
    }

    // ── REQUIRE AUTH (redirect if not logged in) ──────────
    public static function requireAuth($redirect = '/emol/public/pages/signin.php') {
        if (!self::check()) {
            header("Location: $redirect");
            exit();
        }
    }

    // ── GET CURRENT USER ──────────────────────────────────
    public static function user() {
        if (!self::check()) return null;
        return [
            'id'       => $_SESSION['user_id'],
            'name'     => $_SESSION['full_name'],
            'username' => $_SESSION['username'],
            'email'    => $_SESSION['email'],
            'role'     => $_SESSION['role'],
            'initials' => $_SESSION['initials'],
            'firstname'=> explode(' ', $_SESSION['full_name'])[0],
        ];
    }

    // ── PRIVATE HELPERS ───────────────────────────────────

    private function createSession($user) {
        $token     = bin2hex(random_bytes(32));
        $expires   = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
        $ip        = $_SERVER['REMOTE_ADDR'] ?? '';
        $agent     = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt = $this->db->prepare(
            "INSERT INTO sessions (user_id, token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('issss', $user['id'], $token, $ip, $agent, $expires);
        $stmt->execute();
        $stmt->close();

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['email']     = $user['email'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['initials']  = $user['avatar_initials'];
        $_SESSION['token']     = $token;
    }

    private function isRateLimited($identifier) {
        $window = date('Y-m-d H:i:s', time() - 900); // 15 min window
        $stmt   = $this->db->prepare(
            "SELECT COUNT(*) as cnt FROM login_attempts 
             WHERE identifier = ? AND attempted_at > ?"
        );
        $stmt->bind_param('ss', $identifier, $window);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $result['cnt'] >= 5;
    }

    private function recordAttempt($identifier) {
        $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $this->db->prepare(
            "INSERT INTO login_attempts (identifier, ip_address) VALUES (?, ?)"
        );
        $stmt->bind_param('ss', $identifier, $ip);
        $stmt->execute();
        $stmt->close();
    }

    // ── CHANGE PASSWORD ───────────────────────────────────
    public function changePassword($user_id, $current, $new_password) {
        if (strlen($new_password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
        }
        if (!preg_match('/[A-Z]/', $new_password)) {
            return ['success' => false, 'error' => 'Password must contain at least one uppercase letter.'];
        }
        if (!preg_match('/[0-9]/', $new_password)) {
            return ['success' => false, 'error' => 'Password must contain at least one number.'];
        }

        $stmt = $this->db->prepare("SELECT password FROM users WHERE id=?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($current, $row['password'])) {
            return ['success' => false, 'error' => 'Current password is incorrect.'];
        }

        $hashed = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $this->db->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param('si', $hashed, $user_id);
        $stmt->execute();
        $stmt->close();

        $this->logAction($user_id, 'change_password', 'Password changed', $_SERVER['REMOTE_ADDR'] ?? '');
        return ['success' => true];
    }

    // ── UPDATE PROFILE ────────────────────────────────────
    public function updateProfile($user_id, $full_name, $username) {
        if (empty($full_name) || empty($username)) {
            return ['success' => false, 'error' => 'Name and username are required.'];
        }

        // Check username not taken by another user
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username=? AND id!=?");
        $stmt->bind_param('si', $username, $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            return ['success' => false, 'error' => 'Username already taken.'];
        }
        $stmt->close();

        $parts    = explode(' ', trim($full_name));
        $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

        $stmt = $this->db->prepare("UPDATE users SET full_name=?, username=?, avatar_initials=? WHERE id=?");
        $stmt->bind_param('sssi', $full_name, $username, $initials, $user_id);
        $stmt->execute();
        $stmt->close();

        // Update session
        $_SESSION['full_name'] = $full_name;
        $_SESSION['username']  = $username;
        $_SESSION['initials']  = $initials;

        return ['success' => true];
    }

    private function logAction($user_id, $action, $detail = '', $ip = '') {
        $stmt = $this->db->prepare(
            "INSERT INTO audit_logs (user_id, action, detail, ip_address) VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('isss', $user_id, $action, $detail, $ip);
        $stmt->execute();
        $stmt->close();
    }
}

// ── API endpoint handler (called via fetch from JS) ───────
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (!Auth::check()) {
        echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
        exit();
    }

    $user  = Auth::user();
    $auth  = new Auth();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($_GET['action'] === 'change_password') {
        echo json_encode($auth->changePassword(
            $user['id'],
            $input['current']      ?? '',
            $input['new_password'] ?? ''
        ));
    } elseif ($_GET['action'] === 'update_profile') {
        echo json_encode($auth->updateProfile(
            $user['id'],
            $input['full_name'] ?? '',
            $input['username']  ?? ''
        ));
    } else {
        echo json_encode(['error' => 'Unknown action.']);
    }
    exit();
}
