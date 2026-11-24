<?php
/**
 * API: Reverse Geocode Endpoint
 * Converts latitude and longitude into human-readable address.
 * Uses OpenStreetMap Nominatim with proper User-Agent and timeout.
 * 
 * Method: GET
 * Query Params:
 *  - lat: float (latitude)
 *  - lng or lon: float (longitude)
 * Response (JSON):
 *  - success: bool
 *  - address: string
 *  - display_name: string
 *  - details: object
 */

header('Content-Type: application/json; charset=utf-8');

// Allow GET only
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Metode request tidak diizinkan. Gunakan GET.']);
    exit;
}

$lat = isset($_GET['lat']) ? filter_var($_GET['lat'], FILTER_VALIDATE_FLOAT) : false;
$lng = isset($_GET['lng']) ? filter_var($_GET['lng'], FILTER_VALIDATE_FLOAT) : false;
if ($lng === false && isset($_GET['lon'])) {
    $lng = filter_var($_GET['lon'], FILTER_VALIDATE_FLOAT);
}

if ($lat === false || $lng === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter latitude atau longitude tidak valid.']);
    exit;
}

$url = sprintf(
    'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=%F&lon=%F&accept-language=id',
    $lat,
    $lng
);

$response = null;

// Prefer cURL if available
if (extension_loaded('curl')) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_USERAGENT => 'LaporKota-CivicPlatform/1.0 (laporkota@banyumaskab.go.id)',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: id,en;q=0.8'
        ],
        CURLOPT_SSL_VERIFYPEER => false // Avoid local certificate issues in XAMPP
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        $response = null;
    }
}

// Fallback to file_get_contents with stream context if cURL didn't yield a result
if ($response === null) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: LaporKota-CivicPlatform/1.0 (laporkota@banyumaskab.go.id)\r\n" .
                        "Accept: application/json\r\n" .
                        "Accept-Language: id,en;q=0.8\r\n",
            'timeout' => 5,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    $response = @file_get_contents($url, false, $context);
}

if ($response) {
    $data = json_decode($response, true);
    if (is_array($data) && !empty($data['display_name'])) {
        $displayName = $data['display_name'];
        
        echo json_encode([
            'success' => true,
            'address' => $displayName,
            'display_name' => $displayName,
            'details' => $data['address'] ?? []
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

echo json_encode([
    'success' => false,
    'message' => 'Alamat tidak ditemukan untuk titik lokasi ini.'
]);
