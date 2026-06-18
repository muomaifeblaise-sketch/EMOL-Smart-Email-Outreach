<?php
// ── SMTP Email Handler ────────────────────
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
Auth::requireAuth();

$user  = Auth::user();
$uid   = $user['id'];
$db    = getDB();

// ── REPLY: manually record a reply ───────
if (isset($_GET['action']) && $_GET['action'] === 'reply') {
    $input       = json_decode(file_get_contents('php://input'), true);
    $campaign_id = (int)($input['campaign_id'] ?? 0);

    if (!$campaign_id) {
        echo json_encode(['error' => 'Invalid campaign.']);
        exit();
    }

    // Make sure campaign belongs to this user
    $stmt = $db->prepare("SELECT replies FROM campaigns WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $campaign_id, $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['error' => 'Campaign not found.']);
        exit();
    }

    $stmt = $db->prepare("UPDATE campaigns SET replies = replies + 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $campaign_id, $uid);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'replies' => (int)$row['replies'] + 1]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$name       = $input['name']       ?? '';
$category   = $input['category']   ?? '';
$location   = $input['location']   ?? '';
$subject    = $input['subject']    ?? '';
$body       = $input['body']       ?? '';
$businesses = $input['businesses'] ?? [];

if (!$subject || !$body) {
    echo json_encode(['error' => 'Subject and body are required.']);
    exit();
}

// ── Save campaign to DB ───────────────────
$stmt = $db->prepare("INSERT INTO campaigns (user_id,name,category,location,goal,status) VALUES (?,?,?,?,'Generate Leads','draft')");
$stmt->bind_param('isss', $uid, $name, $category, $location);
$stmt->execute();
$campaign_id = $db->insert_id;
$stmt->close();

// ── Check SMTP config ─────────────────────
if (!SMTP_HOST || !SMTP_USER || !SMTP_PASSWORD) {
    $stmt = $db->prepare("UPDATE campaigns SET status='draft' WHERE id=?");
    $stmt->bind_param('i', $campaign_id);
    $stmt->execute();
    echo json_encode(['success' => false, 'error' => 'SMTP not configured. Campaign saved as draft. Add SMTP credentials to config.php.']);
    exit();
}

// ── Base URL for tracking pixel ───────────
$base_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/emol/public/handlers';

// ── Send emails via SMTP ──────────────────
$sent   = 0;
$failed = 0;

foreach ($businesses as $biz) {
    $to_email = $biz['email'] ?? '';
    $to_name  = $biz['name']  ?? '';

    if (!$to_email || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        $failed++;
        continue;
    }

    // Personalise subject + body
    $personalized_subject = str_replace(['{name}', '{business}'], [$to_name, $to_name], $subject);
    $personalized_body    = str_replace(['{name}', '{business}', '{location}'], [$to_name, $to_name, $location], $body);

    // ── Save to DB first to get the email ID ─
    $stmt = $db->prepare("INSERT INTO sent_emails (user_id,campaign_id,to_email,to_name,subject,body,status) VALUES (?,?,?,?,?,?,'sent')");
    $stmt->bind_param('iissss', $uid, $campaign_id, $to_email, $to_name, $personalized_subject, $personalized_body);
    $stmt->execute();
    $email_id = $db->insert_id;
    $stmt->close();

    // ── Build tracking pixel URL ──────────
    $pixel_url = $base_url . '/open.php?id=' . $email_id . '&cid=' . $campaign_id;

    // ── Convert plain text body to HTML ───
    $html_body = buildHtmlEmail($personalized_body, $to_name, $pixel_url);

    $result = sendSMTP($to_email, $to_name, $personalized_subject, $html_body);

    if ($result) {
        $sent++;
    } else {
        $failed++;
        // Mark as failed
        $stmt = $db->prepare("UPDATE sent_emails SET status='failed' WHERE id=?");
        $stmt->bind_param('i', $email_id);
        $stmt->execute();
        $stmt->close();
    }
}

// ── Update campaign stats ─────────────────
$status = $sent > 0 ? ($failed > 0 ? 'partial' : 'sent') : 'draft';
$stmt   = $db->prepare("UPDATE campaigns SET status=?,emails_sent=?,sent_at=NOW() WHERE id=?");
$stmt->bind_param('sii', $status, $sent, $campaign_id);
$stmt->execute();
$stmt->close();

echo json_encode([
    'success' => $sent > 0,
    'message' => "Campaign sent! $sent emails delivered" . ($failed > 0 ? ", $failed failed." : "."),
    'sent'    => $sent,
    'failed'  => $failed,
]);

// ── Build HTML email wrapper ──────────────
function buildHtmlEmail($plain_body, $to_name, $pixel_url) {
    // Convert plain text line breaks to HTML
    $html_content = nl2br(htmlspecialchars($plain_body));

    return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#ffffff;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#ffffff;">
    <tr><td align="center" style="padding:32px 16px;">
      <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">
        <tr><td style="padding:32px;background:#ffffff;border-radius:8px;border:1px solid #e5e7eb;">
          <div style="font-size:15px;line-height:1.7;color:#1f2937;">' . $html_content . '</div>
        </td></tr>
      </table>
    </td></tr>
  </table>
  <!-- tracking pixel -->
  <img src="' . $pixel_url . '" width="1" height="1" style="display:none;border:0;" alt="">
</body>
</html>';
}

// ── SMTP Send Function ────────────────────
function sendSMTP($to_email, $to_name, $subject, $html_body) {
    $host     = SMTP_HOST;
    $port     = SMTP_PORT;
    $user     = SMTP_USER;
    $password = SMTP_PASSWORD;
    $from     = SMTP_FROM ?: $user;
    $fromName = SMTP_FROM_NAME;

    $socket = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$socket) return false;

    $read = function() use ($socket) {
        $resp = '';
        while ($line = fgets($socket, 515)) {
            $resp .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $resp;
    };
    $send = function($cmd) use ($socket, $read) {
        fwrite($socket, $cmd . "\r\n");
        return $read();
    };

    $read(); // Banner
    $send("EHLO " . gethostname());
    $send("STARTTLS");
    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    $send("EHLO " . gethostname());

    // Auth
    $send("AUTH LOGIN");
    $send(base64_encode($user));
    $r = $send(base64_encode($password));
    if (strpos($r, '235') === false) { fclose($socket); return false; }

    // Send
    $send("MAIL FROM:<{$from}>");
    $send("RCPT TO:<{$to_email}>");
    $send("DATA");

    $date     = date('r');
    $headers  = "Date: $date\r\n";
    $headers .= "From: {$fromName} <{$from}>\r\n";
    $headers .= "To: {$to_name} <{$to_email}>\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: quoted-printable\r\n";

    $r = $send($headers . "\r\n" . $html_body . "\r\n.");
    $send("QUIT");
    fclose($socket);

    return strpos($r, '250') !== false;
}
