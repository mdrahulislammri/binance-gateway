<?php

/**
 * BinanceClient
 *
 * Personal Account API client for WHMCS Binance Payment Gateway.
 * Supports payment verification via:
 *   1. Binance Pay Order ID  (sapi/v1/pay/transactions)
 *   2. Crypto Deposit TxID   (sapi/v1/capital/deposit/hisrec)
 *   3. Fiat Deposit          (sapi/v1/fiatpayment/query/deposit/history)
 *
 * Required Binance API permission: Enable Reading ONLY.
 * Docs: https://binance-docs.github.io/apidocs/spot/en/
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

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

    // =========================================================================
    // PRIMARY: Verify by Binance Pay Order ID
    // Endpoint: GET /sapi/v1/pay/transactions
    // =========================================================================

    /**
     * Verify a Binance Pay payment by Order ID.
     *
     * Customer sends via Binance Pay → gets Order ID from Payment Details.
     * We search Pay transaction history for that Order ID and verify amount.
     *
     * @param string $orderId  Binance Pay Order ID (e.g. 424623039214764032)
     * @param float  $amount   Expected amount from invoice
     * @param string $coin     e.g. "USDT"
     */
    public function verifyByOrderId(string $orderId, float $amount, string $coin = 'USDT'): ?array
    {
        $orderId = trim($orderId);

        if (empty($orderId)) {
            $this->lastError = 'Order ID is empty.';
            return null;
        }

        // Fetch last 100 Pay transactions (incoming)
        $transactions = $this->getPayTransactions();

        if ($transactions === null) {
            return null;
        }

        foreach ($transactions as $tx) {
            if (($tx['orderId'] ?? '') !== $orderId) {
                continue;
            }

            // Incoming = positive amount, outgoing = negative
            $txAmount = (float)($tx['amount'] ?? 0);
            if ($txAmount <= 0) {
                $this->lastError = 'This is an outgoing payment, not an incoming one.';
                return null;
            }

            // Coin must match
            $txCoin = strtoupper($tx['currency'] ?? '');
            if ($txCoin !== strtoupper($coin)) {
                $this->lastError = "Coin mismatch. Expected {$coin} but payment was in {$txCoin}.";
                return null;
            }

            // Amount must match
            if (abs($txAmount - $amount) > 0.01) {
                $this->lastError = sprintf(
                    'Amount mismatch. Expected %.2f %s but payment was %.2f %s.',
                    $amount, $coin, $txAmount, $coin
                );
                return null;
            }

            return $tx;
        }

        $this->lastError = 'Order ID not found in Binance Pay transaction history. Make sure the payment was sent to your Binance Pay account.';
        return null;
    }

    // =========================================================================
    // SECONDARY: Verify by Crypto Deposit TxID (on-chain transfer)
    // Endpoint: GET /sapi/v1/capital/deposit/hisrec
    // =========================================================================

    /**
     * Verify an on-chain crypto deposit by Transaction Hash (TxID).
     *
     * Customer sends USDT from external wallet or another exchange.
     * We check deposit history for the TxID and verify amount.
     *
     * @param string $txId    Blockchain transaction hash
     * @param float  $amount  Expected amount
     * @param string $coin    e.g. "USDT"
     */
    public function verifyByTxId(string $txId, float $amount, string $coin = 'USDT'): ?array
    {
        $txId = trim($txId);

        if (empty($txId)) {
            $this->lastError = 'Transaction ID is empty.';
            return null;
        }

        $deposits = $this->getDepositHistory($coin);

        if ($deposits === null) {
            return null;
        }

        foreach ($deposits as $deposit) {
            if (strcasecmp($deposit['txId'] ?? '', $txId) !== 0) {
                continue;
            }

            // Status 1 = success
            if ((int)($deposit['status'] ?? -1) !== 1) {
                $this->lastError = 'Transaction found but not yet confirmed on blockchain. Please wait and try again.';
                return null;
            }

            $depositAmount = (float)($deposit['amount'] ?? 0);
            if (abs($depositAmount - $amount) > 0.01) {
                $this->lastError = sprintf(
                    'Amount mismatch. Expected %.2f %s but deposit was %.2f %s.',
                    $amount, $coin, $depositAmount, $coin
                );
                return null;
            }

            return $deposit;
        }

        $this->lastError = 'TxID not found in deposit history. Make sure you sent to the correct address and the transaction is confirmed.';
        return null;
    }

    // =========================================================================
    // FETCH: Binance Pay transaction history (incoming + outgoing)
    // Endpoint: GET /sapi/v1/pay/transactions
    // =========================================================================

    /**
     * Get Binance Pay transaction history.
     * Returns up to 100 most recent transactions.
     */
    public function getPayTransactions(int $limit = 100): ?array
    {
        $response = $this->signedGet('/sapi/v1/pay/transactions', [
            'limit' => min($limit, 100),
        ]);

        if ($response === null) {
            return null;
        }

        // Response: { "code": "000000", "data": [...] }
        if (($response['code'] ?? '') !== '000000') {
            $this->lastError = $response['errorMessage'] ?? $response['message'] ?? 'Failed to fetch Pay transactions.';
            return null;
        }

        return $response['data'] ?? [];
    }

    // =========================================================================
    // FETCH: Crypto deposit history
    // Endpoint: GET /sapi/v1/capital/deposit/hisrec
    // =========================================================================

    /**
     * Get crypto deposit history for a specific coin.
     */
    public function getDepositHistory(string $coin = 'USDT', int $limit = 1000): ?array
    {
        $response = $this->signedGet('/sapi/v1/capital/deposit/hisrec', [
            'coin'       => strtoupper($coin),
            'limit'      => $limit,
            'recvWindow' => 60000,
        ]);

        if ($response === null) {
            return null;
        }

        if (!is_array($response)) {
            $this->lastError = 'Unexpected response from deposit history API.';
            return null;
        }

        return $response;
    }

    // =========================================================================
    // FETCH: Fiat deposit history
    // Endpoint: GET /sapi/v1/fiatpayment/query/deposit/history
    // =========================================================================

    /**
     * Get fiat deposit history (bank transfer, card, etc.)
     */
    public function getFiatDepositHistory(int $page = 1, int $rows = 100): ?array
    {
        $response = $this->signedGet('/sapi/v1/fiatpayment/query/deposit/history', [
            'page'       => $page,
            'rows'       => $rows,
            'recvWindow' => 60000,
        ]);

        if ($response === null) {
            return null;
        }

        if (($response['code'] ?? '') !== '000000') {
            $this->lastError = $response['message'] ?? 'Failed to fetch fiat deposit history.';
            return null;
        }

        return $response['data'] ?? [];
    }

    // =========================================================================
    // FETCH: Spot account balance
    // Endpoint: GET /api/v3/account
    // =========================================================================

    /**
     * Get spot account balances.
     * Returns array of assets with free/locked amounts.
     */
    public function getSpotBalances(): ?array
    {
        $response = $this->signedGet('/api/v3/account', [
            'recvWindow' => 60000,
        ]);

        if ($response === null) {
            return null;
        }

        return $response['balances'] ?? null;
    }

    /**
     * Get balance for a specific coin (e.g. "USDT").
     */
    public function getCoinBalance(string $coin): ?array
    {
        $balances = $this->getSpotBalances();

        if ($balances === null) {
            return null;
        }

        foreach ($balances as $balance) {
            if (strtoupper($balance['asset'] ?? '') === strtoupper($coin)) {
                return $balance;
            }
        }

        $this->lastError = "Coin {$coin} not found in spot account.";
        return null;
    }

    // =========================================================================
    // FETCH: Funding wallet balance
    // Endpoint: POST /sapi/v1/asset/get-funding-asset
    // =========================================================================

    /**
     * Get funding wallet balances (used by Binance Pay).
     */
    public function getFundingBalances(string $asset = ''): ?array
    {
        $params = ['recvWindow' => 60000];
        if (!empty($asset)) {
            $params['asset'] = strtoupper($asset);
        }

        $response = $this->signedPost('/sapi/v1/asset/get-funding-asset', $params);

        if ($response === null) {
            return null;
        }

        if (!is_array($response)) {
            $this->lastError = 'Unexpected response from funding asset API.';
            return null;
        }

        return $response;
    }

    // =========================================================================
    // FETCH: Universal transfer history
    // Endpoint: GET /sapi/v1/asset/transfer
    // =========================================================================

    /**
     * Get transfer history between wallets (e.g. Funding → Spot).
     * Type examples: MAIN_FUNDING, FUNDING_MAIN
     */
    public function getTransferHistory(string $type = 'FUNDING_MAIN', int $size = 100): ?array
    {
        $response = $this->signedGet('/sapi/v1/asset/transfer', [
            'type'       => $type,
            'size'       => $size,
            'recvWindow' => 60000,
        ]);

        if ($response === null) {
            return null;
        }

        return $response['rows'] ?? null;
    }

    // =========================================================================
    // FETCH: Account trade list
    // Endpoint: GET /api/v3/myTrades
    // =========================================================================

    /**
     * Get recent trades for a symbol (e.g. "USDTBUSD").
     */
    public function getMyTrades(string $symbol, int $limit = 100): ?array
    {
        $response = $this->signedGet('/api/v3/myTrades', [
            'symbol'     => strtoupper($symbol),
            'limit'      => $limit,
            'recvWindow' => 60000,
        ]);

        if ($response === null) {
            return null;
        }

        return $response;
    }

    // =========================================================================
    // FETCH: Withdraw history
    // Endpoint: GET /sapi/v1/capital/withdraw/history
    // =========================================================================

    /**
     * Get withdrawal history for a coin.
     */
    public function getWithdrawHistory(string $coin = 'USDT', int $limit = 1000): ?array
    {
        $response = $this->signedGet('/sapi/v1/capital/withdraw/history', [
            'coin'       => strtoupper($coin),
            'limit'      => $limit,
            'recvWindow' => 60000,
        ]);

        if ($response === null) {
            return null;
        }

        return $response;
    }

    // =========================================================================
    // FETCH: Dust log (small asset conversions)
    // Endpoint: GET /sapi/v1/asset/dribblet
    // =========================================================================

    /**
     * Get dust conversion log.
     */
    public function getDustLog(): ?array
    {
        $response = $this->signedGet('/sapi/v1/asset/dribblet', [
            'recvWindow' => 60000,
        ]);

        if ($response === null) {
            return null;
        }

        return $response['results'] ?? null;
    }

    // =========================================================================
    // FETCH: Dividend / Earn history
    // Endpoint: GET /sapi/v1/asset/assetDividend
    // =========================================================================

    /**
     * Get asset dividend history (staking rewards, savings interest, etc.)
     */
    public function getDividendHistory(string $asset = '', int $limit = 500): ?array
    {
        $params = ['limit' => $limit, 'recvWindow' => 60000];
        if (!empty($asset)) {
            $params['asset'] = strtoupper($asset);
        }

        $response = $this->signedGet('/sapi/v1/asset/assetDividend', $params);

        if ($response === null) {
            return null;
        }

        return $response['rows'] ?? null;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Get last error message.
     */
    public function getLastError(): string
    {
        return $this->lastError ?: 'Unknown error occurred.';
    }

    /**
     * Signed GET request (HMAC-SHA256).
     */
    private function signedGet(string $path, array $params = []): ?array
    {
        $params['timestamp'] = $this->milliseconds();
        $query               = http_build_query($params);
        $signature           = hash_hmac('sha256', $query, $this->apiSecret);
        $url                 = self::BASE_URL . $path . '?' . $query . '&signature=' . $signature;

        return $this->request('GET', $url);
    }

    /**
     * Signed POST request (HMAC-SHA256).
     */
    private function signedPost(string $path, array $params = []): ?array
    {
        $params['timestamp'] = $this->milliseconds();
        $query               = http_build_query($params);
        $signature           = hash_hmac('sha256', $query, $this->apiSecret);
        $url                 = self::BASE_URL . $path;

        return $this->request('POST', $url, $query . '&signature=' . $signature);
    }

    /**
     * Execute HTTP request via cURL.
     */
    private function request(string $method, string $url, string $body = ''): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'X-MBX-APIKEY: ' . $this->apiKey,
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw  = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || $raw === false) {
            $this->lastError = $err ? 'Connection error: ' . $err : 'Empty response from Binance API.';
            logTransaction('binance', ['curl_error' => $err], 'cURL Error');
            return null;
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->lastError = 'Invalid response from Binance API.';
            logTransaction('binance', ['raw' => substr($raw, 0, 500)], 'Invalid JSON');
            return null;
        }

        // Binance error: {"code": -XXXX, "msg": "..."}
        if (isset($decoded['code']) && is_int($decoded['code']) && $decoded['code'] < 0) {
            $this->lastError = $decoded['msg'] ?? 'Binance API error code: ' . $decoded['code'];
            logTransaction('binance', $decoded, 'API Error');
            return null;
        }

        return $decoded;
    }

    /**
     * Current Unix timestamp in milliseconds.
     */
    private function milliseconds(): int
    {
        return (int)(microtime(true) * 1000);
    }
}
