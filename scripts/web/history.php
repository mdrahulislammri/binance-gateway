<?php
/**
 * Binance Pay — Transaction History Page
 * @author Md Rahul Islam <https://github.com/mdrahulislammri>
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/BinanceClient.php';

$client       = new BinanceClient(BINANCE_API_KEY, BINANCE_API_SECRET);
$transactions = $client->getPayTransactions(50);
$error        = $transactions === null ? $client->getError() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Binance Pay — Transaction History</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{colors:{binance:'#F0B90B',dark:'#1E2026'}}}}</script>
</head>
<body class="bg-gray-100 min-h-screen px-4 py-8">
<div class="max-w-2xl mx-auto">

  <div class="bg-dark rounded-t-2xl px-6 py-4 flex items-center gap-3">
    <img src="https://bin.bnbstatic.com/static/images/common/logo.png" alt="Binance" style="height:28px;width:auto;">
    <span class="text-binance font-bold text-lg">Transaction History</span>
  </div>

  <div class="bg-white rounded-b-2xl shadow-lg px-6 py-6">
    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm"><?= htmlspecialchars($error) ?></div>
    <?php elseif (empty($transactions)): ?>
      <p class="text-gray-400 text-sm text-center py-8">No transactions found.</p>
    <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($transactions as $tx):
          $amount   = (float)($tx['amount'] ?? 0);
          $isCredit = $amount > 0;
          $time     = isset($tx['transactionTime']) ? date('Y-m-d H:i', (int)($tx['transactionTime']/1000)) : '-';
        ?>
        <div class="flex items-center justify-between border border-gray-100 rounded-xl px-4 py-3">
          <div>
            <p class="text-xs text-gray-400"><?= $time ?></p>
            <p class="text-xs font-mono text-gray-500 mt-0.5"><?= htmlspecialchars($tx['orderId'] ?? '-') ?></p>
            <p class="text-xs text-gray-400"><?= htmlspecialchars($tx['orderType'] ?? '') ?></p>
          </div>
          <div class="text-right">
            <p class="font-bold <?= $isCredit ? 'text-green-600' : 'text-red-500' ?>">
              <?= $isCredit ? '+' : '' ?><?= number_format(abs($amount), 2) ?> <?= htmlspecialchars($tx['currency'] ?? COIN) ?>
            </p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
