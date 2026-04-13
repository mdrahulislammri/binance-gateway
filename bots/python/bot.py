"""
Binance Pay Telegram Payment Bot
Author: Md Rahul Islam <https://github.com/mdrahulislammri>

Commands:
  /start   — Welcome message
  /deposit — Start deposit flow
  /balance — Check balance
  /cancel  — Cancel current action
"""

import json
import os
import asyncio
import logging
from telegram import Update, ReplyKeyboardMarkup, ReplyKeyboardRemove
from telegram.ext import (
    Application, CommandHandler, MessageHandler,
    ConversationHandler, filters, ContextTypes
)

from config import BOT_TOKEN, BINANCE_UID, BINANCE_API_KEY, BINANCE_API_SECRET, COIN, MAX_AGE_HOURS
from binance_client import BinanceClient

logging.basicConfig(format="%(asctime)s - %(levelname)s - %(message)s", level=logging.INFO)

# ─── Conversation states ──────────────────────────────────────────────────────
ENTER_AMOUNT, ENTER_ORDER_ID = range(2)

# ─── Data store (JSON file — replace with DB in production) ──────────────────
DATA_FILE   = os.path.join(os.path.dirname(__file__), "data.json")
USED_FILE   = os.path.join(os.path.dirname(__file__), "used_ids.json")

def load_data() -> dict:
    if not os.path.exists(DATA_FILE):
        return {}
    with open(DATA_FILE) as f:
        return json.load(f)

def save_data(data: dict) -> None:
    with open(DATA_FILE, "w") as f:
        json.dump(data, f, indent=2)

def get_balance(user_id: int) -> float:
    data = load_data()
    return float(data.get(str(user_id), {}).get("balance", 0))

def add_balance(user_id: int, amount: float, order_id: str) -> None:
    data = load_data()
    uid  = str(user_id)
    if uid not in data:
        data[uid] = {"balance": 0, "history": []}
    data[uid]["balance"] = round(float(data[uid]["balance"]) + amount, 2)
    data[uid]["history"].append({"order_id": order_id, "amount": amount})
    save_data(data)

def is_used(order_id: str) -> bool:
    if not os.path.exists(USED_FILE):
        return False
    with open(USED_FILE) as f:
        return order_id in json.load(f)

def mark_used(order_id: str) -> None:
    used = []
    if os.path.exists(USED_FILE):
        with open(USED_FILE) as f:
            used = json.load(f)
    if order_id not in used:
        used.append(order_id)
    with open(USED_FILE, "w") as f:
        json.dump(used, f)

# ─── Handlers ─────────────────────────────────────────────────────────────────

async def start(update: Update, ctx: ContextTypes.DEFAULT_TYPE):
    await update.message.reply_text(
        f"Welcome to Binance Pay Bot!\n\n"
        f"Use /deposit to add funds\n"
        f"Use /balance to check your balance"
    )

async def balance(update: Update, ctx: ContextTypes.DEFAULT_TYPE):
    bal = get_balance(update.effective_user.id)
    await update.message.reply_text(f"Your balance: {bal:.2f} {COIN}")

async def deposit_start(update: Update, ctx: ContextTypes.DEFAULT_TYPE):
    await update.message.reply_text(
        "Enter the amount you want to deposit (USDT):\n\nExample: 10.00",
        reply_markup=ReplyKeyboardRemove()
    )
    return ENTER_AMOUNT

async def enter_amount(update: Update, ctx: ContextTypes.DEFAULT_TYPE):
    try:
        amount = float(update.message.text.strip())
        if amount <= 0:
            raise ValueError
    except ValueError:
        await update.message.reply_text("Invalid amount. Please enter a valid number (e.g. 10.00):")
        return ENTER_AMOUNT

    ctx.user_data["amount"] = amount

    await update.message.reply_text(
        f"Send exactly *{amount:.2f} {COIN}* to this Binance UID:\n\n"
        f"`{BINANCE_UID}`\n\n"
        f"Binance App → Pay → Send → enter UID → send *{amount:.2f} {COIN}*\n\n"
        f"After sending, paste your *Order ID* below:",
        parse_mode="Markdown"
    )
    return ENTER_ORDER_ID

async def enter_order_id(update: Update, ctx: ContextTypes.DEFAULT_TYPE):
    order_id = update.message.text.strip()
    amount   = ctx.user_data.get("amount", 0)
    user_id  = update.effective_user.id

    if not order_id.isdigit() or len(order_id) < 15:
        await update.message.reply_text("Invalid Order ID format. Please check and try again:")
        return ENTER_ORDER_ID

    if is_used(order_id):
        await update.message.reply_text("This Order ID has already been used.")
        return ConversationHandler.END

    await update.message.reply_text("Verifying payment, please wait...")

    client = BinanceClient(BINANCE_API_KEY, BINANCE_API_SECRET)
    result = client.verify_by_order_id(order_id, amount, COIN, MAX_AGE_HOURS)

    if result is None:
        await update.message.reply_text(f"Verification failed:\n{client.get_error()}")
        return ConversationHandler.END

    mark_used(order_id)
    add_balance(user_id, amount, order_id)
    new_balance = get_balance(user_id)

    await update.message.reply_text(
        f"Payment verified!\n\n"
        f"Amount added: *{amount:.2f} {COIN}*\n"
        f"New balance: *{new_balance:.2f} {COIN}*",
        parse_mode="Markdown"
    )
    return ConversationHandler.END

async def cancel(update: Update, ctx: ContextTypes.DEFAULT_TYPE):
    await update.message.reply_text("Cancelled.", reply_markup=ReplyKeyboardRemove())
    return ConversationHandler.END

# ─── Main ─────────────────────────────────────────────────────────────────────

async def _main():
    app = Application.builder().token(BOT_TOKEN).build()

    conv = ConversationHandler(
        entry_points=[CommandHandler("deposit", deposit_start)],
        states={
            ENTER_AMOUNT:   [MessageHandler(filters.TEXT & ~filters.COMMAND, enter_amount)],
            ENTER_ORDER_ID: [MessageHandler(filters.TEXT & ~filters.COMMAND, enter_order_id)],
        },
        fallbacks=[CommandHandler("cancel", cancel)],
    )

    app.add_handler(CommandHandler("start",   start))
    app.add_handler(CommandHandler("balance", balance))
    app.add_handler(conv)

    async with app:
        await app.start()
        logging.info("Bot started...")
        await app.updater.start_polling()
        await asyncio.Event().wait()  # run forever
        await app.updater.stop()
        await app.stop()

if __name__ == "__main__":
    asyncio.run(_main())
