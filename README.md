# 💰 Binance Gateway

> Accept USDT payments via **Binance Pay (UID Transfer)** — no third-party processor, payments go directly to your Binance account.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://php.net)
[![Platform](https://img.shields.io/badge/Platform-WHMCS%20%7C%20WooCommerce%20%7C%20Telegram-green.svg)](#supported-platforms)

---

## ✅ Supported Platforms

| Platform | Type | Status |
|----------|------|--------|
| **WHMCS** | Payment Gateway | ✅ Ready |
| **WooCommerce** | WordPress Plugin | ✅ Ready |
| **Telegram Bot (Python)** | Bot | ✅ Ready |
| **Telegram Bot (PHP)** | Bot | ✅ Ready |
| **Telegram Bot (BJS)** | Bots.Business | ✅ Ready |
| **Standalone Web Script** | PHP Script | ✅ Ready |

---

## ⚙️ How It Works

```
Customer                          Your System
   │                                   │
   │  1. See your Binance UID          │
   │◄──────────────────────────────────│
   │                                   │
   │  2. Send USDT via Binance Pay     │
   │──────────────────────────────────►│
   │                                   │
   │  3. Paste Order ID from app       │
   │──────────────────────────────────►│
   │                                   │
   │       4. Verify via Binance API   │
   │                  (amount + time)  │
   │                                   │
   │  5. Payment Confirmed ✅          │
   │◄──────────────────────────────────│
```

No manual approval. No third-party. Fully automated.

---

## 📁 Directory Structure

```
binance-gateway/
│
├── 📂 whmcs/                          # WHMCS Payment Gateway
│   └── modules/gateways/
│       ├── binance.php
│       ├── binance/BinanceClient.php
│       └── callback/binance.php
│
├── 📂 woocommerce/                    # WooCommerce Plugin
│   └── wc-binance-pay/
│       ├── wc-binance-pay.php
│       └── includes/
│           ├── class-binance-client.php
│           └── class-wc-binance-gateway.php
│
├── 📂 bots/
│   ├── python/                        # Python Telegram Bot
│   │   ├── bot.py
│   │   ├── binance_client.py
│   │   ├── config.py
│   │   └── requirements.txt
│   │
│   ├── php/                           # PHP Telegram Bot (Webhook)
│   │   ├── bot.php
│   │   ├── binance.php
│   │   ├── config.php
│   │   └── database.php
│   │
│   └── bjs/                           # Bots.Business (BJS) Bot
│       ├── start.js / deposit.js
│       ├── balance.js / stats.js
│       ├── admin_panel.js
│       └── proxy/binance_proxy.php
│
├── 📂 scripts/web/                    # Standalone PHP Scripts
│   ├── BinanceClient.php
│   ├── config.php
│   ├── verify.php
│   └── history.php
│
└── 📂 testing/
    └── binance-test.php
```

---

## 🔧 Installation Guide

### WHMCS

1. Copy files to your WHMCS root:
   ```
   modules/gateways/binance.php
   modules/gateways/binance/BinanceClient.php
   modules/gateways/callback/binance.php
   ```
2. Go to **Admin → Setup → Payment Gateways → All Payment Gateways**
3. Activate **Binance Pay (USDT)**
4. Enter your **API Key**, **API Secret**, and **Binance UID**

---

### WooCommerce

1. Upload `woocommerce/wc-binance-pay/` → `/wp-content/plugins/`
2. Activate in **WordPress → Plugins**
3. Go to **WooCommerce → Settings → Payments**
4. Enable **Binance Pay (USDT)** and enter credentials

---

### Python Telegram Bot

```bash
cd bots/python
pip install -r requirements.txt
```

Edit `config.py`:
```python
BOT_TOKEN          = "your_telegram_bot_token"
BINANCE_UID        = "your_binance_uid"
BINANCE_API_KEY    = "your_api_key"
BINANCE_API_SECRET = "your_api_secret"
```

```bash
python bot.py
```

**Commands:** `/start` `/deposit` `/balance` `/cancel`

---

### PHP Telegram Bot

1. Upload all files from `bots/php/` to your server
2. Edit `config.php` with your credentials
3. Set webhook:
   ```
   https://api.telegram.org/bot{TOKEN}/setWebhook?url=https://yourdomain.com/bot.php
   ```
4. MySQL tables auto-create on first request

**Commands:** `/start` `/deposit` `/balance` `/cancel`

---

### BJS Bot (Bots.Business)

1. Create a bot at [bots.business](https://bots.business)
2. Set Bot Property `ADMIN_ID` = your Telegram ID
3. Import all `.js` files as commands
4. Upload `proxy/binance_proxy.php` to your web server
5. Send `/admin` to configure everything from Telegram

**Commands:** `/start` `/deposit` `/balance` `/admin` `/stats`

---

### Standalone Web Script

1. Upload `scripts/web/` to your PHP server
2. Edit `config.php` with your credentials
3. Use `verify.php` to verify payments
4. Use `history.php` to view transaction history

---

## 🔑 Binance API Setup

1. Login to [Binance](https://www.binance.com)
2. Go to **Profile → API Management**
3. Create a new API key
4. Set permission to **Read Only** only
5. Copy your **API Key** and **Secret Key**

> ⚠️ Never enable Spot Trading or Withdrawal permissions — Read Only is enough.

---

## 📋 Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 7.4+ |
| Python | 3.7+ |
| WHMCS | 7.0+ |
| MySQL | 5.7+ (PHP bot only) |
| cURL | Enabled |

---

## 🧪 Testing

Upload `testing/binance-test.php` to your PHP server, add your credentials at the top, and open in browser to verify your API connection.

---

## 📄 License

MIT License — free to use, modify, and distribute.

---

## 👨‍💻 Author

**Md Rahul Islam**
- GitHub: [@mdrahulislammri](https://github.com/mdrahulislammri)
- Repo: [binance-gateway](https://github.com/mdrahulislammri/binance-gateway)
