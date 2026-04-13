<?php
/**
 * Binance Pay Proxy — for BJS Bot
 * Upload to your server, call from BJS HTTP.get()
 * 
 * @author Md Rahul Islam <https://github.com/mdrahulislammri>
 */

header('Content-Type: application/json');

// ─── Simple auth token to protect this endpoint ───────────────────────────────
$AUTH_TOKEN = 'YOUR_SECRET_TOKEN'; // Change this!

if (($_GET['token'] ?? '') !== $AUTH_TOKEN) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$apiKey    = $_GET['api_key']    ?? '';
$apiSecret = $_GET['api_secret'] ?? '';
$limit     = (int)($_GET['limit'] ?? 100);

if (empty($apiKey) || empty($apiSecret)) {
    echo json_encode(['ok' => false, 'error' => 'Missing credentials']);
    exit;
}

// Build signed request
$params    = ['limit' => min($limit, 100), 'timestamp' => (int)(microtime(true) * 1000)];
$query     = http_build_query($params);
$signature = hash_hmac('sha256', $query, $apiSecret);
$url       = 'https://api.binance.com/sapi/v1/pay/transactions?' . $query . '&signature=' . $signature;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['X-MBX-APIKEY: ' . $apiKey],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$raw = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err || !$raw) {
    echo json_encode(['ok' => false, 'error' => 'Connection error: ' . $err]);
    exit;
}

$data = json_decode($raw, true);
if (($data['code'] ?? '') !== '000000') {
    echo json_encode(['ok' => false, 'error' => $data['message'] ?? 'API error', 'code' => $data['code'] ?? '']);
    exit;
}

echo json_encode(['ok' => true, 'data' => $data['data'] ?? []]);
