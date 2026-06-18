<?php
// ── A/B Test Handler ──────────────────────
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
Auth::requireAuth();

$user = Auth::user();
$uid  = $user['id'];
$db   = getDB();

$action = $_GET['action'] ?? 'save';

// ── VOTE: manually record an open ─────────
if ($action === 'vote') {
    $test_id = (int)($_GET['test_id'] ?? 0);
    $variant = $_GET['v'] ?? '';

    if (!$test_id || !in_array($variant, ['a', 'b'])) {
        echo json_encode(['error' => 'Invalid parameters.']);
        exit();
    }

    $stmt = $db->prepare("SELECT id, opens_a, opens_b, winner FROM ab_tests WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $test_id, $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) { echo json_encode(['error' => 'Test not found.']); exit(); }

    $col  = $variant === 'a' ? 'opens_a' : 'opens_b';
    $stmt = $db->prepare("UPDATE ab_tests SET $col = $col + 1 WHERE id = ?");
    $stmt->bind_param('i', $test_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("SELECT opens_a, opens_b, winner FROM ab_tests WHERE id = ?");
    $stmt->bind_param('i', $test_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $winner = $row['winner'];
    if ($winner === 'none') {
        $diff = abs((int)$row['opens_a'] - (int)$row['opens_b']);
        if ($diff >= 3) {
            $winner = $row['opens_a'] > $row['opens_b'] ? 'a' : 'b';
            $stmt   = $db->prepare("UPDATE ab_tests SET winner = ? WHERE id = ?");
            $stmt->bind_param('si', $winner, $test_id);
            $stmt->execute();
            $stmt->close();
            $row['winner'] = $winner;
        }
    }

    echo json_encode(['success'=>true,'opens_a'=>(int)$row['opens_a'],'opens_b'=>(int)$row['opens_b'],'winner'=>$row['winner']]);
    exit();
}

// ── SET WINNER: manually declare winner ───
if ($action === 'set_winner') {
    $input   = json_decode(file_get_contents('php://input'), true);
    $test_id = (int)($input['test_id'] ?? 0);
    $winner  = $input['winner'] ?? '';

    if (!$test_id || !in_array($winner, ['a', 'b', 'none'])) {
        echo json_encode(['error' => 'Invalid parameters.']); exit();
    }

    $stmt = $db->prepare("UPDATE ab_tests SET winner = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param('sii', $winner, $test_id, $uid);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'winner' => $winner]);
    exit();
}

// ── DELETE ────────────────────────────────
if ($action === 'delete') {
    $test_id = (int)($_GET['test_id'] ?? 0);
    $stmt    = $db->prepare("DELETE FROM ab_tests WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $test_id, $uid);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit();
}

// ── SAVE ──────────────────────────────────
$input     = json_decode(file_get_contents('php://input'), true);
$variant_a = trim($input['variant_a'] ?? '');
$variant_b = trim($input['variant_b'] ?? '');

if (!$variant_a || !$variant_b) {
    echo json_encode(['error' => 'Both variants are required.']); exit();
}

$stmt = $db->prepare("INSERT INTO ab_tests (user_id, variant_a, variant_b) VALUES (?, ?, ?)");
$stmt->bind_param('iss', $uid, $variant_a, $variant_b);

if ($stmt->execute()) {
    $new_id  = $db->insert_id;
    $base    = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $pixel_a = $base . '/emol/public/handlers/track.php?test_id=' . $new_id . '&v=a';
    $pixel_b = $base . '/emol/public/handlers/track.php?test_id=' . $new_id . '&v=b';
    echo json_encode(['success'=>true,'message'=>'A/B test saved.','test_id'=>$new_id,'pixel_a'=>$pixel_a,'pixel_b'=>$pixel_b]);
} else {
    echo json_encode(['error' => 'Failed to save A/B test.']);
}
