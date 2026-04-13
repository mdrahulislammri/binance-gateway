<?php
/**
 * Binance Pay — Payment Verify Page
 * Standalone script — upload & use anywhere
 * @author Md Rahul Islam <https://github.com/mdrahulislammri>
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/BinanceClient.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$error   = null;
$success = null;
$balance = (float)($_SESSION['balance'] ?? 0);

if (!empty($_SESSION['toast'])) {
    $toast = $_SESSION['toast'];
    unset($_SESSION['toast']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = trim($_POST['order_id'] ?? '');
    $amount  = (float)($_POST['amount'] ?? 0);

    if (empty($orderId) || $amount <= 0) {
        $error = 'Please fill in all fields.';
    } elseif (in_array($orderId, $_SESSION['used_ids'] ?? [])) {
        $error = 'This Order ID has already been used.';
    } else {
        $client = new BinanceClient(BINANCE_API_KEY, BINANCE_API_SECRET);
        $result = $client->verifyByOrderId($orderId, $amount, COIN, MAX_AGE_HOURS);

        if ($result === null) {
            $error = $client->getError();
        } else {
            $_SESSION['used_ids'][] = $orderId;
            $_SESSION['balance']    = round($balance + $amount, 2);
            $_SESSION['toast']      = ['type' => 'success', 'msg' => number_format($amount, 2) . ' ' . COIN . ' added to balance!'];
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

$balance = (float)($_SESSION['balance'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Binance Pay — Verify Payment</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{binance:'#F0B90B',dark:'#1E2026'}}}}</script>
<style>
  @keyframes slideIn{from{transform:translateY(-20px);opacity:0}to{transform:translateY(0);opacity:1}}
  @keyframes fadeOut{from{opacity:1}to{opacity:0}}
  .toast-enter{animation:slideIn .3s ease forwards}
  .toast-exit{animation:fadeOut .4s ease forwards}
</style>
</head>
<body class="bg-gray-100 min-h-screen flex items-start sm:items-center justify-center px-4 py-8 sm:py-12">

<?php if (!empty($toast)): ?>
<div id="toast" class="toast-enter fixed top-4 left-4 right-4 sm:left-1/2 sm:right-auto sm:-translate-x-1/2 sm:min-w-80 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl text-sm font-semibold <?= $toast['type']==='success'?'bg-green-500':'bg-red-500' ?> text-white">
  <?php if($toast['type']==='success'): ?>
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
  <?php else: ?>
    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
  <?php endif; ?>
  <span class="flex-1"><?= htmlspecialchars($toast['msg']) ?></span>
  <button onclick="dismissToast()" class="opacity-70 hover:opacity-100">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
  </button>
</div>
<script>
function dismissToast(){var t=document.getElementById('toast');if(t){t.classList.remove('toast-enter');t.classList.add('toast-exit');setTimeout(function(){t.remove()},400)}}
setTimeout(dismissToast,3500);
</script>
<?php endif; ?>

<div class="w-full max-w-md mx-auto">

  <div class="bg-dark rounded-t-2xl px-6 py-4 flex items-center gap-3">
    <img src="https://bin.bnbstatic.com/static/images/common/logo.png" alt="Binance" style="height:28px;width:auto;">
    <span class="text-binance font-bold text-lg">Binance Pay — Verify Payment</span>
  </div>

  <div class="bg-white rounded-b-2xl shadow-lg px-6 py-6 space-y-5">

    <!-- Balance -->
    <div class="bg-dark rounded-xl px-5 py-4 flex items-center justify-between">
      <div>
        <p class="text-xs text-gray-400 mb-0.5">Balance</p>
        <p class="text-2xl font-bold text-binance"><?= number_format($balance, 2) ?> <span class="text-sm font-normal text-gray-400"><?= COIN ?></span></p>
      </div>
    </div>

    <!-- UID -->
    <div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3">
      <p class="text-xs text-gray-500 mb-1.5 font-medium">Binance UID</p>
      <div class="flex items-center gap-2">
        <span id="bnc-uid" class="flex-1 font-mono text-sm font-bold text-dark"><?= htmlspecialchars(BINANCE_UID) ?></span>
        <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars(BINANCE_UID) ?>');this.innerText='Copied';"
          class="bg-binance hover:bg-yellow-500 text-dark text-xs font-bold px-3 py-1.5 rounded-lg transition">Copy</button>
      </div>
    </div>

    <!-- Error -->
    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 flex items-start gap-2">
      <svg class="w-4 h-4 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      <span class="text-red-700 text-sm"><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="post" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Amount (<?= COIN ?>)</label>
        <input type="number" name="amount" step="0.01" min="0.01" placeholder="e.g. 25.00"
          value="<?= ($error ? htmlspecialchars($_POST['amount'] ?? '') : '') ?>" required
          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-yellow-400 transition">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Order ID</label>
        <input type="text" name="order_id" placeholder="Paste Binance Order ID"
          value="<?= ($error ? htmlspecialchars($_POST['order_id'] ?? '') : '') ?>" required autocomplete="off"
          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-yellow-400 transition">
        <p class="text-xs text-gray-400 mt-1">Binance App → Wallets → Transaction History → tap payment → copy Order ID</p>
      </div>
      <button type="submit" class="w-full bg-binance hover:bg-yellow-500 text-dark font-bold text-sm rounded-xl py-3 transition shadow">
        Verify Payment
      </button>
    </form>

  </div>

  <div class="flex items-center justify-center gap-1.5 mt-4">
    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
    <span class="text-xs text-gray-400">Verified directly via Binance API</span>
  </div>

</div>
</body>
</html>
