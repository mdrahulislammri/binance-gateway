<?php

/**
 * Binance — Payment Verification Callback
 *
 * Supports two verification methods:
 *   1. Binance Pay Order ID  → verifyByOrderId()
 *   2. On-chain TxID         → verifyByTxId()
 *
 * Customer submits either one from the invoice page.
 * System auto-detects which method to use based on input format.
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../binance/BinanceClient.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$gatewayModuleName = 'binance';
$gatewayParams     = getGatewayVariables($gatewayModuleName);

if (empty($gatewayParams['type'])) {
    die('Gateway not activated.');
}

// ── Read submitted form data ──────────────────────────────────────────────────
$invoiceId = (int)($_POST['invoiceid'] ?? 0);
$reference = trim($_POST['reference']  ?? '');   // Order ID or TxID
$amount    = (float)($_POST['amount']  ?? 0);
$coin      = strtoupper(trim($_POST['coin'] ?? 'USDT'));

$invoiceUrl = $gatewayParams['systemurl'] . 'viewinvoice.php?id=' . $invoiceId;

// ── Basic validation ──────────────────────────────────────────────────────────
if ($invoiceId <= 0 || empty($reference) || $amount <= 0) {
    $_SESSION['binance_error'] = 'Invalid submission. Please fill in all fields and try again.';
    header('Location: ' . $invoiceUrl);
    exit;
}

// ── Prevent duplicate payment recording ──────────────────────────────────────
$isDuplicate = localAPI('GetTransactions', ['transid' => $reference]);
if (!empty($isDuplicate['transactions']['transaction'])) {
    $_SESSION['binance_error'] = 'This payment reference has already been used.';
    header('Location: ' . $invoiceUrl);
    exit;
}

// ── Validate invoice ──────────────────────────────────────────────────────────
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayModuleName);

// ── Check invoice is still unpaid ────────────────────────────────────────────
$invoiceData = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);
if (($invoiceData['status'] ?? '') === 'Paid') {
    $_SESSION['binance_error'] = 'This invoice has already been paid.';
    header('Location: ' . $invoiceUrl);
    exit;
}

// ── Init Binance client ───────────────────────────────────────────────────────
$apiKey    = trim($gatewayParams['binance_api_key']    ?? '');
$apiSecret = trim($gatewayParams['binance_api_secret'] ?? '');
$client    = new BinanceClient($apiKey, $apiSecret);

// ── Auto-detect verification method ──────────────────────────────────────────
// Binance Pay Order ID  → pure numeric, 18-19 digits
// On-chain TxID         → hex string, usually 64 chars (or longer)
$isOrderId = ctype_digit($reference) && strlen($reference) >= 15;

$deposit = null;

if ($isOrderId) {
    // Method 1: Binance Pay Order ID
    $deposit = $client->verifyByOrderId($reference, $amount, $coin);
} else {
    // Method 2: On-chain TxID
    $deposit = $client->verifyByTxId($reference, $amount, $coin);
}

// ── Handle verification failure ───────────────────────────────────────────────
if ($deposit === null) {
    $error = htmlspecialchars($client->getLastError(), ENT_QUOTES, 'UTF-8');

    logTransaction($gatewayModuleName, [
        'invoiceId' => $invoiceId,
        'reference' => $reference,
        'method'    => $isOrderId ? 'OrderId' : 'TxId',
        'amount'    => $amount,
        'coin'      => $coin,
        'error'     => $error,
    ], 'Verification Failed');

    $_SESSION['binance_error'] = $error;
    header('Location: ' . $invoiceUrl);
    exit;
}

// ── Record payment in WHMCS ───────────────────────────────────────────────────
// For Binance Pay, amount can be negative (outgoing) — use abs()
$paidAmount = abs((float)($deposit['amount'] ?? $amount));

addInvoicePayment(
    $invoiceId,
    $reference,
    $paidAmount,
    0,
    $gatewayModuleName
);

logTransaction($gatewayModuleName, [
    'invoiceId' => $invoiceId,
    'reference' => $reference,
    'method'    => $isOrderId ? 'Binance Pay Order ID' : 'On-chain TxID',
    'amount'    => $paidAmount,
    'coin'      => $coin,
    'detail'    => $deposit,
], 'Payment Verified & Recorded');

// ── Redirect back to invoice ──────────────────────────────────────────────────
$_SESSION['binance_success'] = 'Payment verified successfully! Your invoice has been marked as paid.';
header('Location: ' . $invoiceUrl);
exit;
