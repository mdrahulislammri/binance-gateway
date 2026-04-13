# Binance Pay — Bots.Business (BJS) Integration

## Setup

1. Create a bot on [Bots.Business](https://bots.business)
2. Set **Bot Property** `ADMIN_ID` = your Telegram ID
3. Import all commands below
4. Use `/admin` to configure settings from Telegram

## Bot Properties

| Property | Description |
|----------|-------------|
| `ADMIN_ID` | Your Telegram ID (required first) |

All other settings are configured via `/admin` commands.

## Commands

| Command | File | Description |
|---------|------|-------------|
| `/start` | start.js | Welcome |
| `/deposit` | deposit.js | Start deposit |
| `/balance` | balance.js | Check balance |
| `/admin` | admin_panel.js | Admin panel |
| `/set_uid` | set_uid.js | Set Binance UID |
| `/set_apikey` | set_apikey.js | Set API Key |
| `/set_apisecret` | set_apisecret.js | Set API Secret |
| `/set_coin` | set_coin.js | Set coin (USDT) |
| `/set_maxage` | set_maxage.js | Set max age (hours) |
| `/stats` | stats.js | View statistics |
| `*` | handle_input.js | Handle all text input |
| `/binance_verify_result` | binance_verify_result.js | API callback |
| `/binance_verify_error` | binance_verify_error.js | API error callback |

## First Time Setup

1. Set `ADMIN_ID` in Bot Properties
2. Send `/admin` to your bot
3. Use `/set_uid`, `/set_apikey`, `/set_apisecret` to configure
4. Done — bot is ready!
