<?php
if (!defined('ABSPATH')) exit;

class Binance_Client
{
    const BASE_URL = 'https://api.binance.com';

    private string $apiKey;
    private string $apiSecret;
    private string $lastError = '';

    public function __construct(string $apiKey, string $apiSecret)
    {
        $this->apiKey    = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    public function verifyByOrderId(string $orderId, float $amount, string $coin = 'USDT', int $maxAgeHours = 24): ?array
    {
        $transactions = $this->getPayTransactions(100);
        if ($transactions === null) return null;

        foreach ($transactions as $tx) {
            if (($tx['orderId'] ?? '') !== $orderId) continue;

            $txAmount = (float)($tx['amount'] ?? 0);
            if ($txAmount <= 0) {
                $this->lastError = 'This is an outgoing payment, not an incoming one.';
                return null;
            }
            if (strtoupper($tx['currency'] ?? '') !== strtoupper($coin)) {
                $this->lastError = 'Coin mismatch. Expected ' . $coin . ' but got ' . ($tx['currency'] ?? '');
                return null;
            }
            if (abs($txAmount - $amount) > 0.01) {
                $this->lastError = sprintf('Amount mismatch. Expected %.2f but got %.2f %s.', $amount, $txAmount, $coin);
                return null;
            }
            $txTime = (int)(($tx['transactionTime'] ?? 0) / 1000);
            if ($txTime > 0 && (time() - $txTime) > ($maxAgeHours * 3600)) {
                $this->lastError = 'Transaction is older than ' . $maxAgeHours . ' hours.';
                return null;
            }
            return $tx;
        }

        $this->lastError = 'Order ID not found in Binance Pay history.';
        return null;
    }

    public function getPayTransactions(int $limit = 100): ?array
    {
        $response = $this->signedGet('/sapi/v1/pay/transactions', ['limit' => min($limit, 100)]);
        if ($response === null) return null;
        if (($response['code'] ?? '') !== '000000') {
            $this->lastError = $response['errorMessage'] ?? $response['message'] ?? 'Failed to fetch Pay transactions.';
            return null;
        }
        return $response['data'] ?? [];
    }

    public function getLastError(): string { return $this->lastError ?: 'Unknown error.'; }

    private function signedGet(string $path, array $params = []): ?array
    {
        $params['timestamp'] = (int)(microtime(true) * 1000);
        $query     = http_build_query($params);
        $signature = hash_hmac('sha256', $query, $this->apiSecret);
        $url       = self::BASE_URL . $path . '?' . $query . '&signature=' . $signature;

        $response = wp_remote_get($url, [
            'timeout'   => 30,
            'sslverify' => true,
            'headers'   => ['X-MBX-APIKEY' => $this->apiKey],
        ]);

        if (is_wp_error($response)) {
            $this->lastError = 'Connection error: ' . $response->get_error_message();
            return null;
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if (json_last_error() !== JSON_ERROR_NONE) { $this->lastError = 'Invalid API response.'; return null; }
        if (isset($decoded['code']) && is_int($decoded['code']) && $decoded['code'] < 0) {
            $this->lastError = $decoded['msg'] ?? 'Binance API error: ' . $decoded['code'];
            return null;
        }
        return $decoded;
    }
}
