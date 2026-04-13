<?php
/**
 * Binance Pay API Client
 */

function binance_verify(string $orderId, float $amount): array {
    $transactions = binance_get_transactions();

    if ($transactions === null) {
        return ['ok' => false, 'error' => 'Failed to connect to Binance API.'];
    }

    foreach ($transactions as $tx) {
        if (($tx['orderId'] ?? '') !== $orderId) continue;

        // Incoming = positive amount
        $txAmount = (float)($tx['amount'] ?? 0);
        if ($txAmount <= 0) {
            return ['ok' => false, 'error' => 'This is an outgoing payment, not an incoming one.'];
        }

        // Coin check
        if (strtoupper($tx['currency'] ?? '') !== strtoupper(COIN)) {
            return ['ok' => false, 'error' => 'Coin mismatch. Expected ' . COIN . ' but got ' . ($tx['currency'] ?? '')];
        }

        // Amount check
        if (abs($txAmount - $amount) > 0.01) {
            return ['ok' => false, 'error' => sprintf('Amount mismatch. Expected %.2f but got %.2f %s.', $amount, $txAmount, COIN)];
        }

        // Age check
        $txTime = (int)(($tx['transactionTime'] ?? 0) / 1000);
        if ($txTime > 0 && (time() - $txTime) > (MAX_AGE_HOURS * 3600)) {
            return ['ok' => false, 'error' => 'Transaction is older than ' . MAX_AGE_HOURS . ' hours.'];
        }

        return ['ok' => true, 'tx' => $tx, 'amount' => $txAmount];
    }

    return ['ok' => false, 'error' => 'Order ID not found in Binance Pay history.'];
}

function binance_get_transactions(int $limit = 100): ?array {
    $params    = ['limit' => min($limit, 100), 'timestamp' => (int)(microtime(true) * 1000)];
    $query     = http_build_query($params);
    $signature = hash_hmac('sha256', $query, BINANCE_API_SECRET);
    $url       = 'https://api.binance.com/sapi/v1/pay/transactions?' . $query . '&signature=' . $signature;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['X-MBX-APIKEY: ' . BINANCE_API_KEY],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err || !$raw) return null;

    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) return null;
    if (($data['code'] ?? '') !== '000000') return null;

    return $data['data'] ?? [];
}
