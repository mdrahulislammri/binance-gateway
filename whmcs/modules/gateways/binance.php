<?php

/**
 * Binance Personal Account — WHMCS Payment Gateway
 *
 * How it works:
 *   1. Customer sees your Binance UID on the invoice page.
 *   2. Customer sends exact USDT amount via Binance Pay to your UID.
 *   3. Customer pastes their Order ID and clicks "Verify Payment".
 *   4. Module calls Binance Pay API, finds the Order ID, checks amount.
 *   5. If everything matches → invoice is marked as paid automatically.
 *
 * Required Binance API permission: Enable Reading ONLY.
 *
 * Installation:
 *   - Copy to /modules/gateways/binance.php
 *   - Copy /modules/gateways/binance/ folder
 *   - Copy /modules/gateways/callback/binance.php
 *   - Activate in WHMCS Admin → Setup → Payment Gateways
 *
 * @author  Md Rahul Islam <https://github.com/mdrahulislammri>
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/binance/BinanceClient.php';

// ─────────────────────────────────────────────────────────────────────────────
// Metadata
// ─────────────────────────────────────────────────────────────────────────────
function binance_MetaData()
{
    return [
        'DisplayName'                => 'Binance Pay (USDT)',
        'APIVersion'                 => '1.1',
        'DisableLocalCreditCardForm' => true,
        'langPayNow'                 => 'Pay with Binance Pay',
        'failedMessage'              => 'Payment verification failed. Please try again.',
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Admin configuration fields
// ─────────────────────────────────────────────────────────────────────────────
function binance_config()
{
    return [
        'FriendlyName' => [
            'Type'  => 'System',
            'Value' => 'Binance Pay (USDT)',
        ],
        'binance_api_key' => [
            'FriendlyName' => 'API Key',
            'Type'         => 'text',
            'Size'         => '64',
            'Default'      => '',
            'Description'  => 'Binance API Key — Enable Reading permission only.',
        ],
        'binance_api_secret' => [
            'FriendlyName' => 'API Secret',
            'Type'         => 'password',
            'Size'         => '64',
            'Default'      => '',
            'Description'  => 'Binance API Secret Key.',
        ],
        'binance_uid' => [
            'FriendlyName' => 'Binance UID',
            'Type'         => 'text',
            'Size'         => '20',
            'Default'      => '',
            'Description'  => 'Your Binance UID (customers will send to this UID via Binance Pay).',
        ],
        'binance_coin' => [
            'FriendlyName' => 'Accepted Coin',
            'Type'         => 'text',
            'Size'         => '10',
            'Default'      => 'USDT',
            'Description'  => 'Coin to accept (e.g. USDT).',
        ],
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Payment form shown on invoice page
// ─────────────────────────────────────────────────────────────────────────────
function binance_link($params)
{
    $apiKey    = trim($params['binance_api_key']    ?? '');
    $apiSecret = trim($params['binance_api_secret'] ?? '');
    $uid       = trim($params['binance_uid']        ?? '');
    $coin      = strtoupper(trim($params['binance_coin'] ?? 'USDT'));

    if (empty($apiKey) || empty($apiSecret) || empty($uid)) {
        return '<p style="color:#c0392b;font-weight:600;">
                    Binance gateway is not configured. Please contact support.
                </p>';
    }

    $invoiceId = (int)$params['invoiceid'];
    $amount    = number_format((float)$params['amount'], 2, '.', '');
    $verifyUrl = $params['systemurl'] . 'modules/gateways/callback/binance.php';
    $uidSafe   = htmlspecialchars($uid, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:480px;margin:0 auto;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.10);">

    <!-- Header -->
    <div style="background:linear-gradient(135deg,#1E2026 0%,#2d3139 100%);padding:20px 24px;display:flex;align-items:center;gap:12px;">
        <img src="https://bin.bnbstatic.com/static/images/common/logo.png" alt="Binance" style="height:32px;width:auto;" />
        <div>
            <div style="color:#F0B90B;font-size:16px;font-weight:700;line-height:1.2;">Pay with Binance Pay</div>
            <div style="color:#888;font-size:12px;margin-top:2px;">Fast &amp; Secure Crypto Payment</div>
        </div>
    </div>

    <!-- Amount -->
    <div style="background:#fff;padding:20px 24px;border-bottom:1px solid #f0f0f0;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-size:12px;color:#aaa;margin-bottom:4px;">Invoice #{$invoiceId}</div>
                <div style="font-size:13px;color:#888;">Amount Due</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:32px;font-weight:800;color:#1E2026;line-height:1;">{$amount}</div>
                <div style="font-size:13px;color:#F0B90B;font-weight:600;margin-top:2px;">{$coin}</div>
            </div>
        </div>
    </div>

    <!-- Step 1: UID -->
    <div style="background:#fafafa;padding:20px 24px;border-bottom:1px solid #f0f0f0;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
            <div style="width:22px;height:22px;background:#F0B90B;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#1E2026;flex-shrink:0;">1</div>
            <div style="font-size:13px;font-weight:600;color:#1E2026;">Send payment to this Binance UID</div>
        </div>

        <div style="background:#fff;border:1.5px solid #F0B90B;border-radius:10px;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <div style="font-size:11px;color:#aaa;margin-bottom:3px;text-transform:uppercase;letter-spacing:0.5px;">Binance UID</div>
                <div id="bnc-uid" style="font-size:24px;font-weight:800;color:#1E2026;letter-spacing:2px;">{$uidSafe}</div>
            </div>
            <button onclick="navigator.clipboard.writeText('{$uidSafe}');this.innerHTML='<svg width=&quot;14&quot; height=&quot;14&quot; fill=&quot;none&quot; stroke=&quot;currentColor&quot; stroke-width=&quot;2.5&quot; viewBox=&quot;0 0 24 24&quot;><path stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot; d=&quot;M5 13l4 4L19 7&quot;/></svg> Copied';"
                    style="background:#F0B90B;border:none;border-radius:8px;padding:10px 16px;font-size:12px;font-weight:700;color:#1E2026;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:6px;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-4 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                Copy
            </button>
        </div>

        <div style="margin-top:10px;font-size:12px;color:#888;line-height:1.6;">
            Binance App &rarr; <strong>Pay</strong> &rarr; <strong>Send</strong> &rarr; enter UID &rarr; send exactly <strong style="color:#1E2026;">{$amount} {$coin}</strong>
        </div>
    </div>

    <!-- Step 2: Verify -->
    <div style="background:#fff;padding:20px 24px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
            <div style="width:22px;height:22px;background:#1E2026;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;color:#F0B90B;flex-shrink:0;">2</div>
            <div style="font-size:13px;font-weight:600;color:#1E2026;">Confirm your payment</div>
        </div>

        <div id="binance-msg" style="display:none;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:12px;"></div>

        <form method="post" action="{$verifyUrl}" id="binance-verify-form" onsubmit="return binanceValidate();">
            <input type="hidden" name="invoiceid" value="{$invoiceId}" />
            <input type="hidden" name="amount"    value="{$amount}" />
            <input type="hidden" name="coin"      value="{$coin}" />

            <input type="text"
                   name="reference"
                   id="binance-ref"
                   placeholder="Paste your Order ID here..."
                   required
                   autocomplete="off"
                   style="width:100%;box-sizing:border-box;padding:13px 16px;border:1.5px solid #e0e0e0;
                          border-radius:10px;font-size:14px;color:#1E2026;outline:none;
                          margin-bottom:12px;transition:border-color .2s;background:#fafafa;" />

            <button type="submit" id="binance-submit-btn"
                    style="width:100%;padding:14px;background:#F0B90B;border:none;border-radius:10px;
                           font-size:15px;font-weight:700;color:#1E2026;cursor:pointer;
                           box-shadow:0 4px 12px rgba(240,185,11,0.4);transition:opacity .2s;">
                Verify &amp; Confirm Payment
            </button>
        </form>

        <div style="margin-top:14px;background:#f8f9fa;border-radius:10px;padding:12px 16px;">
            <div style="font-size:12px;color:#555;font-weight:600;margin-bottom:4px;">Where to find your Order ID:</div>
            <div style="font-size:12px;color:#888;line-height:1.7;">
                Binance App &rarr; <strong>Wallets</strong> &rarr; <strong>Transaction History</strong> &rarr; tap the payment &rarr; copy <strong>Order ID</strong>
            </div>
        </div>

        <div style="margin-top:14px;text-align:center;display:flex;align-items:center;justify-content:center;gap:6px;">
            <svg width="13" height="13" fill="none" stroke="#bbb" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <span style="font-size:11px;color:#bbb;">Verified directly via Binance API</span>
        </div>
    </div>

</div>

<script>
function binanceValidate() {
    var ref = document.getElementById('binance-ref').value.trim();
    var msg = document.getElementById('binance-msg');
    if (!ref) {
        msg.style.display = 'block';
        msg.style.background = '#fdecea';
        msg.style.color = '#c0392b';
        msg.innerText = 'Please enter your Order ID.';
        return false;
    }
    var btn = document.getElementById('binance-submit-btn');
    btn.disabled = true;
    btn.style.opacity = '0.7';
    btn.innerText = 'Verifying...';
    return true;
}
document.getElementById('binance-ref').addEventListener('focus', function() {
    this.style.borderColor = '#F0B90B';
    this.style.background = '#fff';
});
document.getElementById('binance-ref').addEventListener('blur', function() {
    this.style.borderColor = '#e0e0e0';
    this.style.background = '#fafafa';
});
</script>
HTML;
}
