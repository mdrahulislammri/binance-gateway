# Binance Gateway

A collection of Binance Pay payment gateway integrations for various platforms and frameworks.

## Supported Platforms

| Platform | Status |
|----------|--------|
| WHMCS | ✅ Ready |
| WooCommerce (WordPress) | ✅ Ready |
| SMM Panel | 🔄 Coming Soon |
| Standalone Script | 🔄 Coming Soon |
| Bot — Python | 🔄 Coming Soon |
| Bot — PHP | 🔄 Coming Soon |
| Bot — JavaScript (Node.js) | 🔄 Coming Soon |

---

## How It Works

1. Customer sees your **Binance UID** on the payment page
2. Customer sends exact USDT amount via **Binance Pay** (Send → UID)
3. Customer pastes their **Order ID** from Binance app
4. System verifies via **Binance API** — amount, coin, and time checked
5. Payment confirmed automatically — no manual approval needed

---

## Directory Structure

```
binance-gateway/
├── whmcs/
│   └── modules/
│       └── gateways/
│           ├── binance.php
│           ├── binance/
│           │   └── BinanceClient.php
│           └── callback/
│               └── binance.php
│
├── woocommerce/
│   └── wc-binance-pay/
│       ├── wc-binance-pay.php
│       └── includes/
│           ├── class-binance-client.php
│           └── class-wc-binance-gateway.php
│
├── testing/
│   ├── binance-test.php
│   ├── bitmart-test.php
│   └── bybit-test.php
│
├── smm/                  (coming soon)
├── bots/
│   ├── python/           (coming soon)
│   ├── php/              (coming soon)
│   └── javascript/       (coming soon)
│
└── README.md
```

---

## WHMCS Installation

1. Copy `whmcs/modules/gateways/binance.php` → `/modules/gateways/binance.php`
2. Copy `whmcs/modules/gateways/binance/` → `/modules/gateways/binance/`
3. Copy `whmcs/modules/gateways/callback/binance.php` → `/modules/gateways/callback/binance.php`
4. Go to **Admin → Setup → Payment Gateways → All Payment Gateways**
5. Activate **Binance Pay (USDT)** and configure:
   - API Key (Read-Only)
   - API Secret
   - Binance UID

---

## WooCommerce Installation

1. Upload `woocommerce/wc-binance-pay/` to `/wp-content/plugins/`
2. Activate in **WordPress → Plugins**
3. Go to **WooCommerce → Settings → Payments**
4. Enable **Binance Pay (USDT)** and configure:
   - API Key (Read-Only)
   - API Secret
   - Binance UID

---

## Testing

Upload any file from `testing/` to your PHP server, set your credentials at the top, and open in browser.

---

## Binance API Setup

1. Login to [Binance](https://www.binance.com)
2. Go to **Profile → API Management**
3. Create API key with **Read Only** permission only
4. Copy **API Key** and **Secret Key**

---

## Requirements

- PHP 7.4+
- Binance account with Read-Only API access

---

## License

MIT License

---

## Credits

Developed by [Md Rahul Islam](https://github.com/mdrahulislammri)
