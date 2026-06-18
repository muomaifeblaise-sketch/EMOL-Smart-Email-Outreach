<?php
// ── Templates Handler ─────────────────────
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
    $stmt = $db->prepare("DELETE FROM templates WHERE id=? AND user_id=?");
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit();
}

// POST — save template
$input = json_decode(file_get_contents('php://input'), true);

$name    = trim($input['name']     ?? '');
$cat     = trim($input['category'] ?? '');
$subject = trim($input['subject']  ?? '');
$body    = trim($input['body']     ?? '');

if (!$name || !$body) {
    echo json_encode(['error' => 'Name and body are required.']);
    exit();
}

$stmt = $db->prepare(
    "INSERT INTO templates (user_id,name,category,subject,body) VALUES (?,?,?,?,?)"
);
$stmt->bind_param('issss', $uid, $name, $cat, $subject, $body);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Template saved.']);
} else {
    echo json_encode(['error' => 'Failed to save template.']);
}
