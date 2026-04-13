# Binance Pay — PHP Telegram Bot

No dependencies. Pure PHP. Webhook based.

## Setup

1. Upload all files to your server (public folder)
2. Edit `config.php` with your credentials
3. Set webhook:
```
https://api.telegram.org/bot{YOUR_TOKEN}/setWebhook?url=https://yourdomain.com/bot.php
```
4. Create MySQL database and set credentials in `config.php` — tables auto-create on first request

## Commands

| Command | Description |
|---------|-------------|
| `/start` | Welcome message |
| `/deposit` | Start deposit flow |
| `/balance` | Check balance |
| `/cancel` | Cancel current action |

## Files

| File | Description |
|------|-------------|
| `bot.php` | Main webhook handler |
| `config.php` | Credentials & settings |
| `database.php` | MySQL helper (auto table create) |
| `binance.php` | Binance API client |
