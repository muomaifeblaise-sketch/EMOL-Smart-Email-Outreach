<?php
// ── OpenAI API Handler ────────────────────
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
Auth::requireAuth();

if (!OPENAI_KEY) {
    echo json_encode(['error' => 'AI Writer not configured yet. Add your OpenAI key to config.php.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$context    = $input['context']   ?? 'writer';
$biz        = $input['biz']       ?? '';
$offer      = $input['offer']     ?? '';
$tone       = $input['tone']      ?? 'Professional';
$length     = $input['length']    ?? 'Medium';
$options    = $input['options']   ?? [];
$variant_a  = $input['variant_a'] ?? '';

// ── Build prompt ──────────────────────────
if ($context === 'abtest') {
    $prompt = "You are an expert email copywriter. I have this cold email (Variant A):\n\n$variant_a\n\nWrite a different Variant B with a different angle, subject line or hook. Same offer, different approach. Return only the email body, no explanation.";
} elseif ($context === 'campaign') {
    $prompt = "Write a cold email to a $biz business. Tone: $tone. Length: $length.";
    if ($offer) $prompt .= " We are offering: $offer.";
    if (in_array('cta', $options))     $prompt .= " Include a clear call to action.";
    if (in_array('urgency', $options)) $prompt .= " Include urgency.";
    if (in_array('stats', $options))   $prompt .= " Include a relevant statistic.";
    if (in_array('offer', $options))   $prompt .= " Include a special offer or discount.";
    if (in_array('ps', $options))      $prompt .= " Add a P.S. line.";
    $prompt .= "\n\nReturn JSON with keys: subject, email. No markdown, no explanation.";
} else {
    // writer context
    $prompt = "Write a cold email targeting $biz businesses. ";
    if ($offer) $prompt .= "We offer: $offer. ";
    $prompt .= "Tone: $tone. Length: $length. ";
    if (in_array('cta', $options))     $prompt .= "Include strong call to action. ";
    if (in_array('urgency', $options)) $prompt .= "Add urgency. ";
    if (in_array('stats', $options))   $prompt .= "Include a relevant statistic. ";
    if (in_array('offer', $options))   $prompt .= "Include a special offer. ";
    if (in_array('ps', $options))      $prompt .= "Add a P.S. line. ";
    $prompt .= "\n\nReturn JSON with keys: subject, email. No markdown.";
}

// ── Call OpenAI ───────────────────────────
$payload = json_encode([
    'model'    => OPENAI_MODEL,
    'messages' => [
        ['role' => 'system', 'content' => 'You are an expert cold email copywriter. Write compelling, personalized emails that get replies. Always return valid JSON.'],
        ['role' => 'user',   'content' => $prompt]
    ],
    'max_tokens'  => 800,
    'temperature' => 0.75,
]);

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_KEY,
    ],
    CURLOPT_TIMEOUT        => 30,
]);

$response = curl_exec($ch);
$err      = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['error' => 'Failed to connect to OpenAI: ' . $err]);
    exit();
}

$data    = json_decode($response, true);
$content = $data['choices'][0]['message']['content'] ?? '';

// Parse JSON response
$content = preg_replace('/```json|```/', '', $content);
$parsed  = json_decode(trim($content), true);

if ($parsed && isset($parsed['email'])) {
    echo json_encode(['email' => $parsed['email'], 'subject' => $parsed['subject'] ?? '']);
} else {
    // If not JSON, return as plain email
    echo json_encode(['email' => trim($content), 'subject' => '']);
}
