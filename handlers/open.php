<?php
// ── Email Open Tracker ────────────────────
// Embedded as invisible 1x1 pixel in every sent email.
// Fires automatically when recipient opens the email.
require_once __DIR__ . '/../config.php';

$db         = getDB();
$email_id   = (int)($_GET['id']  ?? 0);
$campaign_id = (int)($_GET['cid'] ?? 0);

if ($email_id) {
    // Only count first open (don't inflate if re-opened)
    $stmt = $db->prepare("SELECT status FROM sent_emails WHERE id = ?");
    $stmt->bind_param('i', $email_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && $row['status'] === 'sent') {
        // Mark email as opened
        $stmt = $db->prepare("UPDATE sent_emails SET status='opened', opened_at=NOW() WHERE id=?");
        $stmt->bind_param('i', $email_id);
        $stmt->execute();
        $stmt->close();

        // Increment campaign open counter
        if ($campaign_id) {
            $stmt = $db->prepare("UPDATE campaigns SET emails_opened = emails_opened + 1 WHERE id = ?");
            $stmt->bind_param('i', $campaign_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Always return 1x1 transparent GIF
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
exit();
