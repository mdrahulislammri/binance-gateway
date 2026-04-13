<?php

// ─── CONFIG ───────────────────────────────────────────────────────────────────
$API_KEY       = 'YOUR_BINANCE_API_KEY';
$API_SECRET    = 'YOUR_BINANCE_API_SECRET';
$BINANCE_UID   = 'YOUR_BINANCE_UID';
$USED_IDS_FILE = __DIR__ . '/used_ids.json';
$BALANCE_FILE  = __DIR__ . '/balance.json';
$MAX_AGE_HOURS = 24;
// ─────────────────────────────────────────────────────────────────────────────

// ─── Duplicate check ──────────────────────────────────────────────────────────
function isAlreadyUsed(string $id, string $file): bool {
    if (!file_exists($file)) return false;
    $data = json_decode(file_get_contents($file), true) ?? [];
    return in_array($id, $data, true);
}
function markAsUsed(string $id, string $file): void {
    $data = file_exists($file) ? (json_decode(file_get_contents($file), true) ?? []) : [];
    $data[] = $id;
    file_put_contents($file, json_encode(array_values(array_unique($data))));
}

// ─── Balance ──────────────────────────────────────────────────────────────────
function getBalance(string $file): float {
    if (!file_exists($file)) return 0.0;
    $data = json_decode(file_get_contents($file), true) ?? [];
    return (float)($data['balance'] ?? 0);
}
function addBalance(float $amount, string $id, string $file): void {
    $data = file_exists($file) ? (json_decode(file_get_contents($file), true) ?? []) : [];
    $data['balance'] = round((float)($data['balance'] ?? 0) + $amount, 2);
    $data['history'][] = [
        'id'     => $id,
        'amount' => $amount,
        'time'   => date('Y-m-d H:i:s'),
    ];
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// ─── BinanceClient ────────────────────────────────────────────────────────────
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

    public function verifyByOrderId(string $orderId, float $amount, string $coin, int $maxAgeHours): ?array
    {
        $transactions = $this->getPayTransactions(100);
        if ($transactions === null) return null;

        foreach ($transactions as $tx) {
            if (($tx['orderId'] ?? '') !== $orderId) continue;

            // Incoming = positive amount, outgoing = negative
            $txAmount = (float)($tx['amount'] ?? 0);
            if ($txAmount <= 0) {
                $this->lastError = 'This is an outgoing payment, not an incoming one.';
                return null;
            }

            // Coin must match
            if (strtoupper($tx['currency'] ?? '') !== strtoupper($coin)) {
                $this->lastError = 'Coin mismatch. Expected ' . $coin . ' but got ' . ($tx['currency'] ?? '');
                return null;
            }

            // Amount must match
            if (abs($txAmount - $amount) > 0.01) {
                $this->lastError = sprintf('Amount mismatch. Expected %.2f but got %.2f %s.', $amount, $txAmount, $coin);
                return null;
            }

            // Age check
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

    public function verifyByTxId(string $txId, float $amount, string $coin, int $maxAgeHours): ?array
    {
        $deposits = $this->getDepositHistory($coin, 1000);
        if ($deposits === null) return null;

        foreach ($deposits as $deposit) {
            if (strcasecmp($deposit['txId'] ?? '', $txId) !== 0) continue;
            if ((int)($deposit['status'] ?? -1) !== 1) {
                $this->lastError = 'Transaction found but not yet confirmed on blockchain.';
                return null;
            }
            if (abs((float)($deposit['amount'] ?? 0) - $amount) > 0.01) {
                $this->lastError = sprintf('Amount mismatch. Expected %.2f but got %.2f %s.', $amount, (float)($deposit['amount'] ?? 0), $coin);
                return null;
            }
            $txTime = (int)(($deposit['insertTime'] ?? 0) / 1000);
            if ($txTime > 0 && (time() - $txTime) > ($maxAgeHours * 3600)) {
                $this->lastError = 'Transaction is older than ' . $maxAgeHours . ' hours.';
                return null;
            }
            return $deposit;
        }

        $this->lastError = 'TxID not found in deposit history.';
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

    public function getDepositHistory(string $coin = 'USDT', int $limit = 1000): ?array
    {
        $response = $this->signedGet('/sapi/v1/capital/deposit/hisrec', [
            'coin' => strtoupper($coin), 'limit' => $limit, 'recvWindow' => 60000,
        ]);
        if ($response === null) return null;
        if (!is_array($response)) {
            $this->lastError = 'Unexpected response from deposit history API.';
            return null;
        }
        return $response;
    }

    public function getLastError(): string { return $this->lastError ?: 'Unknown error.'; }

    private function signedGet(string $path, array $params = []): ?array
    {
        $params['timestamp'] = (int)(microtime(true) * 1000);
        $query     = http_build_query($params);
        $signature = hash_hmac('sha256', $query, $this->apiSecret);
        return $this->request('GET', self::BASE_URL . $path . '?' . $query . '&signature=' . $signature);
    }

    private function request(string $method, string $url, string $body = ''): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['X-MBX-APIKEY: ' . $this->apiKey, 'Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err)                          { $this->lastError = 'Connection error: ' . $err; return null; }
        if ($raw === false || $raw === '') { $this->lastError = 'Empty response from API.'; return null; }
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) { $this->lastError = 'Invalid API response.'; return null; }
        if (isset($decoded['code']) && is_int($decoded['code']) && $decoded['code'] < 0) {
            $this->lastError = $decoded['msg'] ?? 'Binance API error: ' . $decoded['code'];
            return null;
        }
        return $decoded;
    }
}

// ─── Handle form ──────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
$result   = null;
$toast    = null;
$balance  = getBalance($BALANCE_FILE);

// Pick up success toast from redirect
if (!empty($_SESSION['toast'])) {
    $toast = $_SESSION['toast'];
    unset($_SESSION['toast']);
    $balance = getBalance($BALANCE_FILE);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reference = trim($_POST['reference'] ?? '');
    $amount    = (float)($_POST['amount'] ?? 0);

    if (empty($reference) || $amount <= 0) {
        $toast = ['type' => 'error', 'msg' => 'Please fill in both fields.'];
    } elseif (isAlreadyUsed($reference, $USED_IDS_FILE)) {
        $toast = ['type' => 'error', 'msg' => 'This Order ID / TxID has already been used.'];
    } else {
        $client    = new BinanceClient($API_KEY, $API_SECRET);
        $isOrderId = ctype_digit($reference) && strlen($reference) >= 15;
        $result    = $isOrderId
            ? $client->verifyByOrderId($reference, $amount, 'USDT', $MAX_AGE_HOURS)
            : $client->verifyByTxId($reference, $amount, 'USDT', $MAX_AGE_HOURS);

        if ($result === null) {
            $toast = ['type' => 'error', 'msg' => $client->getLastError()];
        } else {
            markAsUsed($reference, $USED_IDS_FILE);
            addBalance($amount, $reference, $BALANCE_FILE);
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Payment verified! ' . number_format($amount, 2) . ' USDT added to balance.'];
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Binance Payment Verify</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: { extend: { colors: { binance: '#F0B90B', dark: '#1E2026' } } }
  }
</script>
<style>
  @keyframes slideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to   { transform: translateY(0);     opacity: 1; }
  }
  @keyframes fadeOut {
    from { opacity: 1; }
    to   { opacity: 0; }
  }
  .toast-enter { animation: slideIn 0.3s ease forwards; }
  .toast-exit  { animation: fadeOut 0.4s ease forwards; }
</style>
</head>
<body class="bg-gray-100 min-h-screen flex items-start sm:items-center justify-center px-4 py-8 sm:py-12">

<!-- Toast -->
<?php if ($toast): ?>
<div id="toast"
  class="toast-enter fixed top-4 left-4 right-4 sm:left-1/2 sm:right-auto sm:-translate-x-1/2 sm:min-w-80 sm:max-w-sm z-50 flex items-center gap-3 px-4 py-3 sm:px-5 rounded-2xl shadow-xl text-sm font-semibold
  <?= $toast['type'] === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white' ?>">
  <?php if ($toast['type'] === 'success'): ?>
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
    </svg>
  <?php else: ?>
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
    </svg>
  <?php endif; ?>
  <span class="flex-1"><?= htmlspecialchars($toast['msg']) ?></span>
  <button onclick="dismissToast()" class="shrink-0 opacity-70 hover:opacity-100 transition">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
    </svg>
  </button>
</div>
<script>
  function dismissToast() {
    var t = document.getElementById('toast');
    if (t) { t.classList.remove('toast-enter'); t.classList.add('toast-exit');
      setTimeout(function() { t.remove(); }, 400); }
  }
  setTimeout(dismissToast, 3500);
</script>
<?php endif; ?>

<div class="w-full max-w-md mx-auto">

  <!-- Header -->
  <div class="bg-dark rounded-t-2xl px-6 py-4 flex items-center gap-3">
    <img src="https://bin.bnbstatic.com/static/images/common/logo.png" alt="Binance" style="height:28px;width:auto;" />
    <span class="text-binance font-bold text-lg">Binance Payment Verify</span>
  </div>

  <!-- Card -->
  <div class="bg-white rounded-b-2xl shadow-lg px-6 py-6 space-y-5">

    <!-- Balance -->
    <div class="bg-dark rounded-xl px-5 py-4 flex items-center justify-between">
      <div>
        <p class="text-xs text-gray-400 mb-0.5">Total Balance</p>
        <p class="text-2xl font-bold text-binance"><?= number_format($balance, 2) ?> <span class="text-sm font-normal text-gray-400">USDT</span></p>
      </div>
      <svg class="w-8 h-8 text-binance opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25zm0 3v5.25"/>
      </svg>
    </div>

    <!-- Binance UID -->
    <div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3">
      <p class="text-xs text-gray-500 mb-1.5 font-medium">Binance UID</p>
      <div class="flex items-center gap-2">
        <span id="binance-uid" class="flex-1 font-mono text-sm font-bold text-dark"><?= htmlspecialchars($BINANCE_UID) ?></span>
        <button type="button" onclick="copyUID()"
          class="shrink-0 bg-binance hover:bg-yellow-500 text-dark text-xs font-bold px-3 py-1.5 rounded-lg transition flex items-center gap-1">
          <svg id="copy-icon" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-4 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
          </svg>
          <span id="copy-label">Copy</span>
        </button>
      </div>
    </div>

    <!-- Form -->
    <form method="post" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Amount (USDT)</label>
        <input type="number" name="amount" step="0.01" min="0.01"
          placeholder="e.g. 25.00"
          value="<?= $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($toast['type'] === 'error') ? htmlspecialchars($_POST['amount'] ?? '') : '' ?>"
          required
          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-800 outline-none focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 transition">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Order ID / TxID</label>
        <input type="text" name="reference"
          placeholder="Paste Binance Order ID or TxID"
          value="<?= $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($toast['type'] === 'error') ? htmlspecialchars($_POST['reference'] ?? '') : '' ?>"
          required autocomplete="off"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-800 outline-none focus:ring-2 focus:ring-yellow-400 focus:border-yellow-400 transition">
        <p class="text-xs text-gray-400 mt-1">Numeric (15+ digits) = Binance Pay &nbsp;&middot;&nbsp; Hex string = On-chain TxID</p>
      </div>
      <button type="submit"
        class="w-full bg-binance hover:bg-yellow-500 active:bg-yellow-600 text-dark font-bold text-sm rounded-xl py-3 transition shadow">
        Verify Payment
      </button>
    </form>

    <!-- Verified result JSON -->
    <?php if ($result && !$toast): ?>
    <pre class="bg-dark text-binance rounded-xl p-4 text-xs overflow-x-auto leading-relaxed"><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    <?php endif; ?>

  </div>

  <div class="flex items-center justify-center gap-1.5 mt-4">
    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
    </svg>
    <span class="text-xs text-gray-400">Verified directly via Binance API</span>
  </div>

</div>

<script>
function copyUID() {
  var uid = document.getElementById('binance-uid').innerText.trim();
  navigator.clipboard.writeText(uid).then(function() {
    document.getElementById('copy-label').innerText = 'Copied!';
    document.getElementById('copy-icon').innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>';
    setTimeout(function() {
      document.getElementById('copy-label').innerText = 'Copy';
      document.getElementById('copy-icon').innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-4 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>';
    }, 2000);
  });
}
</script>
</body>
</html>
