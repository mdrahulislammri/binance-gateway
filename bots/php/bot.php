<?php
/**
 * Binance Pay — PHP Telegram Bot (Webhook)
 * Author: Md Rahul Islam <https://github.com/mdrahulislammri>
 *
 * Setup:
 *   1. Upload all files to your server
 *   2. Set webhook: https://api.telegram.org/bot{TOKEN}/setWebhook?url=https://yourdomain.com/bot.php
 *   3. Fill config.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/binance.php';

// ─── Read Telegram update ─────────────────────────────────────────────────────
$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

$message  = $update['message'] ?? $update['callback_query']['message'] ?? null;
if (!$message) exit;

$chatId   = $message['chat']['id'];
$userId   = $message['from']['id'];
$username = $message['from']['username'] ?? '';
$text     = trim($message['text'] ?? '');

// ─── Ensure user exists ───────────────────────────────────────────────────────
ensure_user($userId, $username);

// ─── Telegram send helper ─────────────────────────────────────────────────────
function send(int $chatId, string $text, array $extra = []): void {
    $payload = array_merge(['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'], $extra);
    $ch = curl_init('https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ─── State machine ────────────────────────────────────────────────────────────
$stateInfo = get_state($userId);
$state     = $stateInfo['state'];
$stateData = $stateInfo['data'];

// ── Commands ──────────────────────────────────────────────────────────────────
if ($text === '/start') {
    set_state($userId, null);
    send($chatId,
        "Welcome to *Binance Pay Bot*!\n\n" .
        "/deposit — Add funds\n" .
        "/balance — Check balance\n" .
        "/cancel  — Cancel current action"
    );
    exit;
}

if ($text === '/cancel') {
    set_state($userId, null);
    send($chatId, "Cancelled.");
    exit;
}

if ($text === '/balance') {
    $bal = get_balance($userId);
    send($chatId, "Your balance: *" . number_format($bal, 2) . " " . COIN . "*");
    exit;
}

if ($text === '/deposit') {
    set_state($userId, 'ENTER_AMOUNT');
    send($chatId, "Enter the amount you want to deposit (" . COIN . "):\n\nExample: `10.00`");
    exit;
}

// ── State: waiting for amount ─────────────────────────────────────────────────
if ($state === 'ENTER_AMOUNT') {
    $amount = (float)$text;
    if ($amount <= 0 || !is_numeric($text)) {
        send($chatId, "Invalid amount. Please enter a valid number (e.g. `10.00`):");
        exit;
    }

    set_state($userId, 'ENTER_ORDER_ID', ['amount' => $amount]);

    send($chatId,
        "Send exactly *" . number_format($amount, 2) . " " . COIN . "* to this Binance UID:\n\n" .
        "`" . BINANCE_UID . "`\n\n" .
        "Binance App → *Pay* → *Send* → enter UID → send *" . number_format($amount, 2) . " " . COIN . "*\n\n" .
        "After sending, paste your *Order ID* below:"
    );
    exit;
}

// ── State: waiting for Order ID ───────────────────────────────────────────────
if ($state === 'ENTER_ORDER_ID') {
    $orderId = trim($text);
    $amount  = (float)($stateData['amount'] ?? 0);

    if (!ctype_digit($orderId) || strlen($orderId) < 15) {
        send($chatId, "Invalid Order ID format. Please check and try again:");
        exit;
    }

    if (is_order_used($orderId)) {
        set_state($userId, null);
        send($chatId, "This Order ID has already been used.");
        exit;
    }

    send($chatId, "Verifying payment, please wait...");

    $result = binance_verify($orderId, $amount);

    if (!$result['ok']) {
        set_state($userId, null);
        send($chatId, "Verification failed:\n" . $result['error']);
        exit;
    }

    add_balance($userId, $amount, $orderId, COIN);
    set_state($userId, null);
    $newBalance = get_balance($userId);

    send($chatId,
        "Payment verified!\n\n" .
        "Amount added: *" . number_format($amount, 2) . " " . COIN . "*\n" .
        "New balance: *" . number_format($newBalance, 2) . " " . COIN . "*"
    );
    exit;
}

// ── Default ───────────────────────────────────────────────────────────────────
send($chatId, "Use /deposit to add funds or /balance to check your balance.");
