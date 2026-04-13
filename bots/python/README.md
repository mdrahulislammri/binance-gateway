# Binance Pay — Python Telegram Bot

## Setup

1. Install dependencies:
```bash
pip install -r requirements.txt
```

2. Edit `config.py`:
```python
BOT_TOKEN          = "your_telegram_bot_token"
BINANCE_UID        = "your_binance_uid"
BINANCE_API_KEY    = "your_api_key"
BINANCE_API_SECRET = "your_api_secret"
```

3. Run:
```bash
python bot.py
```

## Commands

| Command | Description |
|---------|-------------|
| `/start` | Welcome message |
| `/deposit` | Start deposit flow |
| `/balance` | Check balance |
| `/cancel` | Cancel current action |

## Flow

1. User sends `/deposit`
2. Bot asks for amount
3. Bot shows Binance UID
4. User sends USDT via Binance Pay
5. User pastes Order ID
6. Bot verifies via Binance API
7. Balance added automatically
