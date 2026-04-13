# Binance Pay — WHMCS Payment Gateway

Accept USDT payments via Binance Pay (UID transfer) in WHMCS. No third-party payment processor needed — payments go directly to your Binance account.

## How It Works

1. Customer sees your **Binance UID** on the invoice page
2. Customer sends exact USDT via **Binance Pay** (Send → UID)
3. Customer pastes their **Order ID** from Binance app
4. Module verifies via **Binance API** automatically
5. Invoice marked as paid — no manual approval needed

## Requirements

- WHMCS 7.0 or higher
- PHP 7.4 or higher
- Binance account with Read-Only API access
- cURL extension enabled

## Installation

1. Upload `modules/gateways/binance.php` → `/modules/gateways/binance.php`
2. Upload `modules/gateways/binance/` → `/modules/gateways/binance/`
3. Upload `modules/gateways/callback/binance.php` → `/modules/gateways/callback/binance.php`
4. Go to **Admin → Setup → Payment Gateways → All Payment Gateways**
5. Activate **Binance Pay (USDT)**
6. Configure settings (see below)

## Configuration

| Field | Description |
|-------|-------------|
| API Key | Binance API Key — Read-Only permission only |
| API Secret | Binance API Secret Key |
| Binance UID | Your Binance UID (customers send to this) |
| Accepted Coin | Coin to accept (default: USDT) |

## Binance API Setup

1. Login to [Binance](https://www.binance.com)
2. Go to **Profile → API Management**
3. Create API key with **Read Only** permission only
4. Copy **API Key** and **Secret Key**

## Support

- GitHub: [mdrahulislammri/binance-gateway](https://github.com/mdrahulislammri/binance-gateway)

## License

MIT License — see [LICENSE](LICENSE)

## Author

**Md Rahul Islam** — [GitHub](https://github.com/mdrahulislammri)
