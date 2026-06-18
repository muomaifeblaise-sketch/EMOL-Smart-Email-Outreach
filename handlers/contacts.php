<?php
// ── Contacts Handler ──────────────────────
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
Auth::requireAuth();

$user = Auth::user();
$uid  = $user['id'];
$db   = getDB();

// DELETE
if (isset($_GET['delete'])) {
    $id   = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM contacts WHERE id=? AND user_id=?");
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit();
}

// POST — add contact
$input = json_decode(file_get_contents('php://input'), true);

$biz   = $input['business_name'] ?? '';
$name  = $input['contact_name']  ?? '';
$email = trim($input['email']    ?? '');
$phone = $input['phone']         ?? '';
$cat   = $input['category']      ?? '';
$loc   = $input['location']      ?? '';

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Valid email is required.']);
    exit();
}

$stmt = $db->prepare(
    "INSERT INTO contacts (user_id,business_name,contact_name,email,phone,category,location) VALUES (?,?,?,?,?,?,?)"
);
$stmt->bind_param('issssss', $uid, $biz, $name, $email, $phone, $cat, $loc);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Contact saved.']);
} else {
    echo json_encode(['error' => 'Failed to save contact.']);
}
