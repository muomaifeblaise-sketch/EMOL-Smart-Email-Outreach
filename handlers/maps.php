<?php
// ── Google Maps API Handler ───────────────
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
Auth::requireAuth();

if (!GOOGLE_MAPS_KEY) {
    echo json_encode(['error' => 'Google Maps API not configured yet.']);
    exit();
}

$cat   = trim($_GET['cat']  ?? '');
$loc   = trim($_GET['loc']  ?? '');
$max   = min((int)($_GET['max'] ?? 10), 50);
$noweb = isset($_GET['noweb']);

if (!$cat || !$loc) {
    echo json_encode(['error' => 'Category and location are required.']);
    exit();
}

$query   = urlencode("$cat in $loc");
$url     = "https://maps.googleapis.com/maps/api/place/textsearch/json?query={$query}&key=" . GOOGLE_MAPS_KEY;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
curl_close($ch);

if (!$response) {
    echo json_encode(['error' => 'Failed to connect to Google Maps API.']);
    exit();
}

$data    = json_decode($response, true);
$results = $data['results'] ?? [];

// For each result get details (phone, website)
$detailed = [];
$count    = 0;

foreach ($results as $place) {
    if ($count >= $max) break;

    $place_id = $place['place_id'];
    $fields   = 'name,formatted_address,formatted_phone_number,website,vicinity';
    $durl     = "https://maps.googleapis.com/maps/api/place/details/json?place_id={$place_id}&fields={$fields}&key=" . GOOGLE_MAPS_KEY;

    $ch2 = curl_init($durl);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 8);
    $dres = curl_exec($ch2);
    curl_close($ch2);

    $detail = json_decode($dres, true)['result'] ?? [];

    $business = [
        'name'                  => $detail['name']                  ?? $place['name'],
        'vicinity'              => $detail['formatted_address']     ?? $place['formatted_address'] ?? '',
        'formatted_phone_number'=> $detail['formatted_phone_number'] ?? '',
        'website'               => $detail['website']               ?? '',
    ];

    // If hunting no-website businesses, skip those with websites
    if ($noweb && !empty($business['website'])) continue;

    $detailed[] = $business;
    $count++;
}

echo json_encode(['results' => $detailed, 'total' => count($detailed)]);
