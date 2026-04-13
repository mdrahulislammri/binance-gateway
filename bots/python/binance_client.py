"""
Binance Pay API Client
Author: Md Rahul Islam <https://github.com/mdrahulislammri>
"""

import time
import hmac
import hashlib
import requests
from typing import Optional


class BinanceClient:
    BASE_URL = "https://api.binance.com"

    def __init__(self, api_key: str, api_secret: str):
        self.api_key    = api_key
        self.api_secret = api_secret
        self.last_error = ""

    def verify_by_order_id(self, order_id: str, amount: float, coin: str = "USDT", max_age_hours: int = 24) -> Optional[dict]:
        transactions = self.get_pay_transactions(100)
        if transactions is None:
            return None

        for tx in transactions:
            if tx.get("orderId") != order_id:
                continue

            # Incoming = positive amount
            tx_amount = float(tx.get("amount", 0))
            if tx_amount <= 0:
                self.last_error = "This is an outgoing payment, not an incoming one."
                return None

            # Coin check
            if tx.get("currency", "").upper() != coin.upper():
                self.last_error = f"Coin mismatch. Expected {coin} but got {tx.get('currency', '')}."
                return None

            # Amount check
            if abs(tx_amount - amount) > 0.01:
                self.last_error = f"Amount mismatch. Expected {amount:.2f} but got {tx_amount:.2f} {coin}."
                return None

            # Age check — transactionTime is in milliseconds
            tx_time = int(tx.get("transactionTime", 0)) / 1000
            if tx_time > 0 and (time.time() - tx_time) > (max_age_hours * 3600):
                self.last_error = f"Transaction is older than {max_age_hours} hours."
                return None

            return tx

        self.last_error = "Order ID not found in Binance Pay history."
        return None

    def get_pay_transactions(self, limit: int = 100) -> Optional[list]:
        params    = {"limit": min(limit, 100), "timestamp": int(time.time() * 1000)}
        query     = "&".join(f"{k}={v}" for k, v in params.items())
        signature = hmac.new(self.api_secret.encode(), query.encode(), hashlib.sha256).hexdigest()
        url       = f"{self.BASE_URL}/sapi/v1/pay/transactions?{query}&signature={signature}"

        try:
            resp = requests.get(url, headers={"X-MBX-APIKEY": self.api_key}, timeout=30)
            data = resp.json()
        except Exception as e:
            self.last_error = f"Connection error: {e}"
            return None

        if data.get("code") != "000000":
            self.last_error = data.get("errorMessage") or data.get("message") or "Failed to fetch transactions."
            return None

        return data.get("data", [])

    def get_error(self) -> str:
        return self.last_error or "Unknown error."
