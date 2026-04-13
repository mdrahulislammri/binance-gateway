<?php
/**
 * Binance Pay Client — Standalone Web Scripts
 * @author Md Rahul Islam <https://github.com/mdrahulislammri>
 */

class BinanceClient
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
            if ($txAmount <= 0) { $this->lastError = 'This is an outgoing payment.'; return null; }
            if (strtoupper($tx['currency'] ?? '') !== strtoupper($coin)) { $this->lastError = 'Coin mismatch.'; return null; }
            if (abs($txAmount - $amount) > 0.01) { $this->lastError = sprintf('Amount mismatch. Expected %.2f but got %.2f.', $amount, $txAmount); return null; }

            $txTime = (int)(($tx['transactionTime'] ?? 0) / 1000);
            if ($txTime > 0 && (time() - $txTime) > ($maxAgeHours * 3600)) { $this->lastError = 'Transaction too old.'; return null; }

            return $tx;
        }

        $this->lastError = 'Order ID not found.';
        return null;
    }

    public function getPayTransactions(int $limit = 100): ?array
    {
        $response = $this->signedGet('/sapi/v1/pay/transactions', ['limit' => min($limit, 100)]);
        if ($response === null) return null;
        if (($response['code'] ?? '') !== '000000') { $this->lastError = $response['message'] ?? 'API error.'; return null; }
        return $response['data'] ?? [];
    }

    public function getDepositHistory(string $coin = 'USDT', int $limit = 100): ?array
    {
        $response = $this->signedGet('/sapi/v1/capital/deposit/hisrec', ['coin' => strtoupper($coin), 'limit' => $limit, 'recvWindow' => 60000]);
        if ($response === null) return null;
        if (!is_array($response)) { $this->lastError = 'Unexpected response.'; return null; }
        return $response;
    }

    public function getError(): string { return $this->lastError ?: 'Unknown error.'; }

    private function signedGet(string $path, array $params = []): ?array
    {
        $params['timestamp'] = (int)(microtime(true) * 1000);
        $query     = http_build_query($params);
        $signature = hash_hmac('sha256', $query, $this->apiSecret);
        $url       = self::BASE_URL . $path . '?' . $query . '&signature=' . $signature;

        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['X-MBX-APIKEY: ' . $this->apiKey], CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => true]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err || !$raw) { $this->lastError = 'Connection error: ' . $err; return null; }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) { $this->lastError = 'Invalid response.'; return null; }
        if (isset($decoded['code']) && is_int($decoded['code']) && $decoded['code'] < 0) { $this->lastError = $decoded['msg'] ?? 'API error.'; return null; }
        return $decoded;
    }
}
