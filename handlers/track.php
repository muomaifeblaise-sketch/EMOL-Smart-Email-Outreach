<?php
// ── A/B Test Open Tracker ─────────────────
// Embedded as a 1x1 pixel in emails.
// Called automatically when recipient opens the email.
require_once __DIR__ . '/../config.php';

$db      = getDB();
$test_id = (int)($_GET['test_id'] ?? 0);
$variant = $_GET['v'] ?? '';

if ($test_id && in_array($variant, ['a', 'b'])) {

    // Increment the correct open counter
    $col  = $variant === 'a' ? 'opens_a' : 'opens_b';
    $stmt = $db->prepare("UPDATE ab_tests SET $col = $col + 1 WHERE id = ?");
    $stmt->bind_param('i', $test_id);
    $stmt->execute();
    $stmt->close();

    // Auto-pick winner if one variant is ahead by 3+ opens
    $stmt = $db->prepare("SELECT opens_a, opens_b, winner FROM ab_tests WHERE id = ?");
    $stmt->bind_param('i', $test_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && $row['winner'] === 'none') {
        $diff = abs((int)$row['opens_a'] - (int)$row['opens_b']);
        if ($diff >= 3) {
            $winner = $row['opens_a'] > $row['opens_b'] ? 'a' : 'b';
            $stmt   = $db->prepare("UPDATE ab_tests SET winner = ? WHERE id = ?");
            $stmt->bind_param('si', $winner, $test_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Always return a 1x1 transparent GIF regardless
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
exit();
